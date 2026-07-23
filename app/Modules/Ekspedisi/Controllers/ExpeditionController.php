<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Expedition;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpeditionController extends Controller
{
    use ApiResponseFormatter;

    /**
     * Get paginated or filtered list of expeditions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Expedition::query();

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('expedition_code', 'like', "%{$search}%")
                  ->orWhere('expedition_name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('pic_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $perPage = $request->get('per_page', 15);
        $expeditions = $query->orderBy('expedition_name')->paginate($perPage);

        return $this->successResponse($expeditions, 'Daftar master ekspedisi berhasil diambil.');
    }

    /**
     * Store a new expedition.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'expedition_code' => 'required|string|max:50|unique:pgsql_ekspedisi.ekspedisi.expeditions,expedition_code',
            'expedition_name' => 'required|string|max:150',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'pic_name' => 'nullable|string|max:100',
            'pic_phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'npwp' => 'nullable|string|max:50',
            'vehicle_type' => 'nullable|string|max:50',
            'transport_mode' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $data = $validator->validated();
        $data['created_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'ACTIVE';

        $expedition = Expedition::create($data);

        return $this->successResponse($expedition, 'Master ekspedisi berhasil ditambahkan.', 201);
    }

    /**
     * Show detailed expedition.
     */
    public function show(int $id): JsonResponse
    {
        $expedition = Expedition::with(['rates', 'creator', 'updater'])->find($id);

        if (!$expedition) {
            return $this->errorResponse('Data ekspedisi tidak ditemukan.', [], 404);
        }

        return $this->successResponse($expedition, 'Detail master ekspedisi berhasil diambil.');
    }

    /**
     * Update an expedition.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $expedition = Expedition::find($id);

        if (!$expedition) {
            return $this->errorResponse('Data ekspedisi tidak ditemukan.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'expedition_code' => 'sometimes|required|string|max:50|unique:pgsql_ekspedisi.ekspedisi.expeditions,expedition_code,' . $id,
            'expedition_name' => 'sometimes|required|string|max:150',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'pic_name' => 'nullable|string|max:100',
            'pic_phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'npwp' => 'nullable|string|max:50',
            'vehicle_type' => 'nullable|string|max:50',
            'transport_mode' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $data = $validator->validated();
        $data['updated_by'] = auth()->id();

        $expedition->update($data);

        return $this->successResponse($expedition, 'Master ekspedisi berhasil diperbarui.');
    }

    /**
     * Delete an expedition.
     */
    public function destroy(int $id): JsonResponse
    {
        $expedition = Expedition::find($id);

        if (!$expedition) {
            return $this->errorResponse('Data ekspedisi tidak ditemukan.', [], 404);
        }

        $expedition->delete();

        return $this->successResponse(null, 'Master ekspedisi berhasil dihapus.');
    }
}
