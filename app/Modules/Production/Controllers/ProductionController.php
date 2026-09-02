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
            'u_unit' => $request->input('uom') ?? $request->input('u_unit') ?? $request->input('unit'),
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
     * Import multiple BOMs from flat Excel / CSV file or JSON rows array.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function importBoms(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $rows = [];

            if ($request->hasFile('file')) {
                $request->validate([
                    'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
                ]);
                $rows = $this->productionService->parseBomsFromUploadedFile($request->file('file'));
            } elseif ($request->has('rows') && is_array($request->input('rows'))) {
                $rows = $request->input('rows');
            } elseif ($request->has('data') && is_array($request->input('data'))) {
                $rows = $request->input('data');
            } elseif (is_array($request->all()) && !empty($request->all()) && isset($request->all()[0])) {
                // Direct array payload [ {...}, {...} ]
                $rows = $request->all();
            } else {
                return $this->errorResponse('Payload tidak valid. Kirimkan file spreadsheet (.xlsx, .xls, .csv) atau JSON array pada field "rows" / "data".', [], 422);
            }

            if (empty($rows)) {
                return $this->errorResponse('Data baris Excel/JSON kosong atau tidak dapat diparse.', [], 422);
            }

            $result = $this->productionService->importBomsFromFlatArray($rows, $userId);

            $message = sprintf(
                'Berhasil memproses %d BOM (%d dibuat, %d diperbarui) dengan %d detail komponen.',
                $result['total_boms'],
                $result['total_boms_created'],
                $result['total_boms_updated'],
                $result['total_items_created']
            );

            return $this->successResponse($result, $message, 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengimpor data BOM: ' . $e->getMessage(), [], 500);
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
            'u_shift', 'u_unit', 'uom', 'unit', 'comments', 'is_active'
        ]);

        // Normalize parameters if present
        if (isset($data['uom'])) {
            $data['u_unit'] = $data['uom'];
            unset($data['uom']);
        } elseif (isset($data['unit'])) {
            $data['u_unit'] = $data['unit'];
            unset($data['unit']);
        }
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
            'series' => $request->input('series') ?? $request->input('Series'),
            'series_name' => $request->input('series_name') ?? $request->input('SeriesName'),
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
            'u_unit' => $request->input('u_unit') ?? $request->input('uom') ?? $request->input('unit') ?? $request->input('Uom') ?? $request->input('UOM'),
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
     * Update an existing production order and its detail items.
     */
    public function updateOrder(Request $request, ?int $id = null): JsonResponse
    {
        $orderId = $id ?? $request->input('id') ?? $request->input('DocEntry') ?? $request->input('doc_entry');
        if (!$orderId) {
            return $this->errorResponse('ID Production Order wajib disertakan.', [], 422);
        }

        $userId = $request->user()?->id;
        $input = $request->all();

        // Header mapping
        $itemCode = $input['item_code'] ?? $input['ItemCode'] ?? $input['product_code'] ?? null;
        if (is_array($input['product'] ?? null)) {
            $itemCode = $input['product']['value'] ?? $itemCode;
        } elseif (is_string($input['product'] ?? null)) {
            $itemCode = $input['product'];
        }

        $whs = $input['warehouse'] ?? $input['whs_code'] ?? $input['WhsCode'] ?? $input['to_whs'] ?? null;
        if (is_array($whs)) {
            $whs = $whs['value'] ?? null;
        }

        $ocrCode = $input['ocr_code'] ?? $input['OcrCode'] ?? null;
        if (is_array($input['distributionRule'] ?? null)) {
            $ocrCode = $input['distributionRule']['value'] ?? $ocrCode;
        } elseif (is_string($input['distributionRule'] ?? null)) {
            $ocrCode = $input['distributionRule'];
        }

        $postDate = $input['post_date'] ?? $input['PostingDate'] ?? null;
        $dueDate = $input['due_date'] ?? $input['DueDate'] ?? null;
        $plannedQty = $input['planned_qty'] ?? $input['PlannedQty'] ?? $input['quantity'] ?? $input['qty'] ?? null;
        $status = $input['status'] ?? $input['Status'] ?? null;
        $comments = $input['comments'] ?? $input['remarks'] ?? $input['Remarks'] ?? null;
        $shift = $input['u_shift'] ?? $input['shift'] ?? $input['Shift'] ?? null;
        $unit = $input['u_unit'] ?? $input['unit'] ?? $input['Unit'] ?? $input['uom'] ?? $input['Uom'] ?? $input['UOM'] ?? null;
        $bomId = $input['production_bom_id'] ?? $input['bom_id'] ?? $input['Bomid'] ?? null;

        $data = [];
        if ($itemCode !== null) $data['item_code'] = $itemCode;
        if ($whs !== null) $data['warehouse'] = $whs;
        if ($ocrCode !== null) $data['ocr_code'] = $ocrCode;
        if ($postDate !== null) $data['post_date'] = date('Y-m-d', strtotime($postDate));
        if ($dueDate !== null) $data['due_date'] = date('Y-m-d', strtotime($dueDate));
        if ($plannedQty !== null) $data['planned_qty'] = floatval($plannedQty);
        if ($status !== null) {
            $statusStr = strtoupper(trim((string)$status));
            $data['status'] = ($statusStr === 'RELEASE') ? 'RELEASED' : $statusStr;
        }
        if ($comments !== null) $data['comments'] = $comments;
        if ($shift !== null) $data['u_shift'] = $shift;
        if ($unit !== null) $data['u_unit'] = $unit;
        if ($bomId !== null) {
            $data['Bomid'] = (string) $bomId;
            if ($bomId === '' || $bomId === '0' || $bomId === 0) {
                $data['production_bom_id'] = null;
            } else {
                $localBomId = is_numeric($bomId) ? (int) $bomId : null;
                if ($localBomId && \App\Models\ProductionBom::where('id', $localBomId)->exists()) {
                    $data['production_bom_id'] = $localBomId;
                } else {
                    $data['production_bom_id'] = null;
                }
            }
        }
        if (isset($input['series']) || isset($input['Series'])) $data['series'] = $input['series'] ?? $input['Series'];
        if (isset($input['series_name']) || isset($input['SeriesName'])) $data['series_name'] = $input['series_name'] ?? $input['SeriesName'];
        if (isset($input['ocr_code2']) || isset($input['OcrCode2'])) $data['ocr_code2'] = $input['ocr_code2'] ?? $input['OcrCode2'];
        if (isset($input['ocr_code3']) || isset($input['OcrCode3'])) $data['ocr_code3'] = $input['ocr_code3'] ?? $input['OcrCode3'];

        // Detail items mapping (supports Lines / lines / details / items)
        $rawDetails = $input['details'] ?? $input['Lines'] ?? $input['lines'] ?? $input['items'] ?? null;
        if ($rawDetails !== null && is_array($rawDetails)) {
            $details = [];
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
                    $compCode = $raw['item']['value'] ?? $compCode;
                } elseif (is_string($raw['item'] ?? null)) {
                    $compCode = $raw['item'];
                }

                $baseQty = floatval($raw['base_qty'] ?? $raw['baseQty'] ?? $raw['BaseQty'] ?? 1.0);
                $pQty = floatval($raw['planned_qty'] ?? $raw['PlannedQty'] ?? $raw['quantity'] ?? $raw['qty'] ?? ($baseQty * (floatval($plannedQty ?? 1) > 0 ? floatval($plannedQty ?? 1) : 1)));

                $details[] = [
                    'type'          => $type,
                    'item_code'     => $compCode,
                    'base_qty'      => $baseQty,
                    'planned_qty'   => $pQty,
                    'issued_qty'    => floatval($raw['issued_qty'] ?? $raw['IssuedQty'] ?? $raw['issued'] ?? 0.0),
                    'available_qty' => floatval($raw['available_qty'] ?? $raw['AvailableQty'] ?? $raw['available'] ?? 0.0),
                    'warehouse'     => $raw['warehouse'] ?? $raw['whs_code'] ?? $raw['WhsCode'] ?? null,
                    'issue_mthd'    => $raw['issue_mthd'] ?? $raw['issueMethod'] ?? $raw['IssueMethod'] ?? 'M',
                    'ocr_code'      => $raw['ocr_code'] ?? $raw['OcrCode'] ?? null,
                    'ocr_code2'     => $raw['ocr_code2'] ?? $raw['OcrCode2'] ?? null,
                    'ocr_code3'     => $raw['ocr_code3'] ?? $raw['OcrCode3'] ?? null,
                    'comments'      => $raw['comments'] ?? $raw['Remarks'] ?? null,
                    'base_entry'    => $raw['base_entry'] ?? null,
                    'base_type'     => $raw['base_type'] ?? null,
                    'base_line'     => $raw['base_line'] ?? null,
                ];
            }
            $data['details'] = $details;
        }

        $data['updated_by'] = $userId;

        try {
            $order = $this->productionService->updateOrder((int)$orderId, $data, $userId);
            return $this->successResponse($order, 'Production Order beserta detailnya berhasil diperbarui.');
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
            $msg = ($result['status'] ?? 'PLANNED') === 'RELEASED' 
                ? 'Production Order (PDO) berhasil disimpan dan dirilis ke SAP.' 
                : 'Production Order (PDO) berhasil disimpan dengan status PLANNED.';
            return $this->successResponse($result, $msg);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menyimpan Production Order (PDO): ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of Production Orders (PDO) from SAP API (/api/getListPDO).
     */
    public function getListPdoSap(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $filters = $request->all();

        try {
            $result = $this->productionService->getListPdoSap($filters, $userId);
            $items = $result['items'] ?? [];
            $message = empty($items) ? 'Data not found.' : 'Production Orders retrieved successfully from SAP.';
            return $this->successResponse($items, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Production Orders from SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get detail of Production Order (PDO) from SAP API (/api/getPDObyId).
     */
    public function getPdoByIdSap(Request $request, ?string $id = null): JsonResponse
    {
        $userId = $request->user()?->id;
        $customQuery = $id ?? $request->input('custom_query') ?? $request->input('CustomQuery') ?? $request->input('doc_entry') ?? $request->input('doc_num');

        if (empty($customQuery)) {
            return $this->errorResponse('Identifier parameter / custom_query (DocEntry / DocNum) is required.', [], 422);
        }

        try {
            $result = $this->productionService->getPdoById($customQuery, $userId);
            if (empty($result['header']) && empty($result['items'])) {
                return $this->successResponse(['header' => null, 'items' => []], 'Data not found.');
            }
            return $this->successResponse($result, 'Production Order detail retrieved successfully from SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Production Order detail from SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of Production Receipts from SAP API (/api/getListReceiptProd).
     */
    public function getListReceiptProdSap(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $filters = $request->all();

        try {
            $result = $this->productionService->getListReceiptProd($filters, $userId);
            $items = $result['items'] ?? [];
            $message = empty($items) ? 'Data not found.' : 'Production Receipts retrieved successfully from SAP.';
            return $this->successResponse($items, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Production Receipts from SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get detail of Production Receipt from SAP API (/api/getReceiptProdbyId).
     */
    public function getReceiptProdByIdSap(Request $request, ?string $id = null): JsonResponse
    {
        $userId = $request->user()?->id;
        $customQuery = $id ?? $request->input('custom_query') ?? $request->input('CustomQuery') ?? $request->input('doc_entry') ?? $request->input('doc_num');

        if (empty($customQuery)) {
            return $this->errorResponse('Identifier parameter / custom_query (DocEntry / DocNum) is required.', [], 422);
        }

        try {
            $result = $this->productionService->getReceiptProdById($customQuery, $userId);
            if (empty($result['header']) && empty($result['items'])) {
                return $this->successResponse(['header' => null, 'items' => []], 'Data not found.');
            }
            return $this->successResponse($result, 'Production Receipt detail retrieved successfully from SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Production Receipt detail from SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get list of Issue for Production from SAP API (/api/getListIssueProd).
     */
    public function getListIssueProdSap(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $filters = $request->all();

        try {
            $result = $this->productionService->getListIssueProd($filters, $userId);
            $items = $result['items'] ?? [];
            $message = empty($items) ? 'Data not found.' : 'Issue for Production list retrieved successfully from SAP.';
            return $this->successResponse($items, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Issue for Production list from SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get detail of Issue for Production from SAP API (/api/getIssueProdbyId).
     */
    public function getIssueProdByIdSap(Request $request, ?string $id = null): JsonResponse
    {
        $userId = $request->user()?->id;
        $customQuery = $id ?? $request->input('custom_query') ?? $request->input('CustomQuery') ?? $request->input('doc_entry') ?? $request->input('doc_num');

        if (empty($customQuery)) {
            return $this->errorResponse('Identifier parameter / custom_query (DocEntry / DocNum) is required.', [], 422);
        }

        try {
            $result = $this->productionService->getIssueProdById($customQuery, $userId);
            if (empty($result['header']) && empty($result['items'])) {
                return $this->successResponse(['header' => null, 'items' => []], 'Data not found.');
            }
            return $this->successResponse($result, 'Issue for Production detail retrieved successfully from SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Issue for Production detail from SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Add Goods Issue for Production on SAP (/api/AddIssueForProduction).
     */
    public function addIssueProdSap(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $input = $request->all();

        try {
            $result = $this->productionService->addIssueProdSap($input, $userId);
            $sapResponse = $result['sap_response'] ?? [];
            return $this->successResponse($sapResponse, $sapResponse['Message'] ?? 'Goods Issue for Production berhasil diproses ke SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memproses Goods Issue for Production ke SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Add Receipt for Production on SAP (/api/addreceiptprod).
     */
    public function addReceiptProdSap(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $input = $request->all();

        try {
            $result = $this->productionService->addReceiptProdSap($input, $userId);
            $sapResponse = $result['sap_response'] ?? [];
            return $this->successResponse($sapResponse, $sapResponse['Message'] ?? 'Receipt for Production berhasil diproses ke SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memproses Receipt for Production ke SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Cancel Production Order (PDO) on SAP (/api/cancelpdo).
     */
    public function cancelPdoSap(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $input = $request->all();

        if (empty($input['DocEntry']) && empty($input['doc_entry'])) {
            return $this->errorResponse('DocEntry wajib diisi.', [], 422);
        }

        try {
            $result = $this->productionService->cancelPdoSap($input, $userId);
            return $this->successResponse($result['sap_response'], 'Production Order (PDO) berhasil dibatalkan di SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal membatalkan Production Order di SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Close Production Order (PDO) on SAP (/api/closepdo).
     */
    public function closePdoSap(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $input = $request->all();

        if (empty($input['DocEntry']) && empty($input['doc_entry']) && empty($input['BaseEntry']) && empty($input['base_entry']) && empty($input['id'])) {
            return $this->errorResponse('DocEntry atau BaseEntry wajib diisi.', [], 422);
        }

        try {
            $result = $this->productionService->closePdoSap($input, $userId);
            return $this->successResponse($result['sap_response'], 'Production Order (PDO) berhasil di-close di SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal meng-close Production Order di SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Cancel Inventory Transfer (IT) on SAP (/api/CancelIT).
     */
    public function cancelItSap(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $input = $request->all();

        if (empty($input['DocEntry']) && empty($input['doc_entry'])) {
            return $this->errorResponse('DocEntry wajib diisi.', [], 422);
        }

        try {
            $result = $this->productionService->cancelItSap($input, $userId);
            return $this->successResponse($result['sap_response'], 'Inventory Transfer (IT) berhasil dibatalkan di SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal membatalkan Inventory Transfer di SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get Master Units from SAP API (/api/GetUnit).
     */
    public function getUnitsSap(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $forceRefresh = $request->boolean('refresh') || $request->boolean('force_refresh');

        try {
            $units = $this->productionService->getUnits($userId, $forceRefresh);
            $message = empty($units) ? 'Data Unit tidak ditemukan.' : 'Master Unit berhasil diambil dari SAP.';
            return $this->successResponse($units, $message);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal mengambil data Master Unit dari SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get stock by items in warehouse from SAP API (/api/getstokbyitem).
     */
    public function getStockByItem(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $params = $request->all();

        try {
            $result = $this->productionService->getStockByItem($params, $userId);
            return $this->successResponse($result, 'Data stok item di gudang berhasil diambil dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), null, 400);
        }
    }

    /**
     * Display a listing of Change Products.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function indexChangeProducts(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'sap_status', 'date_from', 'date_to']);
        $changeProducts = $this->productionService->getAllChangeProducts($filters);

        return $this->successResponse($changeProducts, 'Daftar transaksi Change Product berhasil diambil.');
    }

    /**
     * Display the specified Change Product.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function showChangeProduct(int $id): JsonResponse
    {
        try {
            $cp = $this->productionService->getChangeProductById($id);
            return $this->successResponse($cp, 'Detail transaksi Change Product berhasil diambil.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 404);
        }
    }

    /**
     * Store a newly created Change Product (Draft or Direct Post).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeChangeProduct(Request $request): JsonResponse
    {
        $input = $this->normalizeChangeProductInput($request->all());

        $request->merge($input);
        $request->validate([
            'doc_date'                => 'required|date',
            'doc_due_date'            => 'nullable|date',
            'comments'                => 'nullable|string',
            'shift'                   => 'nullable|string|max:50',
            'unit'                    => 'nullable|string|max:50',
            'addon_id'                => 'nullable|string|max:100',
            'user_id'                 => 'nullable|string|max:50',
            'items'                   => 'required|array|min:1',
            'items.*.old_item_code'   => 'required|string|max:50',
            'items.*.new_item_code'   => 'required|string|max:50',
            'items.*.quantity'        => 'required|numeric|min:0.0001',
            'items.*.from_whs_code'   => 'required|string|max:50',
            'items.*.to_whs_code'     => 'required|string|max:50',
        ]);

        $userId = $request->user()?->id;

        try {
            $cp = $this->productionService->createChangeProduct($input, $userId);

            // Auto post to SAP if requested
            if ($request->boolean('post_now') || $request->boolean('is_posted') || $request->input('status') === 'POST' || $request->input('status') === 'COMPLETE') {
                $postResult = $this->productionService->postChangeProductToSap($cp, $userId);
                return $this->successResponse($postResult, 'Transaksi Change Product berhasil disimpan dan diposting ke SAP.', 201);
            }

            return $this->successResponse($cp, 'Draft Change Product berhasil disimpan.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menyimpan Change Product: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update the specified Change Product in storage.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateChangeProduct(Request $request, int $id): JsonResponse
    {
        $input = $this->normalizeChangeProductInput($request->all());

        $request->merge($input);
        $request->validate([
            'doc_date'                => 'sometimes|required|date',
            'doc_due_date'            => 'nullable|date',
            'comments'                => 'nullable|string',
            'shift'                   => 'nullable|string|max:50',
            'unit'                    => 'nullable|string|max:50',
            'addon_id'                => 'nullable|string|max:100',
            'user_id'                 => 'nullable|string|max:50',
            'items'                   => 'sometimes|required|array|min:1',
            'items.*.old_item_code'   => 'required_with:items|string|max:50',
            'items.*.new_item_code'   => 'required_with:items|string|max:50',
            'items.*.quantity'        => 'required_with:items|numeric|min:0.0001',
            'items.*.from_whs_code'   => 'required_with:items|string|max:50',
            'items.*.to_whs_code'     => 'required_with:items|string|max:50',
        ]);

        $userId = $request->user()?->id;

        try {
            $cp = $this->productionService->updateChangeProduct($id, $input, $userId);

            // Auto post to SAP if requested
            if ($request->boolean('post_now') || $request->boolean('is_posted') || $request->input('status') === 'POST' || $request->input('status') === 'COMPLETE') {
                $postResult = $this->productionService->postChangeProductToSap($cp, $userId);
                return $this->successResponse($postResult, 'Transaksi Change Product berhasil diperbarui dan diposting ke SAP.');
            }

            return $this->successResponse($cp, 'Transaksi Change Product berhasil diperbarui.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memperbarui Change Product: ' . $e->getMessage(), [], 400);
        }
    }

    /**
     * Remove the specified Change Product from storage.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroyChangeProduct(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $this->productionService->deleteChangeProduct($id, $userId);
            return $this->successResponse(null, 'Transaksi Change Product berhasil dihapus.');
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal menghapus Change Product: ' . $e->getMessage(), [], 400);
        }
    }

    /**
     * Post a Change Product transaction to SAP B1 (/api/AddCP).
     *
     * @param Request $request
     * @param int|null $id
     * @return JsonResponse
     */
    public function postChangeProductSap(Request $request, ?int $id = null): JsonResponse
    {
        $userId = $request->user()?->id;
        $targetId = $id ?? $request->input('id') ?? $request->input('change_product_id');

        try {
            // Case 1: Post existing record by ID
            if ($targetId) {
                $result = $this->productionService->postChangeProductToSap((int)$targetId, $userId);
                return $this->successResponse($result, 'Transaksi Change Product berhasil diposting ke SAP.');
            }

            // Case 2: Direct payload creation & immediate post
            $input = $this->normalizeChangeProductInput($request->all());
            $request->merge($input);
            $request->validate([
                'doc_date'                => 'required|date',
                'items'                   => 'required|array|min:1',
                'items.*.old_item_code'   => 'required|string|max:50',
                'items.*.new_item_code'   => 'required|string|max:50',
                'items.*.quantity'        => 'required|numeric|min:0.0001',
                'items.*.from_whs_code'   => 'required|string|max:50',
                'items.*.to_whs_code'     => 'required|string|max:50',
            ]);

            $cp = $this->productionService->createChangeProduct($input, $userId);
            $result = $this->productionService->postChangeProductToSap($cp, $userId);

            return $this->successResponse($result, 'Transaksi Change Product berhasil disimpan dan diposting ke SAP.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memposting Change Product ke SAP: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Helper to normalize input parameters for Change Product (supports camelCase and snake_case).
     *
     * @param array $raw
     * @return array
     */
    protected function normalizeChangeProductInput(array $raw): array
    {
        $normalized = [];

        // Header mapping
        $normalized['doc_date'] = $raw['doc_date'] ?? $raw['docDate'] ?? $raw['DocDate'] ?? now()->toIso8601String();
        $normalized['doc_due_date'] = $raw['doc_due_date'] ?? $raw['docDueDate'] ?? $raw['DocDueDate'] ?? $normalized['doc_date'];
        $normalized['comments'] = $raw['comments'] ?? $raw['Comments'] ?? null;
        $normalized['shift'] = $raw['shift'] ?? $raw['Shift'] ?? null;
        $normalized['unit'] = $raw['unit'] ?? $raw['Unit'] ?? null;
        $normalized['addon_id'] = $raw['addon_id'] ?? $raw['addonId'] ?? $raw['AddonId'] ?? null;
        $normalized['user_id'] = isset($raw['user_id']) ? (string)$raw['user_id'] : (isset($raw['userId']) ? (string)$raw['userId'] : (isset($raw['UserId']) ? (string)$raw['UserId'] : null));

        // Details mapping (only if provided)
        if (isset($raw['items']) || isset($raw['lines']) || isset($raw['Lines'])) {
            $rawLines = $raw['items'] ?? $raw['lines'] ?? $raw['Lines'] ?? [];
            $items = [];
            if (is_array($rawLines)) {
                foreach ($rawLines as $line) {
                    $items[] = [
                        'old_item_code' => $line['old_item_code'] ?? $line['oldItemCode'] ?? $line['OldItemCode'] ?? '',
                        'new_item_code' => $line['new_item_code'] ?? $line['newItemCode'] ?? $line['NewItemCode'] ?? '',
                        'quantity'      => floatval($line['quantity'] ?? $line['Quantity'] ?? $line['qty'] ?? 0),
                        'from_whs_code' => $line['from_whs_code'] ?? $line['fromWhsCode'] ?? $line['FromWhsCode'] ?? '',
                        'to_whs_code'   => $line['to_whs_code'] ?? $line['toWhsCode'] ?? $line['ToWhsCode'] ?? '',
                        'ocr_code'      => $line['ocr_code'] ?? $line['ocrCode'] ?? $line['OcrCode'] ?? null,
                        'ocr_code2'     => $line['ocr_code2'] ?? $line['ocrCode2'] ?? $line['OcrCode2'] ?? null,
                        'ocr_code3'     => $line['ocr_code3'] ?? $line['ocrCode3'] ?? $line['OcrCode3'] ?? null,
                    ];
                }
            }
            $normalized['items'] = $items;
        }

        return $normalized;
    }
}

