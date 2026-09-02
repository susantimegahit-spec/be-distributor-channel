<?php

namespace App\Modules\Production\Repositories;

use App\Models\ProductionResource;
use App\Models\ProductionItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductionRepository implements ProductionRepositoryInterface
{
    /**
     * Get all production resources.
     *
     * @param  array  $filters
     * @return Collection<int, ProductionResource>
     */
    public function getAllResources(array $filters = []): Collection
    {
        $query = ProductionResource::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('res_code', $likeOperator, "%{$search}%")
                  ->orWhere('res_name', $likeOperator, "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Create or update a production resource by code.
     *
     * @param  array  $data
     * @return ProductionResource
     */
    public function upsertResource(array $data): ProductionResource
    {
        return ProductionResource::updateOrCreate(
            ['res_code' => $data['res_code']],
            $data
        );
    }

    /**
     * Get all production items.
     *
     * @param  array  $filters
     * @return Collection<int, ProductionItem>
     */
    public function getAllItems(array $filters = []): Collection
    {
        $query = ProductionItem::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('item_code', $likeOperator, "%{$search}%")
                  ->orWhere('item_name', $likeOperator, "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Create or update a production item by code.
     *
     * @param  array  $data
     * @return ProductionItem
     */
    public function upsertItem(array $data): ProductionItem
    {
        return ProductionItem::updateOrCreate(
            ['item_code' => $data['item_code']],
            $data
        );
    }

    /**
     * Get all production BOMs.
     */
    public function getAllBoms(array $filters = []): Collection
    {
        $query = \App\Models\ProductionBom::query()->with([
            'parentItem',
            'details.item',
            'details.resource',
            'details.ocr',
            'details.ocr2',
            'details.ocr3',
        ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('code', $likeOperator, "%{$search}%")
                  ->orWhere('comments', $likeOperator, "%{$search}%");
            });
        }

        if (!empty($filters['code'])) {
            $query->where('code', $filters['code']);
        }

        return $query->get();
    }

    /**
     * Get production BOM by ID.
     */
    public function getBomById(int $id): ?\App\Models\ProductionBom
    {
        return \App\Models\ProductionBom::with([
            'parentItem',
            'details.item',
            'details.resource',
            'details.ocr',
            'details.ocr2',
            'details.ocr3',
        ])->find($id);
    }

    /**
     * Create a new production BOM.
     */
    public function createBom(array $data): \App\Models\ProductionBom
    {
        return DB::connection('pgsql_production')->transaction(function () use ($data) {
            $details = $data['details'] ?? [];
            unset($data['details']);

            // Auto-calculate next alternate version if not provided or if it already exists
            if (empty($data['alternate']) || \App\Models\ProductionBom::where('code', $data['code'])->where('alternate', $data['alternate'])->exists()) {
                $maxAlternate = \App\Models\ProductionBom::where('code', $data['code'])->max('alternate');
                $data['alternate'] = $maxAlternate ? $maxAlternate + 1 : 1;
            }

            $bom = \App\Models\ProductionBom::create($data);

            foreach ($details as $index => $detail) {
                $detail['father'] = $bom->code;
                $detail['child_num'] = $index;
                $bom->details()->create($detail);
            }

            return $bom->fresh(['details']);
        });
    }

    /**
     * Update an existing production BOM.
     */
    public function updateBom(\App\Models\ProductionBom $bom, array $data): \App\Models\ProductionBom
    {
        return DB::connection('pgsql_production')->transaction(function () use ($bom, $data) {
            $details = $data['details'] ?? null;
            unset($data['details']);

            $bom->update($data);

            if ($details !== null) {
                $bom->details()->delete();
                foreach ($details as $index => $detail) {
                    $detail['father'] = $bom->code;
                    $detail['child_num'] = $index;
                    $bom->details()->create($detail);
                }
            }

            return $bom->fresh(['details']);
        });
    }

    /**
     * Delete a production BOM.
     */
    public function deleteBom(\App\Models\ProductionBom $bom): bool
    {
        return DB::connection('pgsql_production')->transaction(function () use ($bom) {
            return $bom->delete();
        });
    }

    /**
     * Get all production orders.
     */
    public function getAllOrders(array $filters = []): Collection
    {
        $query = \App\Models\ProductionOrder::query()->with([
            'parentItem',
            'details.item',
            'details.resource',
            'details.warehouseModel',
            'details.ocr',
            'details.ocr2',
            'details.ocr3',
            'ocr',
            'ocr2',
            'ocr3',
            'warehouseModel'
        ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('prod_order_no', $likeOperator, "%{$search}%")
                  ->orWhere('item_code', $likeOperator, "%{$search}%")
                  ->orWhere('comments', $likeOperator, "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['item_code'])) {
            $query->where('item_code', $filters['item_code']);
        }

        return $query->get();
    }

    /**
     * Get production order by ID.
     */
    public function getOrderById(int $id): ?\App\Models\ProductionOrder
    {
        $order = \App\Models\ProductionOrder::with([
            'parentItem',
            'details.item',
            'details.resource',
            'details.warehouseModel',
            'details.ocr',
            'details.ocr2',
            'details.ocr3',
            'ocr',
            'ocr2',
            'ocr3',
            'warehouseModel'
        ])->find($id);

        if (!$order) {
            $order = \App\Models\ProductionOrder::with([
                'parentItem',
                'details.item',
                'details.resource',
                'details.warehouseModel',
                'details.ocr',
                'details.ocr2',
                'details.ocr3',
                'ocr',
                'ocr2',
                'ocr3',
                'warehouseModel'
            ])->where('doc_entry', $id)->first();
        }

        return $order;
    }

    /**
     * Create a new production order.
     */
    public function createOrder(array $data): \App\Models\ProductionOrder
    {
        return DB::connection('pgsql_production')->transaction(function () use ($data) {
            $details = $data['details'] ?? [];
            unset($data['details']);

            if (empty($data['prod_order_no'])) {
                $data['prod_order_no'] = 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            }

            if (isset($data['production_bom_id']) && !empty($data['production_bom_id'])) {
                $bomExists = \App\Models\ProductionBom::where('id', $data['production_bom_id'])->exists();
                if (!$bomExists) {
                    $data['production_bom_id'] = null;
                }
            }

            $order = \App\Models\ProductionOrder::create($data);

            foreach ($details as $index => $detail) {
                $detail['line_num'] = $index;
                $order->details()->create($detail);
            }

            return $order->fresh([
                'parentItem',
                'details.item',
                'details.resource',
                'details.warehouseModel',
                'details.ocr',
                'details.ocr2',
                'details.ocr3',
                'ocr',
                'ocr2',
                'ocr3',
                'warehouseModel'
            ]);
        });
    }

    /**
     * Update an existing production order.
     */
    public function updateOrder(\App\Models\ProductionOrder $order, array $data): \App\Models\ProductionOrder
    {
        return DB::connection('pgsql_production')->transaction(function () use ($order, $data) {
            $details = $data['details'] ?? null;
            unset($data['details']);

            if (isset($data['production_bom_id']) && !empty($data['production_bom_id'])) {
                $bomExists = \App\Models\ProductionBom::where('id', $data['production_bom_id'])->exists();
                if (!$bomExists) {
                    $data['production_bom_id'] = null;
                }
            }

            $order->update($data);

            if ($details !== null) {
                $order->details()->delete();
                foreach ($details as $index => $detail) {
                    $detail['line_num'] = $index;
                    $order->details()->create($detail);
                }
            }

            return $order->fresh([
                'parentItem',
                'details.item',
                'details.resource',
                'details.warehouseModel',
                'details.ocr',
                'details.ocr2',
                'details.ocr3',
                'ocr',
                'ocr2',
                'ocr3',
                'warehouseModel'
            ]);
        });
    }

    /**
     * Delete a production order.
     */
     public function deleteOrder(\App\Models\ProductionOrder $order): bool
     {
         return DB::connection('pgsql_production')->transaction(function () use ($order) {
             return $order->delete();
         });
     }

    /**
     * Get all production change products.
     */
    public function getAllChangeProducts(array $filters = []): Collection
    {
        $query = \App\Models\ProductionChangeProduct::query()->with([
            'items',
        ]);

        $likeOperator = DB::connection('pgsql_production')->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('cp_no', $likeOperator, "%{$search}%")
                  ->orWhere('comments', $likeOperator, "%{$search}%")
                  ->orWhere('addon_id', $likeOperator, "%{$search}%")
                  ->orWhereHas('items', function ($itemQ) use ($search, $likeOperator) {
                      $itemQ->where('old_item_code', $likeOperator, "%{$search}%")
                            ->orWhere('new_item_code', $likeOperator, "%{$search}%");
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['sap_status'])) {
            $query->where('sap_status', $filters['sap_status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('doc_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('doc_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('doc_date', 'desc')->orderBy('id', 'desc')->get();
    }

    /**
     * Get production change product by ID.
     */
    public function getChangeProductById(int $id): ?\App\Models\ProductionChangeProduct
    {
        return \App\Models\ProductionChangeProduct::with([
            'items',
        ])->find($id);
    }

    /**
     * Create a new production change product with its items.
     */
    public function createChangeProduct(array $data): \App\Models\ProductionChangeProduct
    {
        return DB::connection('pgsql_production')->transaction(function () use ($data) {
            $items = $data['items'] ?? $data['lines'] ?? $data['Lines'] ?? [];
            unset($data['items'], $data['lines'], $data['Lines']);

            if (empty($data['cp_no'])) {
                $prefix = 'CP-' . date('Ymd') . '-';
                $last = \App\Models\ProductionChangeProduct::where('cp_no', 'like', "{$prefix}%")
                    ->orderBy('id', 'desc')
                    ->first();
                $seq = 1;
                if ($last && preg_match('/-(\d+)$/', $last->cp_no, $matches)) {
                    $seq = (int)$matches[1] + 1;
                }
                $data['cp_no'] = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
            }

            $cp = \App\Models\ProductionChangeProduct::create($data);

            foreach ($items as $index => $item) {
                $cp->items()->create([
                    'line_num'      => $index,
                    'old_item_code' => $item['old_item_code'] ?? $item['oldItemCode'] ?? $item['OldItemCode'] ?? '',
                    'new_item_code' => $item['new_item_code'] ?? $item['newItemCode'] ?? $item['NewItemCode'] ?? '',
                    'quantity'      => floatval($item['quantity'] ?? $item['Quantity'] ?? $item['qty'] ?? 0),
                    'from_whs_code' => $item['from_whs_code'] ?? $item['fromWhsCode'] ?? $item['FromWhsCode'] ?? '',
                    'to_whs_code'   => $item['to_whs_code'] ?? $item['toWhsCode'] ?? $item['ToWhsCode'] ?? '',
                    'ocr_code'      => $item['ocr_code'] ?? $item['ocrCode'] ?? $item['OcrCode'] ?? null,
                    'ocr_code2'     => $item['ocr_code2'] ?? $item['ocrCode2'] ?? $item['OcrCode2'] ?? null,
                    'ocr_code3'     => $item['ocr_code3'] ?? $item['ocrCode3'] ?? $item['OcrCode3'] ?? null,
                ]);
            }

            return $cp->fresh(['items']);
        });
    }

    /**
     * Update an existing production change product.
     */
    public function updateChangeProduct(\App\Models\ProductionChangeProduct $cp, array $data): \App\Models\ProductionChangeProduct
    {
        return DB::connection('pgsql_production')->transaction(function () use ($cp, $data) {
            $items = $data['items'] ?? $data['lines'] ?? $data['Lines'] ?? null;
            unset($data['items'], $data['lines'], $data['Lines']);

            $cp->update($data);

            if ($items !== null) {
                $cp->items()->delete();
                foreach ($items as $index => $item) {
                    $cp->items()->create([
                        'line_num'      => $index,
                        'old_item_code' => $item['old_item_code'] ?? $item['oldItemCode'] ?? $item['OldItemCode'] ?? '',
                        'new_item_code' => $item['new_item_code'] ?? $item['newItemCode'] ?? $item['NewItemCode'] ?? '',
                        'quantity'      => floatval($item['quantity'] ?? $item['Quantity'] ?? $item['qty'] ?? 0),
                        'from_whs_code' => $item['from_whs_code'] ?? $item['fromWhsCode'] ?? $item['FromWhsCode'] ?? '',
                        'to_whs_code'   => $item['to_whs_code'] ?? $item['toWhsCode'] ?? $item['ToWhsCode'] ?? '',
                        'ocr_code'      => $item['ocr_code'] ?? $item['ocrCode'] ?? $item['OcrCode'] ?? null,
                        'ocr_code2'     => $item['ocr_code2'] ?? $item['ocrCode2'] ?? $item['OcrCode2'] ?? null,
                        'ocr_code3'     => $item['ocr_code3'] ?? $item['ocrCode3'] ?? $item['OcrCode3'] ?? null,
                    ]);
                }
            }

            return $cp->fresh(['items']);
        });
    }

    /**
     * Delete a production change product.
     */
    public function deleteChangeProduct(\App\Models\ProductionChangeProduct $cp): bool
    {
        return DB::connection('pgsql_production')->transaction(function () use ($cp) {
            return $cp->delete();
        });
    }
}

