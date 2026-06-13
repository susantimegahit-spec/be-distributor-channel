<?php

namespace App\Modules\Claim\Repositories;

use App\Models\TrxProgramUploadBatch;
use App\Models\TrxProgramUpload;
use Illuminate\Support\Facades\DB;

class UploadRepository implements UploadRepositoryInterface
{
    /**
     * Create a new upload batch.
     */
    public function createBatch(array $data)
    {
        return TrxProgramUploadBatch::create([
            'batch_no' => $data['batch_no'],
            'file_name' => $data['file_name'],
            'uploaded_by' => $data['uploaded_by'] ?? null,
        ]);
    }

    /**
     * Bulk insert parsed upload rows.
     */
    public function insertUploadRows(array $rows)
    {
        return TrxProgramUpload::insert($rows);
    }

    /**
     * Get paginated list of batches.
     */
    public function getBatchesPaginated(int $perPage = 15)
    {
        return TrxProgramUploadBatch::orderBy('uploaded_at', 'desc')->paginate($perPage);
    }

    /**
     * Find a batch by ID with calculated summary stats.
     */
    public function findBatchWithSummary(int $id)
    {
        $batch = DB::table('trx_program_upload_batch')->where('id', $id)->first();
        if (!$batch) {
            return null;
        }

        $totalRows = DB::table('trx_program_upload')->where('batch_id', $id)->count();

        $validRows = DB::table('trx_program_result')
            ->join('trx_program_upload', 'trx_program_result.upload_id', '=', 'trx_program_upload.id')
            ->where('trx_program_upload.batch_id', $id)
            ->where('trx_program_result.status', 'VALID_PROGRAM')
            ->count();

        $invalidRows = DB::table('trx_program_result')
            ->join('trx_program_upload', 'trx_program_result.upload_id', '=', 'trx_program_upload.id')
            ->where('trx_program_upload.batch_id', $id)
            ->where('trx_program_result.status', '!=', 'VALID_PROGRAM')
            ->count();

        $totalDiskon = DB::table('trx_program_result')
            ->join('trx_program_upload', 'trx_program_result.upload_id', '=', 'trx_program_upload.id')
            ->where('trx_program_upload.batch_id', $id)
            ->sum('trx_program_result.total_diskon');

        return [
            'batch_id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'file_name' => $batch->file_name,
            'uploaded_by' => $batch->uploaded_by,
            'uploaded_at' => $batch->uploaded_at,
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'total_diskon' => (float)$totalDiskon,
        ];
    }
}
