<?php

namespace App\Modules\Distributor\Repositories;

use App\Models\OcrCode;
use Illuminate\Database\Eloquent\Collection;

class OcrCodeRepository implements OcrCodeRepositoryInterface
{
    /**
     * Get all OCR Codes with filters.
     *
     * @param  array  $filters
     * @return Collection<int, OcrCode>
     */
    public function getAll(array $filters = []): Collection
    {
        $query = OcrCode::query();

        if (!empty($filters['distribution_target'])) {
            $query->where('distribution_target', $filters['distribution_target']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('ocr_code', 'ilike', "%{$search}%")
                  ->orWhere('ocr_name', 'ilike', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Create or update an OCR Code by ocr_code and distribution_target.
     *
     * @param  array  $data
     * @return OcrCode
     */
    public function upsert(array $data): OcrCode
    {
        return OcrCode::updateOrCreate(
            [
                'ocr_code' => $data['ocr_code'],
                'distribution_target' => $data['distribution_target'],
            ],
            [
                'ocr_name' => $data['ocr_name'],
                'status' => $data['status'] ?? 1,
            ]
        );
    }
}
