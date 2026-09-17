<?php

namespace App\Modules\Ekspedisi\Services;

use App\Models\MasterLeadtime;
use App\Models\SalesOrder;
use App\Models\SalesOrderLogisticLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        // Tab / Category filter for dashboard tabs
        $tab = !empty($filters['tab'])
            ? strtolower(trim((string) $filters['tab']))
            : (!empty($filters['category']) ? strtolower(trim((string) $filters['category'])) : null);

        if ($tab) {
            if ($tab === 'approved') {
                $query->whereIn('logistic_status', ['APPROVED', 'RESCHEDULE_APPROVED']);
            } elseif ($tab === 'reschedule') {
                $query->whereIn('logistic_status', ['RESCHEDULE_REQUESTED', 'RESCHEDULE_APPROVED']);
            } elseif ($tab === 'logistic_approved') {
                $query->where('logistic_status', 'APPROVED');
            } elseif ($tab === 'admin_approved' || $tab === 'reschedule_approved') {
                $query->where('logistic_status', 'RESCHEDULE_APPROVED');
            } elseif ($tab === 'reschedule_requested') {
                $query->where('logistic_status', 'RESCHEDULE_REQUESTED');
            } elseif ($tab === 'pending') {
                $query->where(function ($q) {
                    $q->where('logistic_status', 'PENDING')
                      ->orWhereNull('logistic_status');
                });
            }
        } elseif (!empty($filters['logistic_status'])) {
            $statusVal = $filters['logistic_status'];
            if (is_string($statusVal) && str_contains($statusVal, ',')) {
                $statuses = array_map(fn($s) => strtoupper(trim($s)), explode(',', $statusVal));
                $query->whereIn('logistic_status', $statuses);
            } elseif (is_array($statusVal)) {
                $statuses = array_map(fn($s) => strtoupper(trim($s)), $statusVal);
                $query->whereIn('logistic_status', $statuses);
            } else {
                $query->where('logistic_status', strtoupper(trim((string) $statusVal)));
            }
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

            // Approval type classification & label for FE
            $approvalType = match ($logisticStatus) {
                'APPROVED' => 'LOGISTIC_APPROVED',
                'RESCHEDULE_APPROVED' => 'ADMIN_SALES_APPROVED',
                'RESCHEDULE_REQUESTED' => 'RESCHEDULE_REQUESTED',
                default => 'PENDING',
            };

            $approvalLabel = match ($logisticStatus) {
                'APPROVED' => 'Logistic Approved',
                'RESCHEDULE_APPROVED' => 'Reschedule Approved (Admin Sales)',
                'RESCHEDULE_REQUESTED' => 'Reschedule Requested',
                default => 'Pending Logistic Review',
            };

            $orderArray['approval_type'] = $approvalType;
            $orderArray['approval_status_label'] = $approvalLabel;

            // Format latest logistic log if available
            $latestLog = $order->latestLogisticLog;
            $orderArray['latest_log'] = $latestLog ? [
                'id'         => $latestLog->id,
                'action'     => $latestLog->action,
                'from_status'=> $latestLog->from_status,
                'to_status'  => $latestLog->to_status,
                'notes'      => $latestLog->notes,
                'user_name'  => $latestLog->user_name,
                'role_name'  => $latestLog->role_name,
                'created_at' => $latestLog->created_at ? $latestLog->created_at->format('Y-m-d H:i:s') : null,
            ] : null;

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
     * Get summary KPI counters for logistic delivery dashboard.
     */
    public function getDashboardSummary(array $filters = []): array
    {
        $query = SalesOrder::query();

        if (!empty($filters['status'])) {
            $statusInput = strtoupper(trim((string) $filters['status']));
            $query->where('status', $statusInput);
        } else {
            $query->whereIn('status', ['WAITING_FINANCE', 'ORDER_APPROVED']);
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

        return [
            'total_orders'               => (clone $query)->count(),
            'pending_count'              => (clone $query)->where(function ($q) {
                $q->where('logistic_status', 'PENDING')->orWhereNull('logistic_status');
            })->count(),
            'logistic_approved_count'    => (clone $query)->where('logistic_status', 'APPROVED')->count(),
            'reschedule_requested_count' => (clone $query)->where('logistic_status', 'RESCHEDULE_REQUESTED')->count(),
            'admin_approved_count'       => (clone $query)->where('logistic_status', 'RESCHEDULE_APPROVED')->count(),
            'total_approved_count'       => (clone $query)->whereIn('logistic_status', ['APPROVED', 'RESCHEDULE_APPROVED'])->count(),
            'total_reschedule_count'     => (clone $query)->whereIn('logistic_status', ['RESCHEDULE_REQUESTED', 'RESCHEDULE_APPROVED'])->count(),
        ];
    }

    /**
     * Get complete dashboard payload: summary counters, current tab, and paginated orders.
     */
    public function getDashboard(array $filters = [], int $perPage = 15): array
    {
        $summary = $this->getDashboardSummary($filters);
        $paginator = $this->listOrders($filters, $perPage);

        $currentTab = !empty($filters['tab'])
            ? strtolower(trim((string) $filters['tab']))
            : (!empty($filters['category']) ? strtolower(trim((string) $filters['category'])) : 'all');

        return [
            'summary'     => $summary,
            'current_tab' => $currentTab,
            'orders'      => $paginator->items(),
            'meta'        => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
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
     * In both statuses, automatically triggers Inventory Transfer (addIT) to SAP if not yet created.
     */
    public function approveOrder(int $orderId, int $userId, array $data = []): SalesOrder
    {
        $order = SalesOrder::with(['details.item'])->find($orderId);
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

        $targetToWhsCode = trim((string) ($data['to_whs_code'] ?? $data['ToWhsCode'] ?? 'VPGMN01'));
        if (empty($targetToWhsCode)) {
            $targetToWhsCode = 'VPGMN01';
        }
        $nopol = trim((string) ($data['nopol'] ?? $data['Nopol'] ?? ''));
        $namaSupir = trim((string) ($data['nama_supir'] ?? $data['NamaSupir'] ?? $data['driver_name'] ?? ''));

        // 1. Check if Inventory Transfer (IT) has already been created for this Sales Order
        $sapItDocNum = $order->sap_it_doc_num;
        $sapItDocEntry = $order->sap_it_doc_entry;
        $sapItStatus = $order->sap_it_status;

        if (empty($sapItDocNum)) {
            // Trigger IT to SAP first; if it fails, throw Exception (fail-fast atomic)
            $itResult = $this->executeInventoryTransferToSap($order, $userId, $targetToWhsCode, $nopol, $namaSupir, $data);
            $sapItDocNum = $itResult['doc_num'] ?: 'PROCESSED';
            $sapItDocEntry = $itResult['doc_entry'] ?: $sapItDocNum;
            $sapItStatus = 'SUCCESS';
        }

        // 2. Perform DB updates in transaction
        DB::beginTransaction();
        try {
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
                    'to_whs_code'        => $targetToWhsCode,
                    'nopol'              => $nopol ?: $order->nopol,
                    'nama_supir'         => $namaSupir ?: $order->nama_supir,
                    'sap_it_doc_entry'   => $sapItDocEntry,
                    'sap_it_doc_num'     => $sapItDocNum,
                    'sap_it_status'      => $sapItStatus,
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
                    'sap_it_doc_entry'  => $sapItDocEntry,
                    'sap_it_doc_num'    => $sapItDocNum,
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
                    'to_whs_code'        => $targetToWhsCode,
                    'nopol'              => $nopol ?: $order->nopol,
                    'nama_supir'         => $namaSupir ?: $order->nama_supir,
                    'sap_it_doc_entry'   => $sapItDocEntry,
                    'sap_it_doc_num'     => $sapItDocNum,
                    'sap_it_status'      => $sapItStatus,
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
                    'sap_it_doc_entry'  => $sapItDocEntry,
                    'sap_it_doc_num'    => $sapItDocNum,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to save approval for Sales Order #{$order->id}: " . $e->getMessage());
            throw $e;
        }

        return $order->fresh(['details.item', 'distributor', 'latestLogisticLog']);
    }

    /**
     * Execute Inventory Transfer (IT) to SAP B1 API (/api/addIT).
     *
     * @param SalesOrder $order
     * @param int $userId
     * @param string $toWhsCode
     * @param string $nopol
     * @param string $namaSupir
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function executeInventoryTransferToSap(
        SalesOrder $order,
        int $userId,
        string $toWhsCode = 'VPGMN01',
        string $nopol = '',
        string $namaSupir = '',
        array $data = []
    ): array {
        if (!$order->relationLoaded('details') || $order->details->isEmpty()) {
            $order->load('details');
        }

        if ($order->details->isEmpty()) {
            throw new \Exception("Cannot perform Inventory Transfer: Sales Order #{$order->order_no} has no line items.", 400);
        }

        $headerFiller = (string) ($order->details->first()?->whs_code ?: 'FG01');

        $lines = [];
        foreach ($order->details as $detail) {
            $itemCode = trim((string) $detail->item_code);
            $qty = floatval($detail->quantity);
            if (empty($itemCode) || $qty <= 0) {
                continue;
            }

            $itemFiller = trim((string) ($detail->whs_code ?: $headerFiller));
            $lines[] = [
                'ItemCode'     => $itemCode,
                'Quantity'     => $qty,
                'UomEntry'     => is_numeric($detail->uom_entry) ? (int)$detail->uom_entry : 0,
                'Filler'       => $itemFiller,
                'ToWhsCode'    => $toWhsCode,
                'UseBaseUn'    => 'Y',
                'OcrCode'      => (string) ($detail->ocr_code ?? ''),
                'OcrCode2'     => (string) ($detail->ocr_code2 ?? ''),
                'OcrCode3'     => (string) ($detail->ocr_code3 ?? ''),
                'BinActivfrom' => 'N',
                'BinActivto'   => 'N',
            ];
        }

        if (empty($lines)) {
            throw new \Exception("Cannot perform Inventory Transfer: Sales Order #{$order->order_no} has no valid items with quantity > 0.", 400);
        }

        $docDueDate = $order->req_due_date
            ? $order->req_due_date->format('Y-m-d')
            : ($order->doc_due_date ? $order->doc_due_date->format('Y-m-d') : now()->toDateString());

        $itPayload = [
            'DocDate'    => now()->toDateString(),
            'DocDueDate' => $docDueDate,
            'Filler'     => $headerFiller,
            'ToWhsCode'  => $toWhsCode,
            'Comments'   => "Inventory Transfer for SO #{$order->order_no}" . ($order->sap_doc_num ? " (SAP #{$order->sap_doc_num})" : ''),
            'BeratBruto' => (string) ($data['berat_bruto'] ?? $data['BeratBruto'] ?? ''),
            'BeratTara'  => (string) ($data['berat_tara'] ?? $data['BeratTara'] ?? ''),
            'Nopol'      => $nopol,
            'NamaSupir'  => $namaSupir,
            'AddonId'    => 2,
            'UserId'     => (int) $userId,
            'Lines'      => $lines,
        ];

        $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');

        try {
            $response = Http::timeout(30)->post("{$sapUrl}/api/addIT", $itPayload);
        } catch (\Throwable $e) {
            Log::error("Failed to connect to SAP /api/addIT for SO #{$order->id}: " . $e->getMessage());
            throw new \Exception("Failed to connect to SAP API for Inventory Transfer: " . $e->getMessage(), 400);
        }

        if (!$response->successful()) {
            $status = $response->status();
            $body = $response->body();
            Log::error("SAP /api/addIT returned HTTP {$status} for SO #{$order->id}: {$body}");
            throw new \Exception("Failed to process Inventory Transfer to SAP (HTTP {$status}): " . substr($body, 0, 250), 400);
        }

        $result = $response->json();
        if (isset($result['ErrorCode']) && (int) $result['ErrorCode'] !== 0) {
            $errMsg = $result['Message'] ?? 'Unknown SAP error during Inventory Transfer.';
            Log::error("SAP /api/addIT returned ErrorCode {$result['ErrorCode']} for SO #{$order->id}: {$errMsg}");
            throw new \Exception("SAP Inventory Transfer Error [{$result['ErrorCode']}]: {$errMsg}", 400);
        }

        $docEntry = null;
        $docNum = null;

        if (isset($result['Result'])) {
            if (is_array($result['Result'])) {
                $docEntry = $result['Result']['DocEntry'] ?? $result['Result'][0]['DocEntry'] ?? null;
                $docNum = $result['Result']['DocNum'] ?? $result['Result'][0]['DocNum'] ?? null;
            } elseif (is_numeric($result['Result'])) {
                $docEntry = (string) $result['Result'];
            }
        }

        if (!$docNum && !empty($result['Message']) && preg_match('/DocNum:\s*(\d+)/i', $result['Message'], $m)) {
            $docNum = $m[1];
        }
        if (!$docEntry && !empty($result['Message']) && preg_match('/DocEntry:\s*(\d+)/i', $result['Message'], $m)) {
            $docEntry = $m[1];
        }
        if (!$docNum && $docEntry) {
            $docNum = $docEntry;
        }
        if (!$docEntry && $docNum) {
            $docEntry = $docNum;
        }

        return [
            'doc_entry' => $docEntry ? (string) $docEntry : null,
            'doc_num'   => $docNum ? (string) $docNum : null,
            'raw'       => $result,
        ];
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
