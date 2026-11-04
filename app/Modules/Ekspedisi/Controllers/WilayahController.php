<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Regency;
use App\Models\District;
use App\Models\Village;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WilayahController extends Controller
{
    use ApiResponseFormatter;

    /**
     * Get list of all provinces.
     *
     * @return JsonResponse
     */
    public function getProvinces(): JsonResponse
    {
        $provinces = Province::orderBy('name')->get();
        return $this->successResponse($provinces, 'Daftar provinsi berhasil diambil.');
    }

    /**
     * Get list of regencies, optionally filtered by province_id.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getRegencies(Request $request): JsonResponse
    {
        $query = Regency::query();

        if ($request->has('province_id')) {
            $query->where('province_id', $request->get('province_id'));
        }

        $regencies = $query->orderBy('name')->get();
        return $this->successResponse($regencies, 'Daftar kabupaten/kota berhasil diambil.');
    }

    /**
     * Get list of districts, optionally filtered by regency_id.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDistricts(Request $request): JsonResponse
    {
        $query = District::query();

        if ($request->has('regency_id')) {
            $query->where('regency_id', $request->get('regency_id'));
        }

        $districts = $query->orderBy('name')->get();
        return $this->successResponse($districts, 'Daftar kecamatan berhasil diambil.');
    }

    /**
     * Get list of villages, optionally filtered by district_id.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getVillages(Request $request): JsonResponse
    {
        $query = Village::query();

        if ($request->has('district_id')) {
            $query->where('district_id', $request->get('district_id'));
        }

        $villages = $query->orderBy('name')->get();
        return $this->successResponse($villages, 'Daftar desa/kelurahan berhasil diambil.');
    }
}
