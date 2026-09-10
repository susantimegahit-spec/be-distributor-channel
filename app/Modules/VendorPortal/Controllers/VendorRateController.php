<?php

namespace App\Modules\VendorPortal\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\VendorPortal\Models\Vendor;
use App\Modules\VendorPortal\Models\VendorUser;
use App\Modules\VendorPortal\Services\VendorLegalApprovalService;
use App\Modules\VendorPortal\Services\VendorRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VendorRateController extends Controller
{
    protected VendorRateService $rateService;
    protected VendorLegalApprovalService $approvalService;

    public function __construct(VendorRateService $rateService, VendorLegalApprovalService $approvalService)
    {
        $this->rateService = $rateService;
        $this->approvalService = $approvalService;
    }

    /**
     * Resolve the authenticated expedition vendor.
     */
    protected function resolveVendor(Request $request): Vendor
    {
        $user = $request->user();

        if (!$user instanceof VendorUser) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['success' => false, 'message' => 'Unauthorized access. Only vendor users can access this endpoint.'], 403)
            );
        }

        $vendor = $user->vendor;

        if (!$vendor || $vendor->registration_status !== 'APPROVED') {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['success' => false, 'message' => 'Vendor account is not approved by the legal team yet.'], 403)
            );
        }

        if (strtoupper($vendor->vendor_type) !== 'EXPEDITION') {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json(['success' => false, 'message' => 'Rate card submission is only available for expedition vendor partners.'], 422)
            );
        }

        // Auto-link expedition if missing
        if (!$vendor->expedition_id) {
            $this->approvalService->syncToExpeditionMaster($vendor);
            $vendor->refresh();
        }

        return $vendor;
    }

    /**
     * Get paginated rates submitted by this vendor.
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $this->resolveVendor($request);
        $perPage = (int) $request->input('per_page', 15);

        $filters = [
            'approval_status' => $request->input('approval_status'),
            'transport_mode'  => $request->input('transport_mode'),
            'warehouse_id'    => $request->input('warehouse_id'),
            'destination_id'  => $request->input('destination_id'),
            'search'          => $request->input('search'),
        ];

        $rates = $this->rateService->listRates($vendor, $filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Vendor rates retrieved successfully.',
            'data' => $rates->items(),
            'meta' => [
                'current_page' => $rates->currentPage(),
                'last_page'    => $rates->lastPage(),
                'per_page'     => $rates->perPage(),
                'total'        => $rates->total(),
            ],
        ]);
    }

    /**
     * Submit a single rate manually from vendor portal form.
     */
    public function store(Request $request): JsonResponse
    {
        $vendor = $this->resolveVendor($request);

        $validator = Validator::make($request->all(), [
            'warehouse_id'     => 'nullable|integer',
            'origin'           => 'nullable|string',
            'destination_id'   => 'nullable|integer',
            'destination'      => 'nullable|string',
            'transport_mode'   => 'nullable|string|max:50',
            'service_type'     => 'nullable|string|max:50',
            'min_tonnage'      => 'nullable|numeric|min:0',
            'max_tonnage'      => 'nullable|numeric|min:0',
            'price'            => 'required|numeric|gt:0',
            'eta_days'         => 'nullable|integer|min:0',
            'min_shipment_qty' => 'nullable|numeric|min:0',
            'max_shipment_qty' => 'nullable|numeric|min:0',
            'valid_from'       => 'nullable|date',
            'valid_until'      => 'nullable|date|after_or_equal:valid_from',
            'remarks'          => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $rate = $this->rateService->submitRate($vendor, $request->all(), $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Expedition rate submitted successfully. Pending review by logistics team.',
                'data'    => $rate,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
                'errors'  => $e->validator->errors(),
            ], 422);
        }
    }

    /**
     * Upload rates via Excel/CSV spreadsheet.
     */
    public function upload(Request $request): JsonResponse
    {
        $vendor = $this->resolveVendor($request);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ], [
            'file.required' => 'Spreadsheet file is required.',
            'file.mimes'    => 'File must be an Excel spreadsheet (.xlsx, .xls) or CSV file.',
            'file.max'      => 'File size must not exceed 10MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->rateService->uploadRates($vendor, $request->file('file'), $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Rate spreadsheet uploaded and processed successfully. Submitted rates are pending review.',
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download standard CSV template for vendor rate card submission.
     */
    public function template(): Response
    {
        $csv = $this->rateService->generateTemplateCsv();

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="vendor_rate_submission_template.csv"',
        ]);
    }
}
