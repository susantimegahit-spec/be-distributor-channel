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

        // Fetch DO lines from SAP to find the original DO quantity
        $doLines = [];
        $soNum = $salesOrder->sap_doc_num ?: $salesOrder->order_no;
        if ($soNum) {
            try {
                $doLines = $this->getDoBySo($soNum);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Gagal mengambil data DO by SO untuk retur: " . $e->getMessage());
            }
        }

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
                    $query->whereIn('status', ['waiting_admin_sales', 'waiting_finance', 'approved']);
                })
                ->sum('quantity');

            $maxAllowed = (float)$soDetail->quantity - (float)$previouslyReturned;

            if ($qtyToReturn > $maxAllowed) {
                throw ValidationException::withMessages([
                    'items' => "Kuantitas retur untuk item {$soDetail->item_code} melebihi batas. Maksimal yang dapat diretur saat ini: " . max(0, $maxAllowed)
                ]);
            }

            // Read DO quantity directly from FE request (either do_qty or do_quantity)
            $doQty = null;
            if (isset($item['do_quantity'])) {
                $doQty = (float)$item['do_quantity'];
            } elseif (isset($item['do_qty'])) {
                $doQty = (float)$item['do_qty'];
            }

            // Fallback: match from SAP DO lines if not provided by FE
            if ($doQty === null) {
                $matchedDoLine = null;
                if (!empty($doLines)) {
                    foreach ($doLines as $line) {
                        $matchDo = true;
                        if (isset($item['do_num']) && isset($line['DocNum'])) {
                            $matchDo = (string)$line['DocNum'] === (string)$item['do_num'];
                        }
                        $matchBaseline = true;
                        if (isset($item['baseline']) && isset($line['BaseLine'])) {
                            $matchBaseline = (int)$line['BaseLine'] === (int)$item['baseline'];
                        }
                        
                        $matchItem = strtoupper(trim($line['ItemCode'] ?? '')) === strtoupper(trim($soDetail->item_code));

                        if ($matchDo && $matchBaseline && $matchItem) {
                            $matchedDoLine = $line;
                            break;
                        }
                    }

                    // Fallback to match by item code only if do_num/baseline matching fails or was not precise
                    if (!$matchedDoLine) {
                        foreach ($doLines as $line) {
                            if (strtoupper(trim($line['ItemCode'] ?? '')) === strtoupper(trim($soDetail->item_code))) {
                                $matchedDoLine = $line;
                                break;
                            }
                        }
                    }
                }

                if ($matchedDoLine) {
                    $doQty = (float)($matchedDoLine['Delivered_Qty'] ?? $matchedDoLine['Quantity'] ?? $matchedDoLine['Qty'] ?? 0.0);
                } else {
                    $doQty = (float)$soDetail->quantity; // Fallback to SO quantity
                }
            }

            $doDate = null;
            if (!empty($item['do_date'])) {
                try {
                    if (preg_match('/^\d{8}$/', $item['do_date'])) {
                        $doDate = \Carbon\Carbon::createFromFormat('Ymd', $item['do_date'])->format('Y-m-d');
                    } else {
                        $doDate = \Carbon\Carbon::parse($item['do_date'])->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    $doDate = null;
                }
            }

            $lineTotal = $qtyToReturn * (float)$soDetail->unit_price;
            $docTotal += $lineTotal;

            $details[] = [
                'sales_order_detail_id' => $soDetailId,
                'item_code' => $soDetail->item_code,
                'quantity' => $qtyToReturn,
                'do_quantity' => $doQty,
                'unit_msr' => $soDetail->unit_msr,
                'uom_entry' => $soDetail->uom_entry,
                'unit_price' => $soDetail->unit_price,
                'line_total' => $lineTotal,
                'reason' => $item['reason'] ?? null,
                'do_num' => $item['do_num'] ?? null,
                'do_date' => $doDate,
                'baseline' => isset($item['baseline']) ? (int)$item['baseline'] : null,
                'status' => 'waiting_admin_sales',
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
            'status' => 'waiting_admin_sales',
            'submitted_at' => now(),
            'created_by' => $userId,
            'updated_by' => $userId,
            'details' => $details,
            'attachments' => $attachments,
        ];

        return $this->repository->create($returnData);
    }

    /**
     * Approve return request (admin sales) and integrate to SAP.
     */
    public function approve(SalesReturn $salesReturn, int $userId): SalesReturn
    {
        if ($salesReturn->status !== 'waiting_admin_sales' && $salesReturn->status !== 'waiting_finance') {
            throw ValidationException::withMessages(['status' => 'Hanya retur berstatus waiting_admin_sales atau waiting_finance yang dapat disetujui.']);
        }

        if ($salesReturn->status === 'waiting_admin_sales') {
            // Stage 1: Approve by Admin Sales
            $salesReturn->update([
                'status' => 'waiting_finance',
                'approved_admin_by' => $userId,
                'approved_admin_at' => now(),
                'approved_by' => $userId,
                'approved_at' => now(),
                'updated_by' => $userId,
            ]);

            $salesReturn->details()->update(['status' => 'waiting_finance']);

            return $salesReturn;
        }

        // 1. Fetch associated Sales Order
        $salesOrder = $salesReturn->salesOrder;
        if (!$salesOrder) {
            throw new \Exception('Sales Order terkait tidak ditemukan.');
        }

        // Ensure details relationship is loaded
        $salesReturn->load('details.salesOrderDetail');

        // Check if details already have do_num and baseline saved
        $hasSavedDO = true;
        foreach ($salesReturn->details as $detail) {
            if (empty($detail->do_num) || $detail->baseline === null) {
                $hasSavedDO = false;
                break;
            }
        }

        $mappedLines = [];
        $doNum = null;

        if ($hasSavedDO) {
            // Use saved do_num and baseline directly!
            foreach ($salesReturn->details as $detail) {
                $doNum = $detail->do_num;
                $whsCode = $detail->salesOrderDetail->whs_code ?? '01';

                $mappedLines[] = [
                    'BaseLine' => (int)$detail->baseline,
                    'Quantity' => (float)$detail->quantity,
                    'WhsCode' => $whsCode,
                    'BinActivto' => 'N',
                    'Lines_BinTO' => [],
                ];
            }
        } else {
            // Fallback: Fetch DO by SO lines from SAP and match dynamically by ItemCode
            $soNum = $salesOrder->sap_doc_num ?: $salesOrder->order_no;
            try {
                $sapDoResponse = \Illuminate\Support\Facades\Http::timeout(15)->post('http://103.18.133.187:3100/api/GetDObySO', [
                    'CustomQuery' => $soNum,
                ]);

                if (!$sapDoResponse->successful()) {
                    throw new \Exception('Gagal menghubungi API SAP GetDObySO.');
                }

                $sapDoBody = $sapDoResponse->json();
                if (isset($sapDoBody['ErrorCode']) && $sapDoBody['ErrorCode'] !== 0) {
                    throw new \Exception('API SAP GetDObySO error: ' . ($sapDoBody['Message'] ?? 'Unknown error'));
                }

                $doLines = $sapDoBody['Result'] ?? [];
            } catch (\Exception $e) {
                $salesReturn->update(['sap_error' => 'Gagal mengambil data DO dari SAP: ' . $e->getMessage()]);
                throw new \Exception('Gagal memproses approval: ' . $e->getMessage());
            }

            foreach ($salesReturn->details as $detail) {
                $matchedDoLine = null;
                foreach ($doLines as $line) {
                    if (strtoupper(trim($line['ItemCode'])) === strtoupper(trim($detail->item_code))) {
                        $matchedDoLine = $line;
                        break;
                    }
                }

                if (!$matchedDoLine) {
                    throw new \Exception("Item {$detail->item_code} tidak ditemukan pada data Delivery Order (DO) di SAP.");
                }

                $doNum = $matchedDoLine['DocNum']; // Get DO Number
                $whsCode = $detail->salesOrderDetail->whs_code ?? '01';

                $mappedLines[] = [
                    'BaseLine' => (int)$matchedDoLine['BaseLine'],
                    'Quantity' => (float)$detail->quantity,
                    'WhsCode' => $whsCode,
                    'BinActivto' => 'N',
                    'Lines_BinTO' => [],
                ];
            }
        }

        if (!$doNum) {
            throw new \Exception('Nomor DO tidak ditemukan untuk mencocokkan item retur.');
        }

        // 4. Construct addretur payload
        $approver = \App\Models\User::find($userId);
        $payload = [
            'NoDO' => (int)$doNum,
            'DocDate' => now()->format('Y-m-d'),
            'DocDueDate' => now()->format('Y-m-d'),
            'Comments' => $salesReturn->reason,
            'AddonId' => 2,
            'UserId' => $approver->username ?? 'USER_API_01',
            'Lines' => $mappedLines,
        ];

        // 5. Post to addretur API
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)->post('http://103.18.133.187:3100/api/addretur', $payload);
            
            if (!$response->successful()) {
                throw new \Exception('Gagal menghubungi API SAP addretur.');
            }

            $body = $response->json();
            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                throw new \Exception('API SAP addretur error: ' . ($body['Message'] ?? 'Unknown error'));
            }

            // Extract DocEntry and DocNum from SAP response
            $sapResult = $body['Result'][0] ?? $body['result'][0] ?? null;
            $sapDocEntry = null;
            $sapDocNum = null;

            if ($sapResult) {
                $sapDocEntry = $sapResult['DocEntry'] ?? $sapResult['docEntry'] ?? $sapResult['doc_entry'] ?? null;
                $sapDocNum = $sapResult['DocNum'] ?? $sapResult['docNum'] ?? $sapResult['doc_num'] ?? null;
            }

            // If empty, extract from message
            $message = $body['Message'] ?? $body['message'] ?? '';
            if (empty($sapDocNum) && !empty($message)) {
                if (preg_match('/DocNum:\s*([A-Za-z0-9_-]+)/i', $message, $matches)) {
                    $sapDocNum = $matches[1];
                }
            }
            if (empty($sapDocEntry) && !empty($message)) {
                if (preg_match('/DocEntry:\s*(\d+)/i', $message, $matches)) {
                    $sapDocEntry = (int)$matches[1];
                }
            }

            // 6. Update local records on success
            $salesReturn->update([
                'status' => 'approved',
                'approved_finance_by' => $userId,
                'approved_finance_at' => now(),
                'approved_by' => $userId,
                'approved_at' => now(),
                'updated_by' => $userId,
                'sap_doc_entry' => $sapDocEntry,
                'sap_doc_num' => $sapDocNum,
                'sap_error' => null,
            ]);
            $salesReturn->details()->update(['status' => 'approved']);

            return $salesReturn;

        } catch (\Exception $e) {
            $salesReturn->update([
                'sap_error' => $e->getMessage(),
            ]);
            throw new \Exception('Gagal integrasi SAP addretur: ' . $e->getMessage());
        }
    }

    /**
     * Get Delivery Order (DO) lines by Sales Order (SO) DocNum from SAP.
     *
     * @param string $soNum
     * @return array
     */
    public function getDoBySo(string $soNum): array
    {
        // If numeric local ID is provided, resolve to SAP DocNum
        if (is_numeric($soNum)) {
            $salesOrder = SalesOrder::find((int)$soNum);
            if ($salesOrder) {
                $soNum = $salesOrder->sap_doc_num ?: $salesOrder->order_no;
            }
        }

        $response = \Illuminate\Support\Facades\Http::timeout(15)->post('http://103.18.133.187:3100/api/GetDObySO', [
            'CustomQuery' => $soNum,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk mendapatkan DO by SO.');
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP GetDObySO mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        return $body['Result'] ?? [];
    }

    /**
     * Reject return request (admin sales).
     */
    public function reject(SalesReturn $salesReturn, string $reason, int $userId): SalesReturn
    {
        if ($salesReturn->status !== 'waiting_admin_sales' && $salesReturn->status !== 'waiting_finance') {
            throw ValidationException::withMessages(['status' => 'Hanya retur berstatus waiting_admin_sales atau waiting_finance yang dapat ditolak.']);
        }

        $data = [
            'status' => 'rejected',
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'reject_reason' => $reason,
            'updated_by' => $userId,
        ];

        $result = $this->repository->update($salesReturn, $data);
        $salesReturn->details()->update(['status' => 'rejected']);

        return $result;
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
