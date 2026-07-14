<?php

namespace App\Modules\Claim\Repositories;

use App\Models\TrxProgramResult;
use Illuminate\Support\Facades\DB;

class ResultRepository implements ResultRepositoryInterface
{
    /**
     * @var TrxClaimBalanceLedgerRepositoryInterface
     */
    protected TrxClaimBalanceLedgerRepositoryInterface $ledgerRepository;

    /**
     * ResultRepository constructor.
     */
    public function __construct(TrxClaimBalanceLedgerRepositoryInterface $ledgerRepository)
    {
        $this->ledgerRepository = $ledgerRepository;
    }
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
    public function verifyResults(array $ids, bool $status, ?string $claimType = null)
    {
        if ($status) {
            // Find all valid program results that are not yet verified.
            $unverifiedResults = TrxProgramResult::whereIn('id', $ids)
                ->where('is_verified', false)
                ->where('status', 'VALID_PROGRAM')
                ->with(['program', 'upload.batch'])
                ->get();

            // Group by customer_code and program_id
            $groups = $unverifiedResults->groupBy(function($item) {
                return $item->customer_code . '|' . ($item->program_id ?? 0);
            });

            foreach ($groups as $key => $items) {
                list($customerCode, $programId) = explode('|', $key);
                $programId = $programId > 0 ? (int)$programId : null;

                $totalDiscount = $items->sum('total_diskon');
                $firstItem = $items->first();
                $program = $firstItem->program;
                $uploadBatch = $firstItem->upload?->batch;
                $batchId = $firstItem->upload?->batch_id;
                $batchNo = $uploadBatch?->batch_no;

                $ledgerData = [
                    'customer_code'      => $customerCode,
                    'ref_number'         => $batchNo ?: ($program ? $program->program_code : null),
                    'batch_id'           => $batchId,
                    'transaction_date'   => now()->toDateString(),
                    'type'               => 'CLAIM',
                    'debit'              => $totalDiscount,
                    'credit'             => 0.00,
                    'claim_type'         => $claimType,
                    'claim_start'        => $program ? $program->start_date : null,
                    'claim_end'          => $program ? $program->end_date : null,
                    'description'        => "Klaim Program " . ($program ? $program->program_name : 'Klaim'),
                    'referenceable_id'   => $programId,
                    'referenceable_type' => $program ? \App\Models\MstProgram::class : null,
                ];

                // If we have a batch_id, update the existing pending ledger row
                // (created when distributor uploaded the file) instead of inserting a new one.
                if ($batchId) {
                    $this->ledgerRepository->updateOrRecordClaimByBatch($batchId, $ledgerData);
                } else {
                    $this->ledgerRepository->recordTransaction($ledgerData);
                }
            }
        }

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

        $queryLedger = DB::table('trx_claim_balance_ledger');
        if (!empty($codes)) {
            $queryLedger->whereIn('customer_code', $codes);
        }

        // Sum of all CLAIM type debits
        $totalVerified = (float) (clone $queryLedger)->where('type', 'CLAIM')->sum('debit');
        
        // Sum of all WITHDRAW type credits
        $totalWithdrawn = (float) (clone $queryLedger)->where('type', 'WITHDRAW')->sum('credit');

        // Total claimed (which includes unverified claims)
        $queryResult = DB::table('trx_program_result')
            ->where('status', 'VALID_PROGRAM');
        if (!empty($codes)) {
            $queryResult->whereIn('customer_code', $codes);
        }
        $totalClaimed = (float)$queryResult->sum('total_diskon');

        // Current available balance: sum(debit) - sum(credit) across all ledger transactions
        $availableBalance = (float)(clone $queryLedger)->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal') ?? 0.00;

        return [
            'total_claimed' => $totalClaimed,
            'total_verified' => $totalVerified,
            'total_withdrawn' => $totalWithdrawn,
            'available_balance' => $availableBalance,
        ];
    }
}
