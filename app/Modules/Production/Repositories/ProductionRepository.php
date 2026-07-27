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
}
