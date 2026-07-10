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
        $query = TrxProgramResult::with('upload');

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
            $codes = is_array($filters['customer_code'])
                ? $filters['customer_code']
                : array_filter(array_map('trim', explode(',', $filters['customer_code'])));
            $query->whereIn('customer_code', $codes);
        }

        if (!empty($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }

        return $query->orderBy('id', 'asc')->paginate($perPage);
    }

    /**
     * Get all results without pagination.
     */
    public function getResults(array $filters)
    {
        $query = TrxProgramResult::with('upload');

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
            $codes = is_array($filters['customer_code'])
                ? $filters['customer_code']
                : array_filter(array_map('trim', explode(',', $filters['customer_code'])));
            $query->whereIn('customer_code', $codes);
        }

        if (!empty($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }

        return $query->orderBy('id', 'asc')->get();
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

    /**
     * Bulk verify result records.
     */
    public function verifyResults(array $ids, bool $status)
    {
        return TrxProgramResult::whereIn('id', $ids)->update(['is_verified' => $status]);
    }

    /**
     * Get reward summary statistics (claimed, verified, withdrawn, balance).
     */
    public function getRewardSummary($customerCodes = null)
    {
        $codes = [];
        if ($customerCodes) {
            $codes = is_array($customerCodes) ? $customerCodes : [$customerCodes];
        }

        $queryResult = DB::table('trx_program_result')
            ->where('status', 'VALID_PROGRAM');

        if (!empty($codes)) {
            $queryResult->whereIn('customer_code', $codes);
        }

        $totalClaimed = $queryResult->sum('total_diskon');
        
        $queryVerified = clone $queryResult;
        $totalVerified = $queryVerified->where('is_verified', true)->sum('total_diskon');

        $queryWithdraw = DB::table('trx_program_withdraw')
            ->whereNull('deleted_at')
            ->whereIn('status', ['PENDING', 'APPROVED', 'COMPLETED']);

        if (!empty($codes)) {
            $queryWithdraw->whereIn('customer_code', $codes);
        }

        $totalWithdrawn = $queryWithdraw->sum('amount');

        return [
            'total_claimed' => (float)$totalClaimed,
            'total_verified' => (float)$totalVerified,
            'total_withdrawn' => (float)$totalWithdrawn,
            'available_balance' => (float)($totalVerified - $totalWithdrawn),
        ];
    }
}
