<?php

namespace App\Modules\SalesReturn\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SalesReturn\Services\SalesReturnService;
use App\Models\SalesOrder;
use App\Traits\ApiResponseFormatter;
use App\Traits\HasCustomerCodeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalesReturnController extends Controller
{
    use ApiResponseFormatter, HasCustomerCodeResolver;

    protected SalesReturnService $service;

    public function __construct(SalesReturnService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all sales returns with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'start_date', 'end_date']);

        $customerCodes = $this->resolveCustomerCodes($request);
        if (!empty($customerCodes)) {
            $filters['card_code'] = implode(',', $customerCodes);
        }

        $data = $this->service->getAll($filters);
        return $this->successResponse($data, 'Daftar pengajuan retur berhasil diambil.');
    }

    /**
     * Get details of a single sales return request.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $salesReturn = $this->service->getById($id);
        if (!$salesReturn) {
            return $this->errorResponse('Data retur tidak ditemukan.', [], 404);
        }

        // Security check
        $user = $request->user();
        if ($user->code_customer) {
            $allowedCodes = array_filter(array_map('trim', explode(',', $user->code_customer)));
            if (!in_array($salesReturn->card_code, $allowedCodes)) {
                return $this->errorResponse('Akses ditolak. Anda tidak memiliki akses ke data retur ini.', [], 403);
            }
        }

        return $this->successResponse($salesReturn, 'Detail pengajuan retur berhasil diambil.');
    }

    /**
     * Create and submit a return request.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Validate request payload
        $payload = $request->validate([
            'sales_order_id' => 'required|integer',
            'reason' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.sales_order_detail_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.reason' => 'nullable|string|max:500',
            'items.*.do_num' => 'nullable|string|max:50',
            'items.*.baseline' => 'nullable|integer',
            'items.*.do_qty' => 'nullable|numeric|min:0',
            'items.*.do_quantity' => 'nullable|numeric|min:0',
            'items.*.do_date' => 'nullable|string|max:50',
            'attachments' => 'nullable|array',
            'attachments.*' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:10240', // max 10MB per image
        ]);

        // 2. Validate Sales Order access permission
        $salesOrder = SalesOrder::find($payload['sales_order_id']);
        if (!$salesOrder) {
            return $this->errorResponse('Sales Order tidak ditemukan.', [], 404);
        }

        if ($user->code_customer) {
            $allowedCodes = array_filter(array_map('trim', explode(',', $user->code_customer)));
            if (!in_array($salesOrder->card_code, $allowedCodes)) {
                return $this->errorResponse('Akses ditolak. Anda tidak memiliki akses ke Sales Order ini.', [], 403);
            }
        }

        // 3. Extract uploaded files
        $uploadedFiles = $request->file('attachments') ?? [];

        try {
            $result = $this->service->createReturnRequest($payload, $uploadedFiles, $user->id);
            return $this->successResponse($result, 'Pengajuan retur berhasil dikirim.', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal membuat pengajuan retur: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get Delivery Order (DO) lines by Sales Order (SO) DocNum from SAP.
     */
    public function getDoBySo(Request $request): JsonResponse
    {
        $request->validate([
            'so_num' => 'required|string',
        ]);

        try {
            $data = $this->service->getDoBySo($request->input('so_num'));
            return $this->successResponse($data, 'Data DO by SO berhasil diambil dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }

    /**
     * Approve return request (admin sales only).
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $isDistributor = ($user->role && strtoupper($user->role->name) === 'DISTRIBUTOR');
        if ($isDistributor) {
            return $this->errorResponse('Akses ditolak. Distributor tidak diizinkan untuk menyetujui retur.', [], 403);
        }

        $salesReturn = $this->service->getById($id);
        if (!$salesReturn) {
            return $this->errorResponse('Data retur tidak ditemukan.', [], 404);
        }

        try {
            $result = $this->service->approve($salesReturn, $user->id);
            return $this->successResponse($result, 'Pengajuan retur berhasil disetujui.');
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menyetujui pengajuan retur: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Reject return request (admin sales only).
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $isDistributor = ($user->role && strtoupper($user->role->name) === 'DISTRIBUTOR');
        if ($isDistributor) {
            return $this->errorResponse('Akses ditolak. Distributor tidak diizinkan untuk menolak retur.', [], 403);
        }

        $salesReturn = $this->service->getById($id);
        if (!$salesReturn) {
            return $this->errorResponse('Data retur tidak ditemukan.', [], 404);
        }

        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        try {
            $result = $this->service->reject($salesReturn, $request->input('reason'), $user->id);
            return $this->successResponse($result, 'Pengajuan retur berhasil ditolak.');
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menolak pengajuan retur: ' . $e->getMessage(), [], 500);
        }
    }
}
