<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\LoadingRate;
use App\Models\LoadingRateType;
use App\Modules\Ekspedisi\Services\LoadingRateUploadService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoadingRateController extends Controller
{
    use ApiResponseFormatter;

    /**
     * Get paginated or filtered list of master loading rates (ongkos angkut kuli).
     */
    public function index(Request $request): JsonResponse
    {
        $query = LoadingRate::with(['calculationType', 'creator:id,name', 'updater:id,name']);

        if ($request->filled('item_code')) {
            $query->where('item_code', trim((string) $request->get('item_code')));
        }

        if ($request->filled('rate_type')) {
            $typeCode = strtoupper(trim((string) $request->get('rate_type')));
            $query->where('rate_type', $typeCode);
        }

        if ($request->filled('rate_type_id')) {
            $query->where('rate_type_id', (int) $request->get('rate_type_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper(trim((string) $request->get('status'))));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->get('search'));
            $query->where(function ($q) use ($search) {
                $q->where('item_code', 'ilike', "%{$search}%")
                  ->orWhere('item_name', 'ilike', "%{$search}%")
                  ->orWhere('remarks', 'ilike', "%{$search}%");
            });
        }

        if ($request->get('per_page') === 'all') {
            $data = $query->orderBy('item_code', 'asc')->orderBy('rate_type', 'asc')->get();
            return $this->successResponse($data, 'Seluruh master data tarif angkut berhasil diambil.');
        }

        $perPage = (int) $request->get('per_page', 15);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse($paginated, 'Daftar master tarif angkut berhasil diambil.');
    }

    /**
     * Store a new loading rate.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'item_code'    => 'required|string|max:50',
            'item_name'    => 'nullable|string|max:255',
            'rate_type'    => 'nullable|string|max:50',
            'rate_type_id' => 'nullable|integer',
            'rate'         => 'required|numeric|min:0',
            'uom'          => 'nullable|string|max:20',
            'status'       => 'nullable|string|in:ACTIVE,INACTIVE',
            'remarks'      => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), $validator->errors(), 422);
        }

        $itemCode = trim((string) $request->input('item_code'));

        // Resolve Rate Type
        $rateTypeModel = null;
        if ($request->filled('rate_type_id')) {
            $rateTypeModel = LoadingRateType::find((int) $request->input('rate_type_id'));
        } elseif ($request->filled('rate_type')) {
            $rawType = strtoupper(trim((string) $request->input('rate_type')));
            $normalized = in_array($rawType, ['OVERTIME', 'LEMBUR', 'OT'], true) ? 'OVERTIME' : 'REGULAR';
            $rateTypeModel = LoadingRateType::where('code', $normalized)->first();
        }

        if (!$rateTypeModel) {
            $rateTypeModel = LoadingRateType::firstOrCreate(
                ['code' => 'REGULAR'],
                ['name' => 'Regular', 'description' => 'Ongkos angkut jam kerja reguler', 'is_active' => true]
            );
        }

        // Check duplicate
        $existing = LoadingRate::where('item_code', $itemCode)
            ->where('rate_type_id', $rateTypeModel->id)
            ->first();

        if ($existing) {
            return $this->errorResponse("Tarif angkut untuk item '{$itemCode}' dengan tipe '{$rateTypeModel->code}' sudah ada. Gunakan menu update jika ingin mengubah biaya.", [], 422);
        }

        // Resolve item_name and uom if empty
        $itemName = trim((string) $request->input('item_name', ''));
        $uom = strtoupper(trim((string) $request->input('uom', '')));

        if ($itemName === '' || $uom === '') {
            $item = Item::where('item_code', $itemCode)->first();
            if ($item) {
                if ($itemName === '') {
                    $itemName = (string) $item->item_name;
                }
                if ($uom === '') {
                    $uom = (string) ($item->sal_unit_msr ?? $item->invntry_uom ?? 'PCS');
                }
            }
        }

        $loadingRate = LoadingRate::create([
            'item_code'    => $itemCode,
            'item_name'    => $itemName ?: $itemCode,
            'rate_type_id' => $rateTypeModel->id,
            'rate_type'    => $rateTypeModel->code,
            'uom'          => $uom ?: 'PCS',
            'rate'         => (float) $request->input('rate'),
            'status'       => $request->input('status', 'ACTIVE'),
            'remarks'      => $request->input('remarks'),
            'created_by'   => auth()->id(),
            'updated_by'   => auth()->id(),
        ]);

        return $this->successResponse(
            $loadingRate->load(['calculationType', 'creator:id,name']),
            'Tarif angkut kuli berhasil ditambahkan.',
            201
        );
    }

    /**
     * Show specific loading rate details.
     */
    public function show(int $id): JsonResponse
    {
        $rate = LoadingRate::with(['calculationType', 'item', 'creator:id,name', 'updater:id,name'])->find($id);

        if (!$rate) {
            return $this->errorResponse("Tarif angkut dengan ID #{$id} tidak ditemukan.", [], 404);
        }

        return $this->successResponse($rate, 'Detail tarif angkut kuli berhasil diambil.');
    }

    /**
     * Update an existing loading rate.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $rate = LoadingRate::find($id);

        if (!$rate) {
            return $this->errorResponse("Tarif angkut dengan ID #{$id} tidak ditemukan.", [], 404);
        }

        $validator = Validator::make($request->all(), [
            'item_code'    => 'nullable|string|max:50',
            'item_name'    => 'nullable|string|max:255',
            'rate_type'    => 'nullable|string|max:50',
            'rate_type_id' => 'nullable|integer',
            'rate'         => 'nullable|numeric|min:0',
            'uom'          => 'nullable|string|max:20',
            'status'       => 'nullable|string|in:ACTIVE,INACTIVE',
            'remarks'      => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), $validator->errors(), 422);
        }

        $targetItemCode = $request->filled('item_code') ? trim((string) $request->input('item_code')) : $rate->item_code;
        $targetTypeId = $rate->rate_type_id;
        $targetTypeCode = $rate->rate_type;

        if ($request->filled('rate_type_id')) {
            $typeModel = LoadingRateType::find((int) $request->input('rate_type_id'));
            if ($typeModel) {
                $targetTypeId = $typeModel->id;
                $targetTypeCode = $typeModel->code;
            }
        } elseif ($request->filled('rate_type')) {
            $rawType = strtoupper(trim((string) $request->input('rate_type')));
            $normalized = in_array($rawType, ['OVERTIME', 'LEMBUR', 'OT'], true) ? 'OVERTIME' : 'REGULAR';
            $typeModel = LoadingRateType::where('code', $normalized)->first();
            if ($typeModel) {
                $targetTypeId = $typeModel->id;
                $targetTypeCode = $typeModel->code;
            }
        }

        // Verify duplicate if item_code or type changed
        if ($targetItemCode !== $rate->item_code || $targetTypeId !== $rate->rate_type_id) {
            $duplicate = LoadingRate::where('item_code', $targetItemCode)
                ->where('rate_type_id', $targetTypeId)
                ->where('id', '!=', $id)
                ->first();

            if ($duplicate) {
                return $this->errorResponse("Tarif angkut untuk item '{$targetItemCode}' dengan tipe '{$targetTypeCode}' sudah ada pada data lain.", [], 422);
            }
        }

        $rate->item_code = $targetItemCode;
        $rate->rate_type_id = $targetTypeId;
        $rate->rate_type = $targetTypeCode;

        if ($request->filled('item_name')) {
            $rate->item_name = trim((string) $request->input('item_name'));
        }

        if ($request->filled('rate')) {
            $rate->rate = (float) $request->input('rate');
        }

        if ($request->filled('uom')) {
            $rate->uom = strtoupper(trim((string) $request->input('uom')));
        }

        if ($request->filled('status')) {
            $rate->status = $request->input('status');
        }

        if ($request->has('remarks')) {
            $rate->remarks = $request->input('remarks');
        }

        $rate->updated_by = auth()->id();
        $rate->save();

        return $this->successResponse(
            $rate->fresh(['calculationType', 'updater:id,name']),
            'Tarif angkut kuli berhasil diperbarui.'
        );
    }

    /**
     * Delete a loading rate.
     */
    public function destroy(int $id): JsonResponse
    {
        $rate = LoadingRate::find($id);

        if (!$rate) {
            return $this->errorResponse("Tarif angkut dengan ID #{$id} tidak ditemukan.", [], 404);
        }

        $rate->delete();

        return $this->successResponse(null, 'Tarif angkut kuli berhasil dihapus.');
    }

    /**
     * Upload master loading rates via Excel/CSV file.
     */
    public function upload(Request $request, LoadingRateUploadService $uploadService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), $validator->errors(), 422);
        }

        try {
            $result = $uploadService->uploadRates($request->file('file'), auth()->id());
            return $this->successResponse($result, 'File tarif angkut kuli berhasil diupload dan diproses.');
        } catch (\Throwable $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int) $e->getCode() : 500;
            return $this->errorResponse($e->getMessage(), [], $statusCode);
        }
    }

    /**
     * Download Excel template for loading rates upload.
     */
    public function template(LoadingRateUploadService $uploadService): StreamedResponse
    {
        return $uploadService->downloadTemplate();
    }
}
