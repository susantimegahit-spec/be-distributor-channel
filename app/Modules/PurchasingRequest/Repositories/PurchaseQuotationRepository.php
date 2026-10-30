<?php

namespace App\Modules\PurchasingRequest\Repositories;

use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationItem;
use App\Models\PurchaseQuotationOffer;
use App\Models\PurchaseQuotationOfferItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
            foreach (['status', 'title', 'notes', 'valid_until', 'user_id'] as $field) {
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

            foreach ($offerItems as $item) {
                $quotationItemId = $item['quotation_item_id'] ?? $item['quotationItemId'] ?? $item['id'] ?? null;
                $matchedItem = $quotationItemId ? $quotationItemsMap->get($quotationItemId) : null;

                $itemCode = $item['item_code'] ?? $item['itemCode'] ?? null;
                if (!$matchedItem && $itemCode) {
                    $matchedItem = $quotation->items->firstWhere('item_code', $itemCode);
                }

                $qty = (float) ($item['quantity'] ?? ($matchedItem?->quantity ?? 1));
                $unitPrice = (float) ($item['unit_price'] ?? $item['unitPrice'] ?? 0);
                $lineTotal = (float) ($item['total_price'] ?? $item['totalPrice'] ?? ($qty * $unitPrice));
                $totalPrice += $lineTotal;

                $preparedItems[] = [
                    'purchase_quotation_item_id' => $matchedItem?->id,
                    'item_code'                  => $item['item_code'] ?? $item['itemCode'] ?? ($matchedItem?->item_code ?? ''),
                    'item_name'                  => $item['item_name'] ?? $item['itemName'] ?? ($matchedItem?->item_name ?? ''),
                    'quantity'                   => $qty,
                    'unit'                       => $item['unit'] ?? $item['uom'] ?? ($matchedItem?->unit ?? null),
                    'unit_price'                 => $unitPrice,
                    'total_price'                => $lineTotal,
                    'lead_time'                  => $item['lead_time'] ?? $item['leadTime'] ?? null,
                    'notes'                      => $item['notes'] ?? null,
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
                $offer->items()->delete();
                $totalPrice = 0.0;

                foreach ($data['items'] as $item) {
                    $qty = (float) ($item['quantity'] ?? 1);
                    $unitPrice = (float) ($item['unit_price'] ?? $item['unitPrice'] ?? 0);
                    $lineTotal = (float) ($item['total_price'] ?? $item['totalPrice'] ?? ($qty * $unitPrice));
                    $totalPrice += $lineTotal;

                    $offer->items()->create([
                        'purchase_quotation_item_id' => $item['quotation_item_id'] ?? $item['quotationItemId'] ?? $item['id'] ?? null,
                        'item_code'                  => $item['item_code'] ?? $item['itemCode'] ?? '',
                        'item_name'                  => $item['item_name'] ?? $item['itemName'] ?? '',
                        'quantity'                   => $qty,
                        'unit'                       => $item['unit'] ?? $item['uom'] ?? null,
                        'unit_price'                 => $unitPrice,
                        'total_price'                => $lineTotal,
                        'lead_time'                  => $item['lead_time'] ?? $item['leadTime'] ?? null,
                        'notes'                      => $item['notes'] ?? null,
                    ]);
                }

                $offer->update(['total_price' => $totalPrice]);
            }

            return $offer->fresh(['items', 'quotation.items']);
        });
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
