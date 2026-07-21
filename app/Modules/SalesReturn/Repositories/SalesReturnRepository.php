<?php

namespace App\Modules\SalesReturn\Repositories;

use App\Models\SalesReturn;
use App\Models\SalesReturnDetail;
use App\Models\SalesReturnAttachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SalesReturnRepository implements SalesReturnRepositoryInterface
{
    /**
     * Get all sales returns.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        $query = SalesReturn::query()->with(['details', 'attachments', 'salesOrder', 'distributor']);

        if (!empty($filters['distributor_id'])) {
            if (is_array($filters['distributor_id'])) {
                $query->whereIn('distributor_id', $filters['distributor_id']);
            } else {
                $query->where('distributor_id', $filters['distributor_id']);
            }
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['card_code'])) {
            $cardCodes = array_map('trim', explode(',', $filters['card_code']));
            if (count($cardCodes) > 1) {
                $query->whereIn('card_code', $cardCodes);
            } else {
                $query->where('card_code', $cardCodes[0]);
            }
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Find a sales return by ID.
     *
     * @param  int  $id
     * @return SalesReturn|null
     */
    public function getById(int $id): ?SalesReturn
    {
        return SalesReturn::with([
            'details.salesOrderDetail',
            'attachments',
            'salesOrder',
            'distributor',
            'createdByUser',
            'approvedByUser',
            'rejectedByUser'
        ])->find($id);
    }

    /**
     * Create a new sales return with detail lines and attachments.
     *
     * @param  array  $data
     * @return SalesReturn
     */
    public function create(array $data): SalesReturn
    {
        return DB::transaction(function () use ($data) {
            $details = $data['details'] ?? [];
            $attachments = $data['attachments'] ?? [];
            
            unset($data['details']);
            unset($data['attachments']);

            $salesReturn = SalesReturn::create($data);

            foreach ($details as $detail) {
                $salesReturn->details()->create($detail);
            }

            foreach ($attachments as $attachment) {
                $salesReturn->attachments()->create($attachment);
            }

            return $salesReturn->fresh(['details', 'attachments']);
        });
    }

    /**
     * Update an existing sales return.
     *
     * @param  SalesReturn  $salesReturn
     * @param  array  $data
     * @return SalesReturn
     */
    public function update(SalesReturn $salesReturn, array $data): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn, $data) {
            $salesReturn->update($data);
            return $salesReturn->fresh();
        });
    }

    /**
     * Delete a sales return.
     *
     * @param  SalesReturn  $salesReturn
     * @return bool
     */
    public function delete(SalesReturn $salesReturn): bool
    {
        return DB::transaction(function () use ($salesReturn) {
            return $salesReturn->delete();
        });
    }
}
