<?php

namespace App\Modules\PurchasingRequest\Repositories;

use App\Models\MasterBudget;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseRequestRepository implements PurchaseRequestRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = PurchaseRequest::with('details.masterBudget');

        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (!empty($filters['cost_center'])) {
            $query->where('cost_center', $filters['cost_center']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('doc_date', [$filters['start_date'], $filters['end_date']]);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('pr_number', 'like', $search)
                  ->orWhere('department', 'like', $search)
                  ->orWhere('cost_center', 'like', $search)
                  ->orWhere('requester_name', 'like', $search)
                  ->orWhere('remarks', 'like', $search);
            });
        }

        $query->orderBy('doc_date', 'desc')
              ->orderBy('id', 'desc');

        if (isset($filters['paginate']) && $filters['paginate'] === 'false') {
            return $query->get();
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?PurchaseRequest
    {
        return PurchaseRequest::with('details.masterBudget')->find($id);
    }

    public function create(array $data, array $details): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $details) {
            $totalAmount = 0;
            foreach ($details as $item) {
                $qty = (float)($item['quantity'] ?? 1);
                $price = (float)($item['unit_price'] ?? 0);
                $totalAmount += ($qty * $price);
            }
            $data['total_amount'] = $totalAmount;

            $pr = PurchaseRequest::create($data);

            foreach ($details as $item) {
                $qty = (float)($item['quantity'] ?? 1);
                $price = (float)($item['unit_price'] ?? 0);
                $lineTotal = $qty * $price;

                $pr->details()->create([
                    'master_budget_id' => $item['master_budget_id'] ?? null,
                    'bom_id' => $item['bom_id'] ?? null,
                    'item_code' => $item['item_code'] ?? null,
                    'item_description' => $item['item_description'] ?? $item['item_code'] ?? 'Item',
                    'pqt_req_date' => $item['pqt_req_date'] ?? null,
                    'quantity' => $qty,
                    'uom' => $item['uom'] ?? $item['unit_msr'] ?? null,
                    'uom_entry' => $item['uom_entry'] ?? null,
                    'uom_code' => $item['uom_code'] ?? null,
                    'whs_code' => $item['whs_code'] ?? null,
                    'unit_msr' => $item['unit_msr'] ?? null,
                    'unit_price' => $price,
                    'line_total' => $lineTotal,
                    'free_txt' => $item['free_txt'] ?? null,
                    'ocr_code' => $item['ocr_code'] ?? null,
                    'ocr_code2' => $item['ocr_code2'] ?? null,
                    'ocr_code3' => $item['ocr_code3'] ?? null,
                    'remarks' => $item['remarks'] ?? null,
                ]);

                // Update budget used amount if master_budget_id specified & approved/submitted
                if (!empty($item['master_budget_id']) && in_array($data['status'] ?? 'DRAFT', ['SUBMITTED', 'APPROVED'])) {
                    MasterBudget::where('id', $item['master_budget_id'])->increment('used_amount', $lineTotal);
                }
            }

            return $pr->load('details.masterBudget');
        });
    }

    public function update(int $id, array $data, ?array $details = null): ?PurchaseRequest
    {
        return DB::transaction(function () use ($id, $data, $details) {
            $pr = PurchaseRequest::find($id);
            if (!$pr) {
                return null;
            }

            if ($details !== null) {
                // Re-calculate budget impact if details change
                $totalAmount = 0;
                
                // Rollback previous budget used_amount if it was submitted/approved
                if (in_array($pr->status, ['SUBMITTED', 'APPROVED'])) {
                    foreach ($pr->details as $oldDetail) {
                        if ($oldDetail->master_budget_id) {
                            MasterBudget::where('id', $oldDetail->master_budget_id)->decrement('used_amount', $oldDetail->line_total);
                        }
                    }
                }

                $pr->details()->delete();

                foreach ($details as $item) {
                    $qty = (float)($item['quantity'] ?? 1);
                    $price = (float)($item['unit_price'] ?? 0);
                    $lineTotal = $qty * $price;
                    $totalAmount += $lineTotal;

                    $pr->details()->create([
                        'master_budget_id' => $item['master_budget_id'] ?? null,
                        'item_code' => $item['item_code'] ?? null,
                        'item_description' => $item['item_description'],
                        'quantity' => $qty,
                        'uom' => $item['uom'] ?? null,
                        'unit_price' => $price,
                        'line_total' => $lineTotal,
                        'remarks' => $item['remarks'] ?? null,
                    ]);

                    $targetStatus = $data['status'] ?? $pr->status;
                    if (!empty($item['master_budget_id']) && in_array($targetStatus, ['SUBMITTED', 'APPROVED'])) {
                        MasterBudget::where('id', $item['master_budget_id'])->increment('used_amount', $lineTotal);
                    }
                }

                $data['total_amount'] = $totalAmount;
            }

            $pr->update($data);
            return $pr->fresh('details.masterBudget');
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $pr = PurchaseRequest::with('details')->find($id);
            if ($pr) {
                if (in_array($pr->status, ['SUBMITTED', 'APPROVED'])) {
                    foreach ($pr->details as $detail) {
                        if ($detail->master_budget_id) {
                            MasterBudget::where('id', $detail->master_budget_id)->decrement('used_amount', $detail->line_total);
                        }
                    }
                }
                return $pr->delete();
            }
            return false;
        });
    }

    public function updateStatus(int $id, string $status, ?int $userId = null): ?PurchaseRequest
    {
        return DB::transaction(function () use ($id, $status, $userId) {
            $pr = PurchaseRequest::with('details')->find($id);
            if (!$pr) {
                return null;
            }

            $oldStatus = $pr->status;
            if ($oldStatus === $status) {
                return $pr;
            }

            // Status transitions affecting master budget used_amount
            $wasCounted = in_array($oldStatus, ['SUBMITTED', 'APPROVED']);
            $willBeCounted = in_array($status, ['SUBMITTED', 'APPROVED']);

            if (!$wasCounted && $willBeCounted) {
                foreach ($pr->details as $detail) {
                    if ($detail->master_budget_id) {
                        MasterBudget::where('id', $detail->master_budget_id)->increment('used_amount', $detail->line_total);
                    }
                }
            } elseif ($wasCounted && !$willBeCounted) {
                foreach ($pr->details as $detail) {
                    if ($detail->master_budget_id) {
                        MasterBudget::where('id', $detail->master_budget_id)->decrement('used_amount', $detail->line_total);
                    }
                }
            }

            $pr->update([
                'status' => $status,
                'updated_by' => $userId ?? $pr->updated_by,
            ]);

            return $pr->fresh('details.masterBudget');
        });
    }
}
