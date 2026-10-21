<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LoadingRateType;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoadingRateTypeController extends Controller
{
    use ApiResponseFormatter;

    /**
     * Get list of loading rate calculation types (REGULAR, OVERTIME).
     */
    public function index(Request $request): JsonResponse
    {
        $query = LoadingRateType::query()->withCount('loadingRates');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->get('search'));
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $types = $query->orderBy('id', 'asc')->get();

        return $this->successResponse($types, 'Daftar jenis perhitungan tarif angkut berhasil diambil.');
    }

    /**
     * Store a new calculation type.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code'        => 'required|string|max:50',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'start_time'  => 'nullable|string|max:20',
            'end_time'    => 'nullable|string|max:20',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), $validator->errors(), 422);
        }

        $code = strtoupper(trim((string) $request->input('code')));

        $existing = LoadingRateType::where('code', $code)->first();
        if ($existing) {
            return $this->errorResponse("Jenis perhitungan dengan kode '{$code}' sudah ada.", [], 422);
        }

        $type = LoadingRateType::create([
            'code'        => $code,
            'name'        => trim((string) $request->input('name')),
            'description' => $request->input('description'),
            'start_time'  => $this->formatTimeInput($request->input('start_time')),
            'end_time'    => $this->formatTimeInput($request->input('end_time')),
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return $this->successResponse($type, 'Jenis perhitungan tarif angkut berhasil ditambahkan.', 201);
    }

    /**
     * Show specific calculation type.
     */
    public function show(int $id): JsonResponse
    {
        $type = LoadingRateType::withCount('loadingRates')->find($id);

        if (!$type) {
            return $this->errorResponse("Jenis perhitungan dengan ID #{$id} tidak ditemukan.", [], 404);
        }

        return $this->successResponse($type, 'Detail jenis perhitungan berhasil diambil.');
    }

    /**
     * Update calculation type.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $type = LoadingRateType::find($id);

        if (!$type) {
            return $this->errorResponse("Jenis perhitungan dengan ID #{$id} tidak ditemukan.", [], 404);
        }

        $validator = Validator::make($request->all(), [
            'code'        => 'nullable|string|max:50',
            'name'        => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'start_time'  => 'nullable|string|max:20',
            'end_time'    => 'nullable|string|max:20',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), $validator->errors(), 422);
        }

        if ($request->filled('code')) {
            $code = strtoupper(trim((string) $request->input('code')));
            $existing = LoadingRateType::where('code', $code)->where('id', '!=', $id)->first();
            if ($existing) {
                return $this->errorResponse("Jenis perhitungan dengan kode '{$code}' sudah ada.", [], 422);
            }
            $type->code = $code;
        }

        if ($request->filled('name')) {
            $type->name = trim((string) $request->input('name'));
        }

        if ($request->has('description')) {
            $type->description = $request->input('description');
        }

        if ($request->has('start_time')) {
            $type->start_time = $this->formatTimeInput($request->input('start_time'));
        }

        if ($request->has('end_time')) {
            $type->end_time = $this->formatTimeInput($request->input('end_time'));
        }

        if ($request->has('is_active')) {
            $type->is_active = $request->boolean('is_active');
        }

        $type->save();

        return $this->successResponse($type, 'Jenis perhitungan tarif angkut berhasil diperbarui.');
    }

    /**
     * Format time input (support HH:MM, HH.MM, HH:MM:SS) to standard HH:MM:SS.
     */
    protected function formatTimeInput(?string $time): ?string
    {
        if (empty($time)) {
            return null;
        }
        $clean = trim(str_replace('.', ':', $time));
        $parts = explode(':', $clean);
        if (count($parts) === 2) {
            return sprintf('%02d:%02d:00', (int) $parts[0], (int) $parts[1]);
        }
        if (count($parts) >= 3) {
            return sprintf('%02d:%02d:%02d', (int) $parts[0], (int) $parts[1], (int) $parts[2]);
        }
        return $clean;
    }

    /**
     * Delete calculation type.
     */
    public function destroy(int $id): JsonResponse
    {
        $type = LoadingRateType::withCount('loadingRates')->find($id);

        if (!$type) {
            return $this->errorResponse("Jenis perhitungan dengan ID #{$id} tidak ditemukan.", [], 404);
        }

        if ($type->loading_rates_count > 0) {
            return $this->errorResponse("Tidak dapat menghapus jenis perhitungan karena masih digunakan oleh {$type->loading_rates_count} data tarif angkut.", [], 400);
        }

        $type->delete();

        return $this->successResponse(null, 'Jenis perhitungan tarif angkut berhasil dihapus.');
    }
}
