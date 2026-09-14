<?php

namespace App\Modules\Ekspedisi\Services;

use App\Models\MasterLeadtime;
use App\Models\SalesOrder;
use App\Models\SalesOrderLogisticLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class LogisticOrderService
{
    /**
     * Resolve benchmark lead time in days from origin to destination via MasterLeadtime.
     */
    public function resolveLeadTimeDays(SalesOrder $order): int
    {
        try {
            $destCode = $order->ship_to_code ?? $order->card_code;
            $destCity = $order->distributor ? $order->distributor->city : null;

            $originCode = null;
            $firstDetail = $order->details->first();
            if ($firstDetail && !empty($firstDetail->whs_code)) {
                $originCode = $firstDetail->whs_code;
            }

            $query = MasterLeadtime::where('status', 'ACTIVE');
            if ($originCode) {
                $query->where('origin_warehouse_code', $originCode);
            }

            if ($destCode) {
                $leadtime = (clone $query)->where('destination_code', $destCode)->first();
                if ($leadtime && (float) $leadtime->avg_lead_time_days > 0) {
                    return (int) round((float) $leadtime->avg_lead_time_days);
                }
            }

            if ($destCity) {
                $likeOp = config('database.default') === 'sqlite' ? 'LIKE' : 'ILIKE';
                $leadtime = (clone $query)->where('destination_city', $likeOp, "%{$destCity}%")->first();
                if ($leadtime && (float) $leadtime->avg_lead_time_days > 0) {
                    return (int) round((float) $leadtime->avg_lead_time_days);
                }
            }
        } catch (\Throwable $e) {
            // Graceful fallback
        }

        return 3;
    }

    /**
     * Get paginated sales orders that are in WAITING_FINANCE or ORDER_APPROVED stage.
     */
    public function listOrders(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SalesOrder::query()
            ->with(['details.item', 'distributor', 'latestLogisticLog']);

        // Default: filter status WAITING_FINANCE and ORDER_APPROVED
        if (!empty($filters['status'])) {
            $statusInput = strtoupper(trim((string) $filters['status']));
            $query->where('status', $statusInput);
        } else {
            $query->whereIn('status', ['WAITING_FINANCE', 'ORDER_APPROVED']);
        }

        if (!empty($filters['logistic_status'])) {
            $logisticStatus = strtoupper(trim((string) $filters['logistic_status']));
            $query->where('logistic_status', $logisticStatus);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $likeOp = config('database.default') === 'sqlite' ? 'LIKE' : 'ILIKE';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('order_no', $likeOp, $search)
                  ->orWhere('customer_name', $likeOp, $search)
                  ->orWhere('card_code', $likeOp, $search)
                  ->orWhere('po_number', $likeOp, $search);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->where('doc_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('doc_date', '<=', $filters['date_to']);
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $paginator->getCollection()->transform(function (SalesOrder $order) {
            $benchmarkLeadTime = $this->resolveLeadTimeDays($order);
            $logisticStatus = strtoupper((string) ($order->logistic_status ?: 'PENDING'));

            $orderArray = $order->toArray();
            $orderArray['req_due_date'] = $order->req_due_date ? $order->req_due_date->format('Y-m-d') : ($order->doc_due_date ? $order->doc_due_date->format('Y-m-d') : null);
            $orderArray['doc_due_date'] = $order->doc_due_date ? $order->doc_due_date->format('Y-m-d') : null;
            $orderArray['eta_date'] = $order->eta_date ? $order->eta_date->format('Y-m-d') : null;
            $orderArray['proposed_delivery_date'] = $order->proposed_delivery_date ? $order->proposed_delivery_date->format('Y-m-d') : null;
            $orderArray['proposed_eta_date'] = $order->proposed_eta_date ? $order->proposed_eta_date->format('Y-m-d') : null;
            $orderArray['logistic_status'] = $logisticStatus;
            $orderArray['logistic_notes'] = $order->logistic_notes;

            $orderArray['leadtime_info'] = [
                'benchmark_lead_time_days' => $benchmarkLeadTime,
                'source'                   => 'ekspedisi.master_leadtimes',
            ];

            // Action flags for frontend button states
            $orderArray['can_logistic_approve'] = ($order->status === 'ORDER_APPROVED' && in_array($logisticStatus, ['PENDING', 'RESCHEDULE_REJECTED']));
            $orderArray['can_logistic_reschedule'] = ($order->status === 'ORDER_APPROVED');
            $orderArray['can_sales_approve_reschedule'] = ($logisticStatus === 'RESCHEDULE_REQUESTED');

            return $orderArray;
        });

        return $paginator;
    }

    /**
     * Reschedule order delivery date and proposed arrival date by Logistics team.
     */
    public function rescheduleOrder(int $orderId, int $userId, array $data): SalesOrder
    {
        $order = SalesOrder::find($orderId);
        if (!$order) {
            throw ValidationException::withMessages([
                'order' => ['Sales order not found.'],
            ]);
        }

        if ($order->status !== 'ORDER_APPROVED') {
            throw ValidationException::withMessages([
                'status' => ["Cannot reschedule delivery for sales order with status '{$order->status}'. Order must be in 'ORDER_APPROVED' status."],
            ]);
        }

        $rescheduledDate = $data['rescheduled_date'] ?? $data['proposed_delivery_date'] ?? null;
        if (!$rescheduledDate) {
            throw ValidationException::withMessages([
                'rescheduled_date' => ['Proposed delivery date is required for rescheduling.'],
            ]);
        }

        $rescheduledDate = Carbon::parse($rescheduledDate)->toDateString();

        // If eta_date is not explicitly provided, calculate via MasterLeadtime benchmark
        $etaDate = $data['eta_date'] ?? $data['proposed_eta_date'] ?? null;
        if ($etaDate) {
            $etaDate = Carbon::parse($etaDate)->toDateString();
        } else {
            $benchmarkLeadTime = $this->resolveLeadTimeDays($order);
            $etaDate = Carbon::parse($rescheduledDate)->addDays($benchmarkLeadTime)->toDateString();
        }

        $notes = trim((string) ($data['notes'] ?? ''));
        $previousStatus = $order->logistic_status ?: 'PENDING';
        $prevDueDate = $order->doc_due_date ? $order->doc_due_date->format('Y-m-d') : null;
        $prevEtaDate = $order->eta_date ? $order->eta_date->format('Y-m-d') : null;

        $user = User::find($userId);
        $userName = $user ? $user->name : 'User #' . $userId;
        $roleName = $user && $user->role ? $user->role->name : 'Logistic';

        $order->update([
            'logistic_status'        => 'RESCHEDULE_REQUESTED',
            'proposed_delivery_date' => $rescheduledDate,
            'proposed_eta_date'      => $etaDate,
            'logistic_notes'         => $notes ?: null,
            'logistic_action_at'     => now(),
            'logistic_action_by'     => $userId,
        ]);

        SalesOrderLogisticLog::create([
            'sales_order_id'    => $order->id,
            'action'            => 'LOGISTIC_RESCHEDULE',
            'from_status'       => $previousStatus,
            'to_status'         => 'RESCHEDULE_REQUESTED',
            'previous_due_date' => $prevDueDate,
            'previous_eta_date' => $prevEtaDate,
            'proposed_due_date' => $rescheduledDate,
            'proposed_eta_date' => $etaDate,
            'notes'             => $notes ?: null,
            'user_id'           => $userId,
            'user_name'         => $userName,
            'role_name'         => $roleName,
        ]);

        return $order->fresh(['details.item', 'distributor', 'latestLogisticLog']);
    }

    /**
     * Approve delivery schedule:
     * - If logistic_status is RESCHEDULE_REQUESTED, Admin Sales approves the rescheduled date.
     * - If logistic_status is PENDING, Logistic confirms readiness of the current schedule.
     */
    public function approveOrder(int $orderId, int $userId, array $data = []): SalesOrder
    {
        $order = SalesOrder::find($orderId);
        if (!$order) {
            throw ValidationException::withMessages([
                'order' => ['Sales order not found.'],
            ]);
        }

        if ($order->status !== 'ORDER_APPROVED') {
            throw ValidationException::withMessages([
                'status' => ["Cannot approve delivery for sales order with status '{$order->status}'. Order must be in 'ORDER_APPROVED' status."],
            ]);
        }

        $user = User::find($userId);
        $userName = $user ? $user->name : 'User #' . $userId;
        $roleName = $user && $user->role ? $user->role->name : null;

        $notes = trim((string) ($data['notes'] ?? ''));
        $prevDueDate = $order->doc_due_date ? $order->doc_due_date->format('Y-m-d') : null;
        $prevEtaDate = $order->eta_date ? $order->eta_date->format('Y-m-d') : null;
        $currentLogisticStatus = strtoupper((string) ($order->logistic_status ?: 'PENDING'));

        if ($currentLogisticStatus === 'RESCHEDULE_REQUESTED') {
            // Admin Sales Approval of Logistic Reschedule
            $newDueDate = $order->proposed_delivery_date
                ? $order->proposed_delivery_date->format('Y-m-d')
                : ($data['due_date'] ?? $prevDueDate);

            $newEtaDate = $order->proposed_eta_date
                ? $order->proposed_eta_date->format('Y-m-d')
                : ($data['eta_date'] ?? $prevEtaDate);

            $order->update([
                'req_due_date'       => $newDueDate,
                'doc_due_date'       => $newDueDate,
                'eta_date'           => $newEtaDate,
                'logistic_status'    => 'RESCHEDULE_APPROVED',
                'logistic_action_at' => now(),
                'logistic_action_by' => $userId,
            ]);

            SalesOrderLogisticLog::create([
                'sales_order_id'    => $order->id,
                'action'            => 'ADMIN_SALES_APPROVED_RESCHEDULE',
                'from_status'       => 'RESCHEDULE_REQUESTED',
                'to_status'         => 'RESCHEDULE_APPROVED',
                'previous_due_date' => $prevDueDate,
                'previous_eta_date' => $prevEtaDate,
                'approved_due_date' => $newDueDate,
                'approved_eta_date' => $newEtaDate,
                'notes'             => $notes ?: 'Admin sales approved logistic delivery reschedule request.',
                'user_id'           => $userId,
                'user_name'         => $userName,
                'role_name'         => $roleName ?: 'Admin Sales',
            ]);
        } else {
            // Logistic Readiness Approval
            $etaDate = $data['eta_date'] ?? null;
            if ($etaDate) {
                $etaDate = Carbon::parse($etaDate)->toDateString();
            } else {
                $etaDate = $prevEtaDate;
            }

            $currentDueDate = $order->doc_due_date ? $order->doc_due_date->format('Y-m-d') : null;
            $reqDueDate = $order->req_due_date ? $order->req_due_date->format('Y-m-d') : $currentDueDate;

            $order->update([
                'req_due_date'       => $reqDueDate,
                'eta_date'           => $etaDate,
                'logistic_status'    => 'APPROVED',
                'logistic_action_at' => now(),
                'logistic_action_by' => $userId,
            ]);

            SalesOrderLogisticLog::create([
                'sales_order_id'    => $order->id,
                'action'            => 'LOGISTIC_APPROVED',
                'from_status'       => $currentLogisticStatus,
                'to_status'         => 'APPROVED',
                'previous_due_date' => $prevDueDate,
                'previous_eta_date' => $prevEtaDate,
                'approved_due_date' => $currentDueDate,
                'approved_eta_date' => $etaDate,
                'notes'             => $notes ?: 'Logistics team confirmed delivery schedule and shipment readiness.',
                'user_id'           => $userId,
                'user_name'         => $userName,
                'role_name'         => $roleName ?: 'Logistic',
            ]);
        }

        return $order->fresh(['details.item', 'distributor', 'latestLogisticLog']);
    }

    /**
     * Get monitoring activity logs and chronological details for a sales order.
     */
    public function getOrderLogs(int $orderId): array
    {
        $order = SalesOrder::with(['distributor', 'details.item'])->find($orderId);
        if (!$order) {
            throw ValidationException::withMessages([
                'order' => ['Sales order not found.'],
            ]);
        }

        $logs = SalesOrderLogisticLog::where('sales_order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();

        $benchmarkLeadTime = $this->resolveLeadTimeDays($order);

        return [
            'order' => [
                'id'                     => $order->id,
                'order_no'               => $order->order_no,
                'card_code'              => $order->card_code,
                'customer_name'          => $order->customer_name,
                'status'                 => $order->status,
                'approval_id'            => $order->approval_id,
                'doc_date'               => $order->doc_date ? $order->doc_date->format('Y-m-d') : null,
                'doc_due_date'           => $order->doc_due_date ? $order->doc_due_date->format('Y-m-d') : null,
                'req_due_date'           => $order->req_due_date ? $order->req_due_date->format('Y-m-d') : ($order->doc_due_date ? $order->doc_due_date->format('Y-m-d') : null),
                'eta_date'               => $order->eta_date ? $order->eta_date->format('Y-m-d') : null,
                'logistic_status'        => strtoupper((string) ($order->logistic_status ?: 'PENDING')),
                'proposed_delivery_date' => $order->proposed_delivery_date ? $order->proposed_delivery_date->format('Y-m-d') : null,
                'proposed_eta_date'      => $order->proposed_eta_date ? $order->proposed_eta_date->format('Y-m-d') : null,
                'logistic_notes'         => $order->logistic_notes,
                'benchmark_lead_time'    => $benchmarkLeadTime,
            ],
            'logs' => $logs->map(function (SalesOrderLogisticLog $log) {
                return [
                    'id'                => $log->id,
                    'action'            => $log->action,
                    'from_status'       => $log->from_status,
                    'to_status'         => $log->to_status,
                    'previous_due_date' => $log->previous_due_date ? $log->previous_due_date->format('Y-m-d') : null,
                    'previous_eta_date' => $log->previous_eta_date ? $log->previous_eta_date->format('Y-m-d') : null,
                    'proposed_due_date' => $log->proposed_due_date ? $log->proposed_due_date->format('Y-m-d') : null,
                    'proposed_eta_date' => $log->proposed_eta_date ? $log->proposed_eta_date->format('Y-m-d') : null,
                    'approved_due_date' => $log->approved_due_date ? $log->approved_due_date->format('Y-m-d') : null,
                    'approved_eta_date' => $log->approved_eta_date ? $log->approved_eta_date->format('Y-m-d') : null,
                    'notes'             => $log->notes,
                    'user_id'           => $log->user_id,
                    'user_name'         => $log->user_name,
                    'role_name'         => $log->role_name,
                    'created_at'        => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : null,
                ];
            }),
        ];
    }
}
