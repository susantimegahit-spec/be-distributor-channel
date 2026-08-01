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

    /**
     * Get list of production orders.
     */
    public function indexOrders(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'item_code']);
        $orders = $this->productionService->getAllOrders($filters);

        return $this->successResponse($orders, 'Daftar Production Order berhasil diambil.');
    }

    /**
     * Get detail of a specific production order.
     */
    public function showOrder(Request $request, int $id): JsonResponse
    {
        $order = $this->productionService->getOrderById($id);
        if (!$order) {
            return $this->errorResponse('Production Order tidak ditemukan.', [], 404);
        }

        return $this->successResponse($order, 'Detail Production Order berhasil diambil.');
    }

    /**
     * Create a new production order.
     */
    public function storeOrder(Request $request): JsonResponse
    {
        // Merge aliases if SAP PascalCase keys are sent
        if ($request->has('ItemCode') && !$request->has('item_code')) {
            $request->merge(['item_code' => $request->input('ItemCode')]);
        }
        if ($request->has('PlannedQty') && !$request->has('planned_qty')) {
            $request->merge(['planned_qty' => $request->input('PlannedQty')]);
        }
        if ($request->has('WhsCode') && !$request->has('warehouse')) {
            $request->merge(['warehouse' => $request->input('WhsCode')]);
        }
        if ($request->has('PostingDate') && !$request->has('post_date')) {
            $request->merge(['post_date' => $request->input('PostingDate')]);
        }
        if ($request->has('DueDate') && !$request->has('due_date')) {
            $request->merge(['due_date' => $request->input('DueDate')]);
        }
        if ($request->has('Lines') && !$request->has('details')) {
            $request->merge(['details' => $request->input('Lines')]);
        }

        $request->validate([
            'item_code' => 'required_without:product|string|max:50',
            'planned_qty' => 'required_without:quantity|numeric|min:0.0001',
            'warehouse' => 'required_without:to_whs|max:50',
            'post_date' => 'required|date',
            'details' => 'required|array|min:1',
        ]);

        $userId = $request->user()?->id;

        // Normalization
        $itemCode = $request->input('item_code');
        if (is_array($request->input('product'))) {
            $itemCode = $request->input('product.value');
        } elseif (is_string($request->input('product'))) {
            $itemCode = $request->input('product');
        }

        $plannedQty = $request->input('planned_qty') ?? $request->input('quantity') ?? $request->input('qty');

        $whs = $request->input('warehouse');
        if (is_array($request->input('warehouse'))) {
            $whs = $request->input('warehouse.value');
        } elseif (is_string($request->input('to_whs'))) {
            $whs = $request->input('to_whs');
        }

        $ocrCode = $request->input('ocr_code');
        if (is_array($request->input('distributionRule'))) {
            $ocrCode = $request->input('distributionRule.value');
        } elseif (is_string($request->input('distributionRule'))) {
            $ocrCode = $request->input('distributionRule');
        }

        $details = [];
        $rawDetails = $request->input('details') ?? $request->input('Lines') ?? [];
        foreach ($rawDetails as $raw) {
            $type = $raw['type'] ?? $raw['ItemType'] ?? 'Item';
            if ($type === '4' || $type === 4 || $type === 'I') {
                $type = 'Item';
            } elseif ($type === '290' || $type === 290 || $type === 'R') {
                $type = 'Resource';
            } elseif ($type === 'T') {
                $type = 'Text';
            }

            $compCode = $raw['code'] ?? $raw['item_code'] ?? $raw['ItemCode'] ?? null;
            if (is_array($raw['item'] ?? null)) {
                $compCode = $raw['item']['value'];
            } elseif (is_string($raw['item'] ?? null)) {
                $compCode = $raw['item'];
            }

            $baseQty = floatval($raw['base_qty'] ?? $raw['baseQty'] ?? $raw['BaseQty'] ?? 1.0);
            $pQty = floatval($raw['planned_qty'] ?? $raw['quantity'] ?? $raw['qty'] ?? ($baseQty * (floatval($plannedQty) > 0 ? floatval($plannedQty) : 1)));

            $details[] = [
                'type' => $type,
                'item_code' => $compCode,
                'base_qty' => $baseQty,
                'planned_qty' => $pQty,
                'issued_qty' => floatval($raw['issued_qty'] ?? $raw['issued'] ?? 0.0),
                'available_qty' => floatval($raw['available_qty'] ?? $raw['available'] ?? 0.0),
                'warehouse' => $raw['warehouse'] ?? $raw['whs_code'] ?? $raw['WhsCode'] ?? null,
                'issue_mthd' => $raw['issue_mthd'] ?? $raw['issueMethod'] ?? $raw['IssueMethod'] ?? 'B',
                'ocr_code' => $raw['ocr_code'] ?? $raw['OcrCode'] ?? null,
                'ocr_code2' => $raw['ocr_code2'] ?? $raw['OcrCode2'] ?? null,
                'ocr_code3' => $raw['ocr_code3'] ?? $raw['OcrCode3'] ?? null,
                'comments' => $raw['comments'] ?? $raw['Remarks'] ?? null,
                'base_entry' => $raw['base_entry'] ?? null,
                'base_type' => $raw['base_type'] ?? null,
                'base_line' => $raw['base_line'] ?? null,
            ];
        }

        $data = [
            'doc_entry' => $request->input('doc_entry'),
            'doc_num' => $request->input('doc_num'),
            'series' => $request->input('series'),
            'prod_order_no' => $request->input('prod_order_no'),
            'status' => $request->input('status', 'PLANNED'),
            'type' => $request->input('type', 'Standard'),
            'item_code' => $itemCode,
            'planned_qty' => $plannedQty,
            'cmplt_qty' => $request->input('cmplt_qty', 0.0),
            'rjct_qty' => $request->input('rjct_qty', 0.0),
            'warehouse' => $whs,
            'priority' => $request->input('priority', 100),
            'project' => $request->input('project'),
            'post_date' => $request->input('post_date'),
            'start_date' => $request->input('start_date'),
            'due_date' => $request->input('due_date'),
            'origin_type' => $request->input('origin_type'),
            'origin_num' => $request->input('origin_num'),
            'card_code' => $request->input('card_code'),
            'ocr_code' => $ocrCode,
            'ocr_code2' => $request->input('ocr_code2'),
            'ocr_code3' => $request->input('ocr_code3'),
            'u_shift' => $request->input('u_shift'),
            'u_unit' => $request->input('u_unit'),
            'comments' => $request->input('comments'),
            'issue_for_production' => $request->input('issue_for_production'),
            'receipt_from_production' => $request->input('receipt_from_production'),
            'production_bom_id' => $request->input('production_bom_id'),
            'act_item_cost' => $request->input('act_item_cost', 0.0),
            'act_res_cost' => $request->input('act_res_cost', 0.0),
            'act_add_cost' => $request->input('act_add_cost', 0.0),
            'act_prod_cost' => $request->input('act_prod_cost', 0.0),
            'act_by_prod_cost' => $request->input('act_by_prod_cost', 0.0),
            'total_variance' => $request->input('total_variance', 0.0),
            'jrnl_memo' => $request->input('jrnl_memo'),
            'ref_doc' => $request->input('ref_doc'),
            'act_close_date' => $request->input('act_close_date'),
            'overdue' => $request->input('overdue'),
            'sap_status' => $request->input('sap_status', 'PENDING'),
            'sap_error' => $request->input('sap_error'),
            'details' => $details,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];

        try {
            $order = $this->productionService->createOrder($data, $userId);
            return $this->successResponse($order, 'Production Order berhasil dibuat.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal membuat Production Order: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update an existing production order.
     */
    public function updateOrder(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()?->id;

        $data = $request->only([
            'doc_entry',
            'doc_num',
            'series',
            'prod_order_no',
            'status',
            'type',
            'item_code',
            'planned_qty',
            'cmplt_qty',
            'rjct_qty',
            'warehouse',
            'priority',
            'project',
            'post_date',
            'start_date',
            'due_date',
            'origin_type',
            'origin_num',
            'card_code',
            'ocr_code',
            'ocr_code2',
            'ocr_code3',
            'u_shift',
            'u_unit',
            'comments',
            'issue_for_production',
            'receipt_from_production',
            'production_bom_id',
            'act_item_cost',
            'act_res_cost',
            'act_add_cost',
            'act_prod_cost',
            'act_by_prod_cost',
            'total_variance',
            'jrnl_memo',
            'ref_doc',
            'act_close_date',
            'overdue',
            'sap_status',
            'sap_error',
        ]);

        // Normalize header item_code if sent as product
        if ($request->has('product')) {
            if (is_array($request->input('product'))) {
                $data['item_code'] = $request->input('product.value');
            } else {
                $data['item_code'] = $request->input('product');
            }
        }

        // Normalize header warehouse if sent as warehouse object
        if ($request->has('warehouse') && is_array($request->input('warehouse'))) {
            $data['warehouse'] = $request->input('warehouse.value');
        }

        // Normalize header ocr_code if sent as distributionRule object
        if ($request->has('distributionRule')) {
            if (is_array($request->input('distributionRule'))) {
                $data['ocr_code'] = $request->input('distributionRule.value');
            } else {
                $data['ocr_code'] = $request->input('distributionRule');
            }
        }

        if ($request->has('details')) {
            $details = [];
            foreach ($request->input('details', []) as $raw) {
                $type = $raw['type'] ?? 'Item';
                if ($type === '4' || $type === 4) {
                    $type = 'Item';
                } elseif ($type === '290' || $type === 290) {
                    $type = 'Resource';
                }

                $compCode = $raw['code'] ?? $raw['item_code'] ?? null;
                if (is_array($raw['item'] ?? null)) {
                    $compCode = $raw['item']['value'];
                } elseif (is_string($raw['item'] ?? null)) {
                    $compCode = $raw['item'];
                }

                $details[] = [
                    'type' => $type,
                    'item_code' => $compCode,
                    'base_qty' => $raw['base_qty'] ?? $raw['baseQty'] ?? 1.0,
                    'planned_qty' => $raw['planned_qty'] ?? $raw['quantity'] ?? $raw['qty'] ?? 0.0,
                    'issued_qty' => $raw['issued_qty'] ?? $raw['issued'] ?? 0.0,
                    'available_qty' => $raw['available_qty'] ?? $raw['available'] ?? 0.0,
                    'warehouse' => $raw['warehouse'] ?? $raw['whs_code'] ?? null,
                    'issue_mthd' => $raw['issue_mthd'] ?? $raw['issueMethod'] ?? 'B',
                    'ocr_code' => $raw['ocr_code'] ?? null,
                    'ocr_code2' => $raw['ocr_code2'] ?? null,
                    'ocr_code3' => $raw['ocr_code3'] ?? null,
                    'comments' => $raw['comments'] ?? null,
                    'base_entry' => $raw['base_entry'] ?? null,
                    'base_type' => $raw['base_type'] ?? null,
                    'base_line' => $raw['base_line'] ?? null,
                ];
            }
            $data['details'] = $details;
        }

        $data['updated_by'] = $userId;

        try {
            $order = $this->productionService->updateOrder($id, $data, $userId);
            return $this->successResponse($order, 'Production Order berhasil diperbarui.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memperbarui Production Order: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Delete a production order.
     */
    public function destroyOrder(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $this->productionService->deleteOrder($id, $userId);
            return $this->successResponse(null, 'Production Order berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus Production Order: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Submit Production Order (PDO) directly to SAP API endpoint (/api/addpdo).
     */
    public function addPdoSap(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $input = $request->all();
        if (isset($input['item_code']) && !isset($input['ItemCode'])) {
            $input['ItemCode'] = $input['item_code'];
        }
        if (isset($input['planned_qty']) && !isset($input['PlannedQty'])) {
            $input['PlannedQty'] = $input['planned_qty'];
        }
        if (isset($input['warehouse']) && !isset($input['WhsCode'])) {
            $input['WhsCode'] = $input['warehouse'];
        }
        if (isset($input['post_date']) && !isset($input['PostingDate'])) {
            $input['PostingDate'] = $input['post_date'];
        }
        if (isset($input['due_date']) && !isset($input['DueDate'])) {
            $input['DueDate'] = $input['due_date'];
        }
        if (isset($input['details']) && !isset($input['Lines'])) {
            $input['Lines'] = $input['details'];
        }

        try {
            $result = $this->productionService->addPdoSap($input, $userId);
            return $this->successResponse($result, 'Production Order (PDO) berhasil dikirim ke SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengirim Production Order (PDO) ke SAP: ' . $e->getMessage(), [], 500);
        }
    }
}
