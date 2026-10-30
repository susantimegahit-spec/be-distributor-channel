<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Picklist;
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
            'is_checked'    => $request->query('is_checked'),
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
            'expedition_id'       => 'nullable|string|max:100',
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
            'items.*.bin_allocations'       => 'nullable|array',
            'items.*.binAllocations'        => 'nullable|array',
            'items.*.direct_bin_quantity'   => 'nullable|numeric|min:0',
            'items.*.directBinQuantity'     => 'nullable|numeric|min:0',
            'to_whs_code'                   => 'nullable|string|max:50',
            'series'                        => 'nullable',
            'Series'                        => 'nullable',
            'series_name'                   => 'nullable|string|max:100',
            'SeriesName'                    => 'nullable|string|max:100',
            'seal_number'                   => 'nullable|string|max:100',
            'noseal'                        => 'nullable|string|max:100',
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
            'status' => 'required|string|in:open,weigh_in,inprogress,weigh_out,overload,complete,canceled,OPEN,COMPLETED,CANCELLED,OVERLOAD',
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

    /**
     * Add Delivery Order (DO) in SAP B1 for a picklist, or save progress as draft.
     */
    public function addDo(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $payload = $request->all();
            if ($request->allFiles()) {
                $payload = array_merge_recursive($payload, $request->allFiles());
            }
            // Unwrap if wrapped in 'payload' key: { payload: [...], is_draft: true }
            if (isset($payload['payload']) && is_array($payload['payload'])) {
                $rootKeys = array_diff_key($payload, ['payload' => null]);
                $inner = $payload['payload'];
                $payload = array_merge(is_array($inner[0] ?? null) ? $inner[0] : $inner, $rootKeys);
            } elseif (isset($payload[0]) && is_array($payload[0])) {
                // Merge root-level keys (e.g. is_draft) into the unwrapped payload
                $rootKeys = array_diff_key($payload, [0 => null]);
                $payload = array_merge($payload[0], $rootKeys);
            }
            $result = $this->picklistService->addDeliveryOrder($id, $payload, $user?->id);

            $message = !empty($result['doc_num'])
                ? "Delivery Order successfully created in SAP (DocNum: {$result['doc_num']})."
                : ($result['message'] ?? "Draft picklist progress successfully saved.");

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $result,
            ], 200);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Save draft checklist progress for a picklist (multi-checker / multi-warehouse workflow).
     */
    public function saveDraft(Request $request, ?int $id = null): JsonResponse
    {
        try {
            $user = $request->user();
            $picklistId = $id ?? $request->input('picklist_id') ?? $request->input('picklistId');

            if (!$picklistId && $request->isJson()) {
                $firstDoc = $request->input(0);
                if (is_array($firstDoc) && !empty($firstDoc['picklist_id'])) {
                    $picklistId = $firstDoc['picklist_id'];
                }
            }

            if (!$picklistId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Picklist ID is required to save draft progress.',
                ], 422);
            }

            $payload = $request->all();
            if ($request->allFiles()) {
                $payload = array_merge_recursive($payload, $request->allFiles());
            }
            // Unwrap if wrapped in 'payload' key: { payload: [...], is_draft: true }
            if (isset($payload['payload']) && is_array($payload['payload'])) {
                $rootKeys = array_diff_key($payload, ['payload' => null]);
                $inner = $payload['payload'];
                $payload = array_merge(is_array($inner[0] ?? null) ? $inner[0] : $inner, $rootKeys);
            } elseif (isset($payload[0]) && is_array($payload[0])) {
                $rootKeys = array_diff_key($payload, [0 => null]);
                $payload = array_merge($payload[0], $rootKeys);
            }

            $result = $this->picklistService->saveDraftProgress((int) $picklistId, $payload, $user?->id);

            return response()->json([
                'success' => true,
                'message' => 'Draft picklist progress successfully saved.',
                'data'    => $result,
            ], 200);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Save inspection / handover signatures for a picklist.
     */
    public function saveSignatures(Request $request, int $id): JsonResponse
    {
        try {
            $picklist = Picklist::find($id);
            if (!$picklist) {
                return response()->json([
                    'success' => false,
                    'message' => "Picklist with ID #{$id} not found.",
                ], 404);
            }

            $user = $request->user();
            $payload = $request->all();
            if ($request->allFiles()) {
                $payload = array_merge_recursive($payload, $request->allFiles());
            }
            $signatures = $this->picklistService->savePicklistSignatures($picklist, $payload, $user?->id);

            return response()->json([
                'success' => true,
                'message' => 'Signatures saved successfully.',
                'data'    => $signatures,
            ], 200);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Preview / stream signature image directly.
     */
    public function previewSignature(int $id, int $signatureId)
    {
        $signature = \App\Models\PicklistSignature::where('picklist_id', $id)->find($signatureId);
        if (!$signature) {
            abort(404, 'Signature record not found.');
        }

        $path = $signature->signature_path;
        if (empty($path)) {
            abort(404, 'Signature image path is empty.');
        }

        // 1. If stored as Data URI directly
        if (str_starts_with($path, 'data:image/')) {
            $parts = explode(',', $path, 2);
            $mime = 'image/png';
            if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);/i', $parts[0], $m)) {
                $mime = $m[1];
            }
            $binary = base64_decode($parts[1] ?? '');
            return response($binary, 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        // 2. Check S3
        if (config('filesystems.disks.s3.bucket') && \Illuminate\Support\Facades\Storage::disk('s3')->exists($path)) {
            $mime = \Illuminate\Support\Facades\Storage::disk('s3')->mimeType($path) ?: 'image/png';
            return response(\Illuminate\Support\Facades\Storage::disk('s3')->get($path), 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        // 3. Check public storage
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($path) ?: 'image/png';
            return response(\Illuminate\Support\Facades\Storage::disk('public')->get($path), 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $localPath = storage_path('app/public/' . $path);
        if (file_exists($localPath)) {
            $mime = mime_content_type($localPath) ?: 'image/png';
            return response(file_get_contents($localPath), 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $publicPath = public_path('storage/' . $path);
        if (file_exists($publicPath)) {
            $mime = mime_content_type($publicPath) ?: 'image/png';
            return response(file_get_contents($publicPath), 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        abort(404, 'Signature image file not found on disk.');
    }

    /**
     * Direct Add Delivery Order (DO) in SAP B1 with raw payload or sales_order_id, or save draft progress.
     */
    public function directAddDo(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $payload = $request->all();
            if ($request->allFiles()) {
                $payload = array_merge_recursive($payload, $request->allFiles());
            }
            $result = $this->picklistService->directAddDeliveryOrder($payload, $user?->id);

            $message = !empty($result['delivery_order_no']) || !empty($result['doc_num'])
                ? "Delivery Order successfully created in SAP (DocNum: " . ($result['delivery_order_no'] ?? $result['doc_num'] ?? '') . ")."
                : ($result['message'] ?? "Draft picklist progress successfully saved.");

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $result,
            ], 200);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get available Document Series from SAP B1 for Delivery Order / Picklist.
     */
    public function getSeries(Request $request): JsonResponse
    {
        $customQuery = $request->query('CustomQuery') ?? $request->input('CustomQuery') ?? date('Ymd');
        $cardCode = $request->query('CardCode') ?? $request->input('CardCode') ?? $request->query('card_code') ?? $request->input('card_code');

        try {
            $sapUrl = rtrim(config('services.sap.url') ?: env('SAP_API_URL', 'http://103.18.133.187:3100'), '/');
            $response = \Illuminate\Support\Facades\Http::timeout(15)->post("{$sapUrl}/api/getSeriesby", [
                'CustomQuery' => $customQuery,
                'CardCode' => $cardCode,
            ]);

            if (!$response->successful()) {
                $response = \Illuminate\Support\Facades\Http::timeout(15)->post("{$sapUrl}/api/getSeries", [
                    'CustomQuery' => $customQuery,
                ]);
            }

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghubungi API SAP untuk series.',
                ], 502);
            }

            $sapData = $response->json();
            $seriesList = $sapData['Result'] ?? $sapData['result'] ?? [];

            return response()->json([
                'success' => true,
                'message' => 'Daftar series berhasil diambil.',
                'data'    => $seriesList,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve series: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit Tare Weighing (Timbang Masuk / Kosong) for a picklist.
     */
    public function weighIn(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'weight'        => 'required|numeric|min:0.0001',
            'ticket_no'     => 'nullable|string|max:50',
            'operator_name' => 'nullable|string|max:150',
            'weighed_at'    => 'nullable|date',
            'notes'         => 'nullable|string|max:1000',
            'license_plate' => 'nullable|string|max:50',
            'driver_name'   => 'nullable|string|max:100',
        ]);

        try {
            $user = $request->user();
            $result = $this->picklistService->submitWeighIn($id, $request->all(), $user?->id);

            return response()->json([
                'success' => true,
                'message' => 'Timbang masuk (kosong) berhasil dicatat. Status picklist berubah menjadi weigh_in.',
                'data'    => $result,
            ], 200);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Submit Gross Weighing (Timbang Keluar / Isi) for a picklist.
     */
    public function weighOut(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'weight'        => 'required|numeric|min:0.0001',
            'ticket_no'     => 'nullable|string|max:50',
            'operator_name' => 'nullable|string|max:150',
            'weighed_at'    => 'nullable|date',
            'notes'         => 'nullable|string|max:1000',
        ]);

        try {
            $user = $request->user();
            $result = $this->picklistService->submitWeighOut($id, $request->all(), $user?->id);

            $statusCode = empty($result['success']) ? 422 : 200;

            return response()->json([
                'success' => (bool) ($result['success'] ?? true),
                'message' => $result['message'] ?? 'Timbang keluar berhasil diproses.',
                'data'    => $result,
            ], $statusCode);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Reject or cancel weigh-out due to overload.
     */
    public function rejectWeighOut(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'weight'           => 'nullable|numeric|min:0.0001',
            'ticket_no'        => 'nullable|string|max:50',
            'operator_name'    => 'nullable|string|max:150',
            'rejection_reason' => 'nullable|string|max:1000',
            'notes'            => 'nullable|string|max:1000',
            'weighed_at'       => 'nullable|date',
        ]);

        try {
            $user = $request->user();
            $result = $this->picklistService->rejectWeighOutOverload($id, $request->all(), $user?->id);

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Timbang keluar berhasil ditolak karena overload.',
                'data'    => $result,
            ], 200);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get list of all overload records without ID.
     */
    public function getAllOverloads(Request $request): JsonResponse
    {
        $filters = [
            'picklist_id' => $request->query('picklist_id'),
            'picklist_no' => $request->query('picklist_no'),
            'status'      => $request->query('status'),
            'search'      => $request->query('search'),
            'date_from'   => $request->query('date_from'),
            'date_to'     => $request->query('date_to'),
            'per_page'    => $request->query('per_page', 15),
        ];

        try {
            $result = $this->picklistService->getOverloads($filters);

            return response()->json([
                'success' => true,
                'message' => 'Daftar data overload berhasil diambil.',
                'data'    => $result['data'],
                'meta'    => $result['meta'],
            ], 200);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data overload: ' . $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get overload details and temporary history for a picklist.
     */
    public function getOverload(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->picklistService->getOverloadDetail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data overload picklist berhasil diambil.',
                'data'    => $result,
            ], 200);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Adjust overloaded picklist by updating Sales Order in SAP (/api/editso) and syncing local DB.
     */
    public function adjustOverload(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'sales_order_id'  => 'nullable|integer',
            'DocEntry'        => 'nullable|integer',
            'doc_entry'       => 'nullable|integer',
            'NumAtCard'       => 'nullable|string',
            'DocDate'         => 'nullable|string',
            'DocDueDate'      => 'nullable|string',
            'SlpCode'         => 'nullable|integer',
            'CntctCode'       => 'nullable|integer',
            'PayToCode'       => 'nullable|string',
            'Address'         => 'nullable|string',
            'ShipToCode'      => 'nullable|string',
            'Address2'        => 'nullable|string',
            'DiscountPercent' => 'nullable|numeric',
            'DocTotal'        => 'nullable|numeric',
            'IdDiskon'        => 'nullable|string',
            'AddonId'         => 'nullable|string',
            'UserId'          => 'nullable|string',
            'action_taken'    => 'nullable|string',
            'comments'        => 'nullable|string',
            'Comments'        => 'nullable|string',
            'lines'           => 'nullable|array',
            'Lines'           => 'nullable|array',
            'items'           => 'nullable|array',
        ]);

        try {
            $user = $request->user();
            $result = $this->picklistService->adjustOverload($id, $request->all(), $user?->id);

            return response()->json([
                'success' => true,
                'message' => 'Sales Order berhasil di-edit di SAP dan data muatan picklist telah disesuaikan.',
                'data'    => $result,
            ], 200);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Preview item label photo directly (allows standard <img> rendering).
     */
    public function previewLabelPhoto(int $id, int $itemId, int $photoId)
    {
        return $this->picklistService->previewLabelPhoto($id, $itemId, $photoId);
    }

    /**
     * Record start time for a picklist item checklist (KPI).
     */
    public function startItemTime(Request $request, ?int $id = null, ?int $itemId = null): JsonResponse
    {
        $request->validate([
            'start_time'         => 'nullable|date',
            'startTime'          => 'nullable|date',
            'jam_mulai'          => 'nullable|date',
            'checker_name'       => 'nullable|string|max:100',
            'checkerName'        => 'nullable|string|max:100',
            'NamaChecker'        => 'nullable|string|max:100',
            'nama_checker'       => 'nullable|string|max:100',
            'checked_by_checker' => 'nullable|string|max:100',
            'overwrite'          => 'nullable|boolean',
            'item_id'            => 'nullable|integer',
            'itemId'             => 'nullable|integer',
            'picklist_item_id'   => 'nullable|integer',
        ]);

        try {
            $user = $request->user();

            $routeItemId = $request->route('itemId') ?? $request->route('item_id');
            $routeId = $request->route('id');

            if ($routeId !== null && $routeItemId !== null) {
                $resolvedPicklistId = (int) $routeId;
                $resolvedItemId = (int) $routeItemId;
            } elseif ($routeItemId !== null && $routeId === null) {
                $resolvedPicklistId = $request->input('picklist_id') ? (int) $request->input('picklist_id') : null;
                $resolvedItemId = (int) $routeItemId;
            } else {
                $resolvedPicklistId = $routeId !== null ? (int) $routeId : ($request->input('picklist_id') ? (int) $request->input('picklist_id') : null);
                $resolvedItemId = $itemId 
                    ?? $request->input('item_id') 
                    ?? $request->input('itemId') 
                    ?? $request->input('picklist_item_id') 
                    ?? $request->input('id');
            }

            if (!$resolvedItemId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item ID is required to record start time.',
                ], 422);
            }

            $result = $this->picklistService->startItemTime(
                $resolvedPicklistId,
                (int) $resolvedItemId,
                $request->all(),
                $user?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Start time recorded successfully.',
                'data'    => $result,
            ], 200);
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}

