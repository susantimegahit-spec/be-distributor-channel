<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ekspedisi\Services\PicklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PicklistController extends Controller
{
    protected PicklistService $picklistService;

    public function __construct(PicklistService $picklistService)
    {
        $this->picklistService = $picklistService;
    }

    /**
     * Get paginated list of picklists.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'shipping_type' => $request->query('shipping_type'),
            'status'        => $request->query('status'),
            'search'        => $request->query('search'),
            'date_from'     => $request->query('date_from'),
            'date_to'       => $request->query('date_to'),
            'per_page'      => $request->query('per_page', 15),
        ];

        try {
            $result = $this->picklistService->getPicklists($filters);

            return response()->json([
                'success' => true,
                'message' => 'Picklists retrieved successfully.',
                'data'    => $result['data'],
                'meta'    => $result['meta'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve picklists: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of approved Sales Orders available for picklist creation.
     */
    public function availableOrders(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
        ];

        try {
            $orders = $this->picklistService->getAvailableOrdersForPicklist($filters);

            return response()->json([
                'success' => true,
                'message' => 'Available orders for picklist retrieved successfully.',
                'data'    => $orders,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available orders: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new Picklist.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'shipping_type'       => 'required|string|in:internal,external,pickup',
            'posting_date'        => 'required|date',
            'due_date'            => 'required|date|after_or_equal:posting_date',
            'delivery_order_no'   => 'nullable|string|max:50',
            'total_weight_limit'  => 'nullable|numeric|min:0',
            'comments'            => 'nullable|string|max:1000',
            'license_plate'       => 'nullable|string|max:50',
            'driver_name'         => 'nullable|string|max:100',
            'checker_name'        => 'nullable|string|max:100',
            'expedition_id'       => 'nullable|integer',
            'expedition_name'     => 'nullable|string|max:150',
            'expedition_rate_id'  => 'nullable|integer',
            'service_type'        => 'nullable|string|max:50',
            'estimated_cost'      => 'nullable|numeric|min:0',
            'items'               => 'required|array|min:1',
            'items.*.sales_order_id'        => 'required|integer',
            'items.*.sales_order_detail_id' => 'nullable|integer',
            'items.*.item_code'             => 'required|string|max:50',
            'items.*.item_name'             => 'nullable|string|max:255',
            'items.*.whs_code'              => 'nullable|string|max:50',
            'items.*.unit_msr'              => 'nullable|string|max:20',
            'items.*.ordered_qty'           => 'nullable|numeric|min:0',
            'items.*.pick_qty'              => 'required|numeric|min:0.0001',
            'items.*.unit_weight'           => 'nullable|numeric|min:0',
        ]);

        try {
            $user = $request->user();
            $picklist = $this->picklistService->createPicklist($request->all(), $user?->id);

            return response()->json([
                'success' => true,
                'message' => 'Picklist created successfully.',
                'data'    => $picklist,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors'  => $e->validator->errors(),
            ], 422);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get detail of a specific picklist.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $picklist = $this->picklistService->getPicklistDetail($id);

            return response()->json([
                'success' => true,
                'message' => 'Picklist detail retrieved successfully.',
                'data'    => $picklist,
            ]);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 404;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Update status of a picklist (COMPLETED / CANCELLED).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:COMPLETED,CANCELLED,completed,cancelled',
        ]);

        try {
            $user = $request->user();
            $picklist = $this->picklistService->updateStatus($id, $request->input('status'), $user?->id);

            return response()->json([
                'success' => true,
                'message' => "Picklist status updated successfully to {$picklist->status}.",
                'data'    => $picklist,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors'  => $e->validator->errors(),
            ], 422);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
