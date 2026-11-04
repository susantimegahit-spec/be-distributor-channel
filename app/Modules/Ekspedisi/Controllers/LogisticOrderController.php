<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ekspedisi\Services\LogisticOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LogisticOrderController extends Controller
{
    protected LogisticOrderService $logisticOrderService;

    public function __construct(LogisticOrderService $logisticOrderService)
    {
        $this->logisticOrderService = $logisticOrderService;
    }

    /**
     * Get paginated list of sales orders with WAITING_FINANCE or ORDER_APPROVED status for logistic readiness tracking.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'tab'             => $request->query('tab') ?: $request->query('category'),
            'status'          => $request->query('status'),
            'logistic_status' => $request->query('logistic_status'),
            'search'          => $request->query('search'),
            'date_from'       => $request->query('date_from'),
            'date_to'         => $request->query('date_to'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orders = $this->logisticOrderService->listOrders($filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Delivery orders retrieved successfully.',
            'data'    => $orders->items(),
            'meta'    => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * Get dashboard summary statistics (KPI counters) and paginated orders grouped/filtered by tabs.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $filters = [
            'tab'             => $request->query('tab') ?: $request->query('category'),
            'status'          => $request->query('status'),
            'logistic_status' => $request->query('logistic_status'),
            'search'          => $request->query('search'),
            'date_from'       => $request->query('date_from'),
            'date_to'         => $request->query('date_to'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $dashboardData = $this->logisticOrderService->getDashboard($filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard delivery orders retrieved successfully.',
            'data'    => $dashboardData,
        ]);
    }

    /**
     * Request delivery reschedule by Logistics team.
     */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'rescheduled_date'       => 'required_without:proposed_delivery_date|date',
            'proposed_delivery_date' => 'required_without:rescheduled_date|date',
            'eta_date'               => 'nullable|date',
            'proposed_eta_date'      => 'nullable|date',
            'notes'                  => 'nullable|string|max:1000',
        ]);

        try {
            $user = $request->user();
            $order = $this->logisticOrderService->rescheduleOrder($id, $user->id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Delivery schedule reschedule request submitted successfully. Awaiting sales admin review.',
                'data'    => $order,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors'  => $e->validator->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve delivery schedule:
     * - If logistic_status is RESCHEDULE_REQUESTED, Admin Sales approves the proposed reschedule.
     * - If logistic_status is PENDING, Logistics confirms delivery schedule and shipment readiness.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'due_date' => 'nullable|date',
            'eta_date' => 'nullable|date',
            'notes'    => 'nullable|string|max:1000',
        ]);

        try {
            $user = $request->user();
            $order = $this->logisticOrderService->approveOrder($id, $user->id, $request->all());

            $isRescheduleApproved = ($order->logistic_status === 'RESCHEDULE_APPROVED');
            $message = $isRescheduleApproved
                ? 'Rescheduled delivery schedule approved successfully. Delivery due date and ETA have been updated.'
                : 'Logistics delivery schedule confirmed and approved successfully.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $order,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors'  => $e->validator->errors(),
            ], 422);
        } catch (\Exception $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int)$e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Create / execute Inventory Transfer (IT) to SAP for an approved sales order.
     */
    public function inventoryTransfer(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'to_whs_code' => 'nullable|string|max:50',
            'nopol'       => 'nullable|string|max:50',
            'nama_supir'  => 'nullable|string|max:150',
            'berat_bruto' => 'nullable|numeric|min:0',
            'berat_tara'  => 'nullable|numeric|min:0',
            'notes'       => 'nullable|string|max:1000',
        ]);

        try {
            $user = $request->user();
            $order = $this->logisticOrderService->createInventoryTransfer($id, $user->id, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Inventory Transfer (IT) processed successfully to SAP.',
                'data'    => $order,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors'  => $e->validator->errors(),
            ], 422);
        } catch (\Exception $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int)$e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get detail activity logs for a sales order.
     */
    public function logs(int $id): JsonResponse
    {
        try {
            $data = $this->logisticOrderService->getOrderLogs($id);

            return response()->json([
                'success' => true,
                'message' => 'Delivery monitoring logs retrieved successfully.',
                'data'    => $data,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors'  => $e->validator->errors(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
