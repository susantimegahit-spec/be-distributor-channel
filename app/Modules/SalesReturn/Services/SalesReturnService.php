<?php

namespace App\Modules\SalesReturn\Services;

use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\SalesReturnDetail;
use App\Models\SalesOrderDetail;
use App\Modules\SalesReturn\Repositories\SalesReturnRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class SalesReturnService
{
    protected SalesReturnRepositoryInterface $repository;

    public function __construct(SalesReturnRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all sales returns with filters.
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->repository->getAll($filters);
    }

    /**
     * Find a sales return by ID.
     */
    public function getById(int $id): ?SalesReturn
    {
        return $this->repository->getById($id);
    }

    /**
     * Create and submit a goods return request.
     */
    public function createReturnRequest(array $payload, array $uploadedFiles, int $userId): SalesReturn
    {
        // 1. Fetch and validate Sales Order
        $salesOrder = SalesOrder::find($payload['sales_order_id']);
        if (!$salesOrder) {
            throw ValidationException::withMessages(['sales_order_id' => 'Sales Order tidak ditemukan.']);
        }

        if (!in_array(strtoupper($salesOrder->status), ['DELIVERY', 'ARRIVED'])) {
            throw ValidationException::withMessages(['sales_order_id' => 'Retur hanya dapat diajukan jika status Sales Order adalah DELIVERY atau ARRIVED.']);
        }

        // 2. Validate attachments count
        if (count($uploadedFiles) > 5) {
            throw ValidationException::withMessages(['attachments' => 'Maksimal bukti foto yang diunggah adalah 5 file.']);
        }

        $details = [];
        $docTotal = 0;

        // 3. Process return items and check quantities
        foreach ($payload['items'] as $item) {
            $soDetailId = $item['sales_order_detail_id'];
            $qtyToReturn = (float)$item['quantity'];

            if ($qtyToReturn <= 0) {
                throw ValidationException::withMessages(['items' => 'Kuantitas retur harus lebih besar dari 0.']);
            }

            // Find corresponding SO line item
            $soDetail = SalesOrderDetail::where('sales_order_id', $salesOrder->id)
                ->where('id', $soDetailId)
                ->first();

            if (!$soDetail) {
                throw ValidationException::withMessages(['items' => 'Item detail Sales Order tidak valid atau tidak cocok dengan SO terkait.']);
            }

            // Calculate total quantity already returned for this SO line item
            $previouslyReturned = SalesReturnDetail::where('sales_order_detail_id', $soDetailId)
                ->whereHas('salesReturn', function ($query) {
                    $query->whereIn('status', ['SUBMITTED', 'APPROVED', 'SAP_INTEGRATED']);
                })
                ->sum('quantity');

            $maxAllowed = (float)$soDetail->quantity - (float)$previouslyReturned;

            if ($qtyToReturn > $maxAllowed) {
                throw ValidationException::withMessages([
                    'items' => "Kuantitas retur untuk item {$soDetail->item_code} melebihi batas. Maksimal yang dapat diretur saat ini: " . max(0, $maxAllowed)
                ]);
            }

            $lineTotal = $qtyToReturn * (float)$soDetail->unit_price;
            $docTotal += $lineTotal;

            $details[] = [
                'sales_order_detail_id' => $soDetailId,
                'item_code' => $soDetail->item_code,
                'quantity' => $qtyToReturn,
                'unit_msr' => $soDetail->unit_msr,
                'uom_entry' => $soDetail->uom_entry,
                'unit_price' => $soDetail->unit_price,
                'line_total' => $lineTotal,
                'reason' => $item['reason'] ?? null,
            ];
        }

        // 4. Generate return number: RET/YYYYMM/XXXX
        $prefix = 'RET/' . date('Ym') . '/';
        $count = SalesReturn::where('return_no', 'like', $prefix . '%')->count();
        $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        $returnNo = $prefix . $sequence;

        // 5. Handle image uploads and compression (GD)
        $attachments = [];
        foreach ($uploadedFiles as $file) {
            $attachments[] = $this->compressAndStoreImage($file, $userId);
        }

        // 6. Assemble return request data
        $returnData = [
            'return_no' => $returnNo,
            'sales_order_id' => $salesOrder->id,
            'distributor_id' => $salesOrder->distributor_id,
            'card_code' => $salesOrder->card_code,
            'customer_name' => $salesOrder->customer_name,
            'reason' => $payload['reason'] ?? null,
            'doc_total' => $docTotal,
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
            'created_by' => $userId,
            'updated_by' => $userId,
            'details' => $details,
            'attachments' => $attachments,
        ];

        return $this->repository->create($returnData);
    }

    /**
     * Approve return request (admin sales).
     */
    public function approve(SalesReturn $salesReturn, int $userId): SalesReturn
    {
        if ($salesReturn->status !== 'SUBMITTED') {
            throw ValidationException::withMessages(['status' => 'Hanya retur berstatus SUBMITTED yang dapat disetujui.']);
        }

        $data = [
            'status' => 'APPROVED',
            'approved_by' => $userId,
            'approved_at' => now(),
            'updated_by' => $userId,
        ];

        return $this->repository->update($salesReturn, $data);
    }

    /**
     * Reject return request (admin sales).
     */
    public function reject(SalesReturn $salesReturn, string $reason, int $userId): SalesReturn
    {
        if ($salesReturn->status !== 'SUBMITTED') {
            throw ValidationException::withMessages(['status' => 'Hanya retur berstatus SUBMITTED yang dapat ditolak.']);
        }

        $data = [
            'status' => 'REJECTED',
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'reject_reason' => $reason,
            'updated_by' => $userId,
        ];

        return $this->repository->update($salesReturn, $data);
    }

    /**
     * Compress and store image utilizing PHP GD extension.
     */
    private function compressAndStoreImage($file, int $userId): array
    {
        $fileSize = $file->getSize();
        $originalName = $file->getClientOriginalName();
        $mime = $file->getMimeType();

        // If <= 200kb, save it directly
        if ($fileSize <= 200 * 1024) {
            $path = $file->store('returns', 'public');
            return [
                'file_name' => $originalName,
                'file_path' => $path,
                'file_type' => $mime,
                'file_size' => $fileSize,
                'uploaded_by' => $userId,
            ];
        }

        $imagePath = $file->getRealPath();
        $img = null;
        if ($mime === 'image/png') {
            $img = @imagecreatefrompng($imagePath);
        } elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            $img = @imagecreatefromjpeg($imagePath);
        } elseif ($mime === 'image/gif') {
            $img = @imagecreatefromgif($imagePath);
        } elseif ($mime === 'image/webp') {
            $img = @imagecreatefromwebp($imagePath);
        }

        if (!$img) {
            // Fallback to storing as is
            $path = $file->store('returns', 'public');
            return [
                'file_name' => $originalName,
                'file_path' => $path,
                'file_type' => $mime,
                'file_size' => $fileSize,
                'uploaded_by' => $userId,
            ];
        }

        // Check and resize if width or height > 1600
        $width = imagesx($img);
        $height = imagesy($img);
        $maxDim = 1600;

        if ($width > $maxDim || $height > $maxDim) {
            if ($width > $height) {
                $newWidth = $maxDim;
                $newHeight = (int)($height * ($maxDim / $width));
            } else {
                $newHeight = $maxDim;
                $newWidth = (int)($width * ($maxDim / $height));
            }
            $resizedImg = imagecreatetruecolor($newWidth, $newHeight);
            
            // Transparency
            imagealphablending($resizedImg, false);
            imagesavealpha($resizedImg, true);
            
            imagecopyresampled($resizedImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($img);
            $img = $resizedImg;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'return_img_');
        $quality = 80;
        $compressedSize = 0;

        do {
            $w = imagesx($img);
            $h = imagesy($img);
            $bg = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefill($bg, 0, 0, $white);
            imagecopy($bg, $img, 0, 0, 0, 0, $w, $h);

            imagejpeg($bg, $tempPath, $quality);
            imagedestroy($bg);

            $compressedSize = filesize($tempPath);
            $quality -= 10;
        } while ($compressedSize > 200 * 1024 && $quality >= 10);

        imagedestroy($img);

        $fileName = time() . '_' . uniqid() . '.jpg';
        $storedPath = 'returns/' . $fileName;
        Storage::disk('public')->put($storedPath, file_get_contents($tempPath));
        unlink($tempPath);

        $info = pathinfo($originalName);
        $newOriginalName = $info['filename'] . '.jpg';

        return [
            'file_name' => $newOriginalName,
            'file_path' => $storedPath,
            'file_type' => 'image/jpeg',
            'file_size' => $compressedSize,
            'uploaded_by' => $userId,
        ];
    }
}
