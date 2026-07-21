<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Services\ProductionService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    use ApiResponseFormatter;

    protected ProductionService $productionService;

    /**
     * ProductionController constructor.
     *
     * @param  ProductionService  $productionService
     */
    public function __construct(ProductionService $productionService)
    {
        $this->productionService = $productionService;
    }

    /**
     * Display a listing of production resources.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function indexResources(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        $resources = $this->productionService->getAllResources($filters);

        return $this->successResponse($resources, 'Daftar resource produksi berhasil diambil.');
    }

    /**
     * Synchronize production resources from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function syncResources(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $syncedData = $this->productionService->syncResourcesFromSap($userId);
            return $this->successResponse($syncedData, 'Data resource produksi berhasil disinkronisasi dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display a listing of production items.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function indexItems(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        $items = $this->productionService->getAllItems($filters);

        return $this->successResponse($items, 'Daftar item produksi berhasil diambil.');
    }

    /**
     * Synchronize production items from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function syncItems(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $syncedData = $this->productionService->syncItemsFromSap($userId);
            return $this->successResponse($syncedData, 'Data item produksi berhasil disinkronisasi dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get list of production BOMs.
     */
    public function indexBoms(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'code']);
        $boms = $this->productionService->getAllBoms($filters);

        return $this->successResponse($boms, 'Daftar Bill of Material berhasil diambil.');
    }

    /**
     * Get detail of a specific production BOM.
     */
    public function showBom(Request $request, int $id): JsonResponse
    {
        $bom = $this->productionService->getBomById($id);
        if (!$bom) {
            return $this->errorResponse('Bill of Material tidak ditemukan.', [], 404);
        }

        return $this->successResponse($bom, 'Detail Bill of Material berhasil diambil.');
    }

    /**
     * Create a new production BOM.
     */
    public function storeBom(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required_without:product|string|max:50',
            'qty' => 'required_without:quantity|numeric|min:0.0001',
            'to_whs' => 'required_without:warehouse|string|max:20',
            'details' => 'required|array|min:1',
        ]);

        $userId = $request->user()?->id;

        // Normalize parameters
        $code = $request->input('code');
        if (is_array($request->input('product'))) {
            $code = $request->input('product.value');
        } elseif (is_string($request->input('product'))) {
            $code = $request->input('product');
        }

        $qty = $request->input('qty') ?? $request->input('quantity');
        
        $toWhs = $request->input('to_whs');
        if (is_array($request->input('warehouse'))) {
            $toWhs = $request->input('warehouse.value');
        } elseif (is_string($request->input('warehouse'))) {
            $toWhs = $request->input('warehouse');
        }

        $ocrCode = $request->input('ocr_code');
        if (is_array($request->input('distributionRule'))) {
            $ocrCode = $request->input('distributionRule.value');
        } elseif (is_string($request->input('distributionRule'))) {
            $ocrCode = $request->input('distributionRule');
        }

        $details = [];
        foreach ($request->input('details', []) as $raw) {
            $type = $raw['type'] ?? '';
            if ($type === '4') {
                $type = 'Item';
            } elseif ($type === '290') {
                $type = 'Resource';
            }

            $compCode = $raw['code'] ?? '';
            if (is_array($raw['item'] ?? null)) {
                $compCode = $raw['item']['value'];
            } elseif (is_string($raw['item'] ?? null)) {
                $compCode = $raw['item'];
            }

            $details[] = [
                'type' => $type,
                'code' => $compCode,
                'quantity' => $raw['quantity'] ?? $raw['qty'] ?? 0,
                'warehouse' => $raw['warehouse'] ?? null,
                'issue_mthd' => $raw['issue_mthd'] ?? $raw['issueMethod'] ?? 'B',
                'ocr_code' => $raw['ocr_code'] ?? null,
                'ocr_code2' => $raw['ocr_code2'] ?? null,
                'ocr_code3' => $raw['ocr_code3'] ?? null,
                'comments' => $raw['comments'] ?? null,
            ];
        }

        $data = [
            'code' => $code,
            'qty' => $qty,
            'to_whs' => $toWhs,
            'type' => $request->input('type', 'P'),
            'alternate' => $request->input('alternate', 1),
            'ocr_code' => $ocrCode,
            'ocr_code2' => $request->input('ocr_code2'),
            'ocr_code3' => $request->input('ocr_code3'),
            'u_shift' => $request->input('u_shift'),
            'u_unit' => $request->input('u_unit'),
            'comments' => $request->input('comments'),
            'is_active' => $request->input('is_active', true),
            'details' => $details,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];

        try {
            $bom = $this->productionService->createBom($data, $userId);
            return $this->successResponse($bom, 'Bill of Material berhasil dibuat.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal membuat Bill of Material: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update an existing production BOM.
     */
    public function updateBom(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'details' => 'sometimes|array|min:1',
        ]);

        $userId = $request->user()?->id;

        $data = $request->only([
            'qty', 'quantity', 'to_whs', 'warehouse', 'type', 'alternate', 
            'ocr_code', 'distributionRule', 'ocr_code2', 'ocr_code3', 
            'u_shift', 'u_unit', 'comments', 'is_active'
        ]);

        // Normalize parameters if present
        if (isset($data['quantity'])) {
            $data['qty'] = $data['quantity'];
            unset($data['quantity']);
        }
        if (isset($data['warehouse'])) {
            $data['to_whs'] = is_array($data['warehouse']) ? $data['warehouse']['value'] : $data['warehouse'];
            unset($data['warehouse']);
        }
        if (isset($data['distributionRule'])) {
            $data['ocr_code'] = is_array($data['distributionRule']) ? $data['distributionRule']['value'] : $data['distributionRule'];
            unset($data['distributionRule']);
        }

        if ($request->has('details')) {
            $details = [];
            foreach ($request->input('details', []) as $raw) {
                $type = $raw['type'] ?? '';
                if ($type === '4') {
                    $type = 'Item';
                } elseif ($type === '290') {
                    $type = 'Resource';
                }

                $compCode = $raw['code'] ?? '';
                if (is_array($raw['item'] ?? null)) {
                    $compCode = $raw['item']['value'];
                } elseif (is_string($raw['item'] ?? null)) {
                    $compCode = $raw['item'];
                }

                $details[] = [
                    'type' => $type,
                    'code' => $compCode,
                    'quantity' => $raw['quantity'] ?? $raw['qty'] ?? 0,
                    'warehouse' => $raw['warehouse'] ?? null,
                    'issue_mthd' => $raw['issue_mthd'] ?? $raw['issueMethod'] ?? 'B',
                    'ocr_code' => $raw['ocr_code'] ?? null,
                    'ocr_code2' => $raw['ocr_code2'] ?? null,
                    'ocr_code3' => $raw['ocr_code3'] ?? null,
                    'comments' => $raw['comments'] ?? null,
                ];
            }
            $data['details'] = $details;
        }

        $data['updated_by'] = $userId;

        try {
            $bom = $this->productionService->updateBom($id, $data, $userId);
            return $this->successResponse($bom, 'Bill of Material berhasil diperbarui.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memperbarui Bill of Material: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Delete a production BOM.
     */
    public function destroyBom(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $this->productionService->deleteBom($id, $userId);
            return $this->successResponse(null, 'Bill of Material berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus Bill of Material: ' . $e->getMessage(), [], 500);
        }
    }
}
