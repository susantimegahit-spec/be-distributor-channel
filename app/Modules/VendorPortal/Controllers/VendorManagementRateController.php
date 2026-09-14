<?php

namespace App\Modules\VendorPortal\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\VendorPortal\Services\VendorRateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorManagementRateController extends Controller
{
    protected VendorRateService $rateService;

    public function __construct(VendorRateService $rateService)
    {
        $this->rateService = $rateService;
    }

    /**
     * Ensure request user is an internal SMETSA backoffice user.
     */
    protected function authorizeInternalUser(Request $request): User
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access. Only internal users can access this endpoint.',
                ], 403)
            );
        }

        return $user;
    }

    /**
     * Get paginated vendor rates for internal SMETSA staff with vendor/expedition filters.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeInternalUser($request);

        $perPage = (int) $request->input('per_page', 15);

        $filters = [
            'vendor_id'       => $request->input('vendor_id', $request->input('vendorId')),
            'expedition_id'   => $request->input('expedition_id', $request->input('expeditionId')),
            'approval_status' => $request->input('approval_status'),
            'transport_mode'  => $request->input('transport_mode'),
            'warehouse_id'    => $request->input('warehouse_id'),
            'destination_id'  => $request->input('destination_id'),
            'batch_id'        => $request->input('batch_id'),
            'search'          => $request->input('search'),
        ];

        $rates = $this->rateService->listRates(null, $filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Vendor rates retrieved successfully.',
            'data'    => $rates->items(),
            'meta'    => [
                'current_page' => $rates->currentPage(),
                'last_page'    => $rates->lastPage(),
                'per_page'     => $rates->perPage(),
                'total'        => $rates->total(),
            ],
        ]);
    }

    /**
     * Get rate batch summary headers for internal SMETSA staff with vendor/expedition filters.
     */
    public function headers(Request $request): JsonResponse
    {
        $this->authorizeInternalUser($request);

        $perPage = (int) $request->input('per_page', 15);

        $filters = [
            'vendor_id'       => $request->input('vendor_id', $request->input('vendorId')),
            'expedition_id'   => $request->input('expedition_id', $request->input('expeditionId')),
            'approval_status' => $request->input('approval_status'),
            'search'          => $request->input('search'),
            'date_from'       => $request->input('date_from'),
            'date_to'         => $request->input('date_to'),
        ];

        $headers = $this->rateService->listRateHeaders(null, $filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Rate submission headers retrieved successfully.',
            'data'    => $headers->items(),
            'meta'    => [
                'current_page' => $headers->currentPage(),
                'last_page'    => $headers->lastPage(),
                'per_page'     => $headers->perPage(),
                'total'        => $headers->total(),
            ],
        ]);
    }

    /**
     * Get single rate batch header with detailed rate items for internal SMETSA staff.
     */
    public function showBatch(Request $request, string $batchId): JsonResponse
    {
        $this->authorizeInternalUser($request);

        try {
            $data = $this->rateService->getRateBatchDetail(null, $batchId);

            return response()->json([
                'success' => true,
                'message' => 'Rate batch details retrieved successfully.',
                'data'    => $data,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
