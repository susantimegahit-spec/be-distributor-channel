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
        return \App\Models\ProductionOrder::with([
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
}
