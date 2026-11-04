<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ekspedisi\Services\PicklistWorkerManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PicklistWorkerManagementController extends Controller
{
    protected PicklistWorkerManagementService $service;

    public function __construct(PicklistWorkerManagementService $service)
    {
        $this->service = $service;
    }

    /**
     * Get list of picklists that have been submitted by checker or add-do,
     * equipped with worker summary and regular vs overtime breakdown.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'shipping_type' => $request->query('shipping_type'),
            'status'        => $request->query('status'),
            'date_from'     => $request->query('date_from'),
            'date_to'       => $request->query('date_to'),
            'search'        => $request->query('search'),
            'rate_type'     => $request->query('rate_type'),
            'per_page'      => $request->query('per_page', 15),
        ];

        try {
            $result = $this->service->getWorkerManagementPicklists($filters);

            return response()->json([
                'success' => true,
                'message' => 'Worker management picklists retrieved successfully.',
                'data'    => $result['data'],
                'meta'    => $result['meta'],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve worker management picklists: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detail of a single picklist for worker management dashboard,
     * including complete regular vs overtime worker calculation analysis.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $data = $this->service->getWorkerManagementDetail($id);

            return response()->json([
                'success' => true,
                'message' => 'Worker management picklist detail retrieved successfully.',
                'data'    => $data,
            ], 200);
        } catch (\Throwable $e) {
            $status = $e->getCode() === 404 ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}
