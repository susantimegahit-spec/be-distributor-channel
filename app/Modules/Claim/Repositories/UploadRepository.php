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
    public function getBatchesPaginated(array $customerCodes = [], int $perPage = 15)
    {
        $totalRowsQuery = DB::table('trx_program_upload')
            ->whereColumn('batch_id', 'b.id');

        $validRowsQuery = DB::table('trx_program_result as r')
            ->join('trx_program_upload as u', 'r.upload_id', '=', 'u.id')
            ->whereColumn('u.batch_id', 'b.id')
            ->where('r.status', 'VALID_PROGRAM');

        $invalidRowsQuery = DB::table('trx_program_result as r')
            ->join('trx_program_upload as u', 'r.upload_id', '=', 'u.id')
            ->whereColumn('u.batch_id', 'b.id')
            ->where('r.status', '!=', 'VALID_PROGRAM');

        $totalDiskonQuery = DB::table('trx_program_result as r')
            ->join('trx_program_upload as u', 'r.upload_id', '=', 'u.id')
            ->whereColumn('u.batch_id', 'b.id');

        if (!empty($customerCodes)) {
            $totalRowsQuery->whereIn('customer_code', $customerCodes);
            $validRowsQuery->whereIn('r.customer_code', $customerCodes);
            $invalidRowsQuery->whereIn('r.customer_code', $customerCodes);
            $totalDiskonQuery->whereIn('r.customer_code', $customerCodes);
        }

        $query = DB::table('trx_program_upload_batch as b')
            ->leftJoin('trx_program_upload as u', function ($join) {
                $join->on('u.batch_id', '=', 'b.id')
                     ->whereRaw('u.id = (SELECT MIN(id) FROM trx_program_upload WHERE batch_id = b.id)');
            })
            ->leftJoin('distributors as d', 'd.code_customer', '=', 'u.customer_code')
            ->select([
                'b.id as batch_id',
                'b.id',
                'b.batch_no',
                'b.file_name',
                'b.uploaded_by',
                'b.uploaded_at',
                'd.code_customer as code_customer',
                'd.name as customer_name',
                'd.name as name_customer',
                'd.depo as depo'
            ])
            ->selectSub($totalRowsQuery->selectRaw('COUNT(*)'), 'total_rows')
            ->selectSub($validRowsQuery->selectRaw('COUNT(*)'), 'valid_rows')
            ->selectSub($invalidRowsQuery->selectRaw('COUNT(*)'), 'invalid_rows')
            ->selectSub($totalDiskonQuery->selectRaw('COALESCE(SUM(r.total_diskon), 0)'), 'total_diskon')
            ->orderBy('b.uploaded_at', 'desc');

        if (!empty($customerCodes)) {
            $query->whereExists(function ($q) use ($customerCodes) {
                $q->select(DB::raw(1))
                    ->from('trx_program_upload')
                    ->whereColumn('batch_id', 'b.id')
                    ->whereIn('customer_code', $customerCodes);
            });
        }

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function ($item) {
            $item->batch_id = (int)$item->batch_id;
            $item->total_rows = (int)$item->total_rows;
            $item->valid_rows = (int)$item->valid_rows;
            $item->invalid_rows = (int)$item->invalid_rows;
            $item->total_diskon = (float)$item->total_diskon;
            return $item;
        });

        return $paginator;
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
