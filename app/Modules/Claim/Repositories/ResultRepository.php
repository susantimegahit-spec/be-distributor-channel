<?php

namespace App\Modules\Claim\Repositories;

use App\Models\TrxProgramResult;
use Illuminate\Support\Facades\DB;

class ResultRepository implements ResultRepositoryInterface
{
    /**
     * Bulk insert calculation results.
     */
    public function insertResults(array $results)
    {
        return TrxProgramResult::insert($results);
    }

    /**
     * Get paginated results with optional filters.
     */
    public function paginateResults(array $filters, int $perPage = 15)
    {
        $query = TrxProgramResult::query();

        if (!empty($filters['batch_id'])) {
            $batchId = $filters['batch_id'];
            $query->whereIn('upload_id', function ($q) use ($batchId) {
                $q->select('id')
                  ->from('trx_program_upload')
                  ->where('batch_id', $batchId);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['customer_code'])) {
            $query->where('customer_code', $filters['customer_code']);
        }

        if (!empty($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }

        return $query->orderBy('id', 'asc')->paginate($perPage);
    }

    /**
     * Get overall summary statistics for dashboard.
     */
    public function getDashboardSummary()
    {
        $totalProgram = DB::table('mst_program')->whereNull('deleted_at')->count();
        $totalBatch = DB::table('trx_program_upload_batch')->count();
        $totalValidRows = DB::table('trx_program_result')->where('status', 'VALID_PROGRAM')->count();
        $totalDiskon = DB::table('trx_program_result')->where('status', 'VALID_PROGRAM')->sum('total_diskon');

        return [
            'total_program' => $totalProgram,
            'total_batch' => $totalBatch,
            'total_valid_rows' => $totalValidRows,
            'total_diskon' => (float)$totalDiskon,
        ];
    }
}
