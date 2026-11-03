<?php

namespace App\Modules\PurchasingRequest\Repositories;

use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationItem;
use App\Models\PurchaseQuotationOffer;
use App\Models\PurchaseQuotationOfferItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PurchaseQuotationRepository implements PurchaseQuotationRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = PurchaseQuotation::with(['items', 'offers.items'])
            ->withCount('offers');

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', $search)
                  ->orWhere('title', 'like', $search)
                  ->orWhere('notes', 'like', $search)
                  ->orWhereHas('items', function ($itemQuery) use ($search) {
                      $itemQuery->where('item_code', 'like', $search)
                                ->orWhere('item_name', 'like', $search)
                                ->orWhere('request_number', 'like', $search);
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
        }

        if (!empty($filters['from'])) {
            $from = str_replace('-', '', $filters['from']);
            if (strlen($from) === 8) {
                $query->whereDate('created_at', '>=', substr($from, 0, 4) . '-' . substr($from, 4, 2) . '-' . substr($from, 6, 2));
            } else {
                $query->whereDate('created_at', '>=', $filters['from']);
            }
        }

        if (!empty($filters['to'])) {
            $to = str_replace('-', '', $filters['to']);
            if (strlen($to) === 8) {
                $query->whereDate('created_at', '<=', substr($to, 0, 4) . '-' . substr($to, 4, 2) . '-' . substr($to, 6, 2));
            } else {
                $query->whereDate('created_at', '<=', $filters['to']);
            }
        }

        $query->orderBy('id', 'desc');

        if (isset($filters['paginate']) && filter_var($filters['paginate'], FILTER_VALIDATE_BOOLEAN) === false) {
            return $query->get();
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?PurchaseQuotation
    {
        return PurchaseQuotation::with(['items', 'offers.items'])->find($id);
    }

    public function findByToken(string $token): ?PurchaseQuotation
    {
        return PurchaseQuotation::with(['items', 'offers.items'])->where('token', $token)->first();
    }

    public function create(array $data, array $items = []): PurchaseQuotation
    {
        return DB::transaction(function () use ($data, $items) {
            if (empty($data['number'])) {
                $data['number'] = PurchaseQuotation::generateNumber();
            }

            if (empty($data['token'])) {
                $data['token'] = PurchaseQuotation::generateToken();
            }

            if (empty($data['status'])) {
                $data['status'] = 'OPEN';
            }

            $quotation = PurchaseQuotation::create([
                'number'      => $data['number'],
                'token'       => $data['token'],
                'link'        => $data['link'] ?? null,
                'status'      => $data['status'],
                'title'       => $data['title'] ?? null,
                'notes'       => $data['notes'] ?? null,
                'user_id'     => $data['user_id'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
            ]);

            foreach ($items as $item) {
                $quotation->items()->create([
                    'request_id'     => $item['request_id'] ?? $item['requestId'] ?? null,
                    'request_number' => $item['request_number'] ?? $item['requestNumber'] ?? null,
                    'line_key'       => $item['line_key'] ?? $item['id'] ?? null,
                    'item_code'      => $item['item_code'] ?? $item['itemCode'] ?? '',
                    'item_name'      => $item['item_name'] ?? $item['itemName'] ?? '',
                    'quantity'       => $item['quantity'] ?? 1,
                    'unit'           => $item['unit'] ?? $item['uom'] ?? null,
                    'warehouse'      => $item['warehouse'] ?? $item['whs_code'] ?? null,
                    'required_date'  => $item['required_date'] ?? $item['requiredDate'] ?? null,
                    'notes'          => $item['notes'] ?? null,
                ]);
            }

            return $quotation->load(['items', 'offers.items']);
        });
    }

    public function update(int $id, array $data, ?array $items = null): ?PurchaseQuotation
    {
        return DB::transaction(function () use ($id, $data, $items) {
            $quotation = PurchaseQuotation::find($id);
            if (!$quotation) {
                return null;
            }

            $updateData = [];
            foreach (['status', 'title', 'notes', 'valid_until', 'user_id', 'link'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (!empty($updateData)) {
                $quotation->update($updateData);
            }

            if ($items !== null) {
                $quotation->items()->delete();
                foreach ($items as $item) {
                    $quotation->items()->create([
                        'request_id'     => $item['request_id'] ?? $item['requestId'] ?? null,
                        'request_number' => $item['request_number'] ?? $item['requestNumber'] ?? null,
                        'line_key'       => $item['line_key'] ?? $item['id'] ?? null,
                        'item_code'      => $item['item_code'] ?? $item['itemCode'] ?? '',
                        'item_name'      => $item['item_name'] ?? $item['itemName'] ?? '',
                        'quantity'       => $item['quantity'] ?? 1,
                        'unit'           => $item['unit'] ?? $item['uom'] ?? null,
                        'warehouse'      => $item['warehouse'] ?? $item['whs_code'] ?? null,
                        'required_date'  => $item['required_date'] ?? $item['requiredDate'] ?? null,
                        'notes'          => $item['notes'] ?? null,
                    ]);
                }
            }

            return $quotation->fresh(['items', 'offers.items']);
        });
    }

    public function delete(int $id): bool
    {
        $quotation = PurchaseQuotation::find($id);
        if (!$quotation) {
            return false;
        }

        return (bool) $quotation->delete();
    }

    public function getAllOffers(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = PurchaseQuotationOffer::with(['items', 'quotation.items']);

        if (!empty($filters['quotation_id'])) {
            $query->where('purchase_quotation_id', $filters['quotation_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', $search)
                  ->orWhere('pic_name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('phone', 'like', $search);
            });
        }

        $query->orderBy('id', 'desc');

        if (isset($filters['paginate']) && filter_var($filters['paginate'], FILTER_VALIDATE_BOOLEAN) === false) {
            return $query->get();
        }

        return $query->paginate($perPage);
    }

    public function findOfferById(int $id): ?PurchaseQuotationOffer
    {
        return PurchaseQuotationOffer::with(['items', 'quotation.items'])->find($id);
    }

    public function createOffer(PurchaseQuotation $quotation, array $vendorData, array $offerItems = []): PurchaseQuotationOffer
    {
        return DB::transaction(function () use ($quotation, $vendorData, $offerItems) {
            $totalPrice = 0.0;
            $preparedItems = [];

            $quotationItemsMap = $quotation->items->keyBy('id');

            foreach ($offerItems as $idx => $item) {
                $quotationItemId = $item['quotation_item_id'] ?? $item['quotationItemId'] ?? $item['id'] ?? null;
                $matchedItem = $quotationItemId ? $quotationItemsMap->get($quotationItemId) : null;

                $itemCode = $item['item_code'] ?? $item['itemCode'] ?? null;
                if (!$matchedItem && $itemCode) {
                    $matchedItem = $quotation->items->firstWhere('item_code', $itemCode);
                }

                $qty = (float) ($item['quantity'] ?? ($matchedItem?->quantity ?? 1));
                $unitPrice = (float) ($item['unit_price'] ?? $item['unitPrice'] ?? 0);

                $disc = $this->normalizeDiscountAndTotals($qty, $unitPrice, $item);
                $lineTotal = $disc['total_price'];
                $totalPrice += $lineTotal;

                $attachmentMeta = $this->storeOfferItemAttachment(
                    $quotation->id,
                    (int) $idx,
                    $item['attachment'] ?? $item['file'] ?? null
                );

                $preparedItems[] = [
                    'purchase_quotation_item_id' => $matchedItem?->id,
                    'item_code'                  => $item['item_code'] ?? $item['itemCode'] ?? ($matchedItem?->item_code ?? ''),
                    'item_name'                  => $item['item_name'] ?? $item['itemName'] ?? ($matchedItem?->item_name ?? ''),
                    'quantity'                   => $qty,
                    'unit'                       => $item['unit'] ?? $item['uom'] ?? ($matchedItem?->unit ?? null),
                    'unit_price'                 => $unitPrice,
                    'discount_type'              => $disc['discount_type'],
                    'discount_value'             => $disc['discount_value'],
                    'nominal_disc'               => $disc['nominal_disc'],
                    'total_price'                => $lineTotal,
                    'lead_time'                  => $item['lead_time'] ?? $item['leadTime'] ?? null,
                    'notes'                      => $item['notes'] ?? null,
                    'attachment_path'            => $attachmentMeta['path'],
                    'attachment_name'            => $attachmentMeta['name'],
                    'attachment_mime'            => $attachmentMeta['mime'],
                ];
            }

            $offer = $quotation->offers()->create([
                'company_name' => $vendorData['company'] ?? $vendorData['company_name'] ?? '',
                'pic_name'     => $vendorData['name'] ?? $vendorData['pic_name'] ?? '',
                'email'        => $vendorData['email'] ?? '',
                'phone'        => $vendorData['phone'] ?? '',
                'notes'        => $vendorData['notes'] ?? null,
                'status'       => $vendorData['status'] ?? 'SUBMITTED',
                'total_price'  => $totalPrice,
                'submitted_at' => now(),
                'ip_address'   => request()?->ip(),
            ]);

            foreach ($preparedItems as $prepItem) {
                $offer->items()->create($prepItem);
            }

            return $offer->load(['items', 'quotation.items']);
        });
    }

    public function updateOffer(int $id, array $data): ?PurchaseQuotationOffer
    {
        return DB::transaction(function () use ($id, $data) {
            $offer = PurchaseQuotationOffer::find($id);
            if (!$offer) {
                return null;
            }

            $updateData = [];
            foreach (['company_name', 'pic_name', 'email', 'phone', 'notes', 'status', 'total_price'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (isset($data['vendor']) && is_array($data['vendor'])) {
                if (isset($data['vendor']['company'])) $updateData['company_name'] = $data['vendor']['company'];
                if (isset($data['vendor']['name'])) $updateData['pic_name'] = $data['vendor']['name'];
                if (isset($data['vendor']['email'])) $updateData['email'] = $data['vendor']['email'];
                if (isset($data['vendor']['phone'])) $updateData['phone'] = $data['vendor']['phone'];
                if (isset($data['vendor']['notes'])) $updateData['notes'] = $data['vendor']['notes'];
            }

            if (!empty($updateData)) {
                $offer->update($updateData);
            }

            if (isset($data['items']) && is_array($data['items'])) {
                $existingItemsById = $offer->items->keyBy('id');
                $existingItemsByCode = $offer->items->keyBy('item_code');

                $offer->items()->delete();
                $totalPrice = 0.0;

                foreach ($data['items'] as $idx => $item) {
                    $qty = (float) ($item['quantity'] ?? 1);
                    $unitPrice = (float) ($item['unit_price'] ?? $item['unitPrice'] ?? 0);

                    $disc = $this->normalizeDiscountAndTotals($qty, $unitPrice, $item);
                    $lineTotal = $disc['total_price'];
                    $totalPrice += $lineTotal;

                    $rawAttachment = $item['attachment'] ?? $item['file'] ?? null;
                    $itemCode = $item['item_code'] ?? $item['itemCode'] ?? '';
                    $prevItem = isset($item['id']) ? $existingItemsById->get($item['id']) : null;
                    if (!$prevItem && $itemCode) {
                        $prevItem = $existingItemsByCode->get($itemCode);
                    }

                    if ($rawAttachment !== null) {
                        $attachmentMeta = $this->storeOfferItemAttachment(
                            (int) $offer->purchase_quotation_id,
                            (int) $idx,
                            $rawAttachment
                        );
                    } else {
                        $attachmentMeta = [
                            'path' => $prevItem?->attachment_path,
                            'name' => $prevItem?->attachment_name,
                            'mime' => $prevItem?->attachment_mime,
                        ];
                    }

                    $offer->items()->create([
                        'purchase_quotation_item_id' => $item['quotation_item_id'] ?? $item['quotationItemId'] ?? $item['id'] ?? ($prevItem?->purchase_quotation_item_id),
                        'item_code'                  => $itemCode,
                        'item_name'                  => $item['item_name'] ?? $item['itemName'] ?? ($prevItem?->item_name ?? ''),
                        'quantity'                   => $qty,
                        'unit'                       => $item['unit'] ?? $item['uom'] ?? ($prevItem?->unit ?? null),
                        'unit_price'                 => $unitPrice,
                        'discount_type'              => $disc['discount_type'],
                        'discount_value'             => $disc['discount_value'],
                        'nominal_disc'               => $disc['nominal_disc'],
                        'total_price'                => $lineTotal,
                        'lead_time'                  => $item['lead_time'] ?? $item['leadTime'] ?? null,
                        'notes'                      => $item['notes'] ?? null,
                        'attachment_path'            => $attachmentMeta['path'],
                        'attachment_name'            => $attachmentMeta['name'],
                        'attachment_mime'            => $attachmentMeta['mime'],
                    ]);
                }

                $offer->update(['total_price' => $totalPrice]);
            }

            return $offer->fresh(['items', 'quotation.items']);
        });
    }

    /**
     * Normalize discount type ('percent' or 'nominal'), discount value, nominal discount, and net line total.
     */
    protected function normalizeDiscountAndTotals(float $qty, float $unitPrice, array $item): array
    {
        $rawType = $item['discount_type'] ?? $item['discountType'] ?? $item['tipe_diskon'] ?? null;
        $discountType = null;
        if (is_string($rawType) && trim($rawType) !== '') {
            $lower = strtolower(trim($rawType));
            if (in_array($lower, ['percent', 'percentage', 'persen', '%'], true)) {
                $discountType = 'percent';
            } elseif (in_array($lower, ['nominal', 'amount', 'rp', 'value'], true)) {
                $discountType = 'nominal';
            }
        }

        $discountValue = (float) ($item['discount_value'] ?? $item['discountValue'] ?? $item['value_diskon'] ?? 0);
        $explicitNominal = $item['nominal_disc'] ?? $item['nominalDisc'] ?? $item['discount_amount'] ?? $item['discountAmount'] ?? null;

        $grossTotal = $qty * $unitPrice;

        if ($discountType === 'percent') {
            $calculatedNominal = round($grossTotal * ($discountValue / 100), 2);
        } elseif ($discountType === 'nominal') {
            // Nominal discount can be passed per line or calculated; if explicit nominal_disc is provided, use it, otherwise use discount_value
            $calculatedNominal = round($discountValue, 2);
        } else {
            $calculatedNominal = 0.0;
        }

        $nominalDisc = ($explicitNominal !== null && $explicitNominal !== '' && (float) $explicitNominal > 0)
            ? round((float) $explicitNominal, 2)
            : $calculatedNominal;

        if ($discountType === null && $nominalDisc > 0) {
            $discountType = 'nominal';
            if ($discountValue <= 0) {
                $discountValue = $nominalDisc;
            }
        }

        $explicitTotal = $item['total_price'] ?? $item['totalPrice'] ?? null;
        $netTotal = ($explicitTotal !== null && $explicitTotal !== '')
            ? (float) $explicitTotal
            : max(0.0, round($grossTotal - $nominalDisc, 2));

        return [
            'discount_type'  => $discountType,
            'discount_value' => $discountValue,
            'nominal_disc'   => $nominalDisc,
            'total_price'    => $netTotal,
        ];
    }

    /**
     * Store offer item attachment to S3 (and local public disk fallback).
     * Supports UploadedFile, Base64/Data URI strings, or existing URLs/paths.
     */
    protected function storeOfferItemAttachment(int $quotationId, int $index, mixed $attachment): array
    {
        if (empty($attachment)) {
            return ['path' => null, 'name' => null, 'mime' => null];
        }

        $dir = "quotation-offers/{$quotationId}/items";
        $s3Configured = !empty(config('filesystems.disks.s3.bucket'))
            || config('filesystems.disks.s3.driver') === 'local';

        // 1. UploadedFile (multipart/form-data)
        if ($attachment instanceof UploadedFile) {
            $origName = $attachment->getClientOriginalName() ?: ('attachment_' . ($index + 1));
            $ext = strtolower($attachment->getClientOriginalExtension() ?: ($attachment->guessExtension() ?: 'bin'));
            $mime = $attachment->getClientMimeType() ?: 'application/octet-stream';
            $filename = sprintf('item_%d_%s_%s.%s', $index + 1, now()->format('YmdHis'), Str::random(8), $ext);
            $relativePath = "{$dir}/{$filename}";

            if ($s3Configured) {
                try {
                    Storage::disk('s3')->putFileAs($dir, $attachment, $filename, 'public');
                } catch (\Throwable $e) {
                    Log::warning('[PurchaseQuotation] S3 upload failed for offer item attachment: ' . $e->getMessage());
                }
            }

            try {
                Storage::disk('public')->putFileAs($dir, $attachment, $filename);
            } catch (\Throwable $e) {
                Log::warning('[PurchaseQuotation] Local public upload failed for offer item attachment: ' . $e->getMessage());
            }

            return [
                'path' => $relativePath,
                'name' => $origName,
                'mime' => $mime,
            ];
        }

        // 2. Base64 / Data URI string or existing path/URL
        if (is_string($attachment)) {
            $trimmed = trim($attachment);
            if ($trimmed === '') {
                return ['path' => null, 'name' => null, 'mime' => null];
            }

            // Already a URL or relative path
            if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, 'quotation-offers/')) {
                return [
                    'path' => $trimmed,
                    'name' => basename(parse_url($trimmed, PHP_URL_PATH) ?: $trimmed),
                    'mime' => null,
                ];
            }

            $mime = 'application/octet-stream';
            $ext = 'bin';
            $base64Data = $trimmed;

            if (preg_match('/^data:([a-zA-Z0-9\/\+\-\.]+);base64,(.+)$/s', $trimmed, $matches)) {
                $mime = strtolower($matches[1]);
                $base64Data = $matches[2];
                $extMap = [
                    'image/jpeg'      => 'jpg',
                    'image/jpg'       => 'jpg',
                    'image/png'       => 'png',
                    'image/webp'      => 'webp',
                    'application/pdf' => 'pdf',
                ];
                $ext = $extMap[$mime] ?? (explode('/', $mime)[1] ?? 'bin');
            }

            $binary = base64_decode($base64Data, true);
            if ($binary === false || $binary === '') {
                return ['path' => null, 'name' => null, 'mime' => null];
            }

            $filename = sprintf('item_%d_%s_%s.%s', $index + 1, now()->format('YmdHis'), Str::random(8), $ext);
            $relativePath = "{$dir}/{$filename}";

            if ($s3Configured) {
                try {
                    Storage::disk('s3')->put($relativePath, $binary, 'public');
                } catch (\Throwable $e) {
                    Log::warning('[PurchaseQuotation] S3 base64 upload failed: ' . $e->getMessage());
                }
            }

            try {
                Storage::disk('public')->put($relativePath, $binary);
            } catch (\Throwable $e) {
                Log::warning('[PurchaseQuotation] Local base64 upload failed: ' . $e->getMessage());
            }

            return [
                'path' => $relativePath,
                'name' => $filename,
                'mime' => $mime,
            ];
        }

        return ['path' => null, 'name' => null, 'mime' => null];
    }

    public function deleteOffer(int $id): bool
    {
        $offer = PurchaseQuotationOffer::find($id);
        if (!$offer) {
            return false;
        }

        return (bool) $offer->delete();
    }
}
