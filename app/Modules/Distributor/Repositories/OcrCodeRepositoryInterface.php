<?php

namespace App\Modules\Distributor\Repositories;

use App\Models\OcrCode;
use Illuminate\Database\Eloquent\Collection;

interface OcrCodeRepositoryInterface
{
    /**
     * Get all OCR Codes with filters.
     *
     * @param  array  $filters
     * @return Collection<int, OcrCode>
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Create or update an OCR Code by ocr_code and distribution_target.
     *
     * @param  array  $data
     * @return OcrCode
     */
    public function upsert(array $data): OcrCode;
}
