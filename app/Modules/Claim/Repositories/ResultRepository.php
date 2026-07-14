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
        return DB::transaction(function () use ($ids, $status, $claimType) {
            // 1. Get the target results before updating so we know their metadata (customer, batch, program)
            $targetResults = TrxProgramResult::whereIn('id', $ids)
                ->where('status', 'VALID_PROGRAM')
                ->with(['program', 'upload.batch'])
                ->get();

            // 2. Perform the verification update on the results
            TrxProgramResult::whereIn('id', $ids)->update(['is_verified' => $status]);

            // 3. Group by customer, batch_id, and program_id to update the ledger rows
            $groups = $targetResults->groupBy(function ($item) {
                $batchId = $item->upload?->batch_id ?? 0;
                $programId = $item->program_id ?? 0;
                return $item->customer_code . '|' . $batchId . '|' . $programId;
            });

            foreach ($groups as $key => $items) {
                list($customerCode, $batchId, $programId) = explode('|', $key);
                $batchId = $batchId > 0 ? (int)$batchId : null;
                $programId = $programId > 0 ? (int)$programId : null;

                $firstItem = $items->first();
                $program = $firstItem->program;
                $uploadBatch = $firstItem->upload?->batch;
                $batchNo = $uploadBatch?->batch_no;

                // 4. Calculate the cumulative sum of ALL verified results for this batch & program
                $totalVerifiedDiscount = 0.00;
                if ($batchId) {
                    $totalVerifiedDiscount = (float)TrxProgramResult::join('trx_program_upload', 'trx_program_result.upload_id', '=', 'trx_program_upload.id')
                        ->where('trx_program_upload.batch_id', $batchId)
                        ->where('trx_program_result.customer_code', $customerCode)
                        ->where('trx_program_result.status', 'VALID_PROGRAM')
                        ->where('trx_program_result.is_verified', true)
                        ->sum('trx_program_result.total_diskon');
                } else {
                    // Fallback for non-batch results
                    $totalVerifiedDiscount = (float)TrxProgramResult::where('customer_code', $customerCode)
                        ->where('program_id', $programId)
                        ->where('status', 'VALID_PROGRAM')
                        ->where('is_verified', true)
                        ->sum('total_diskon');
                }

                $ledgerData = [
                    'customer_code'      => $customerCode,
                    'ref_number'         => $batchNo ?: ($program ? $program->program_code : null),
                    'batch_id'           => $batchId,
                    'transaction_date'   => now()->toDateString(),
                    'type'               => 'CLAIM',
                    'debit'              => $totalVerifiedDiscount,
                    'credit'             => 0.00,
                    'claim_type'         => $claimType,
                    'claim_start'        => $program ? $program->start_date : null,
                    'claim_end'          => $program ? $program->end_date : null,
                    'description'        => "Klaim Program " . ($program ? $program->program_name : 'Klaim'),
                    'referenceable_id'   => $programId,
                    'referenceable_type' => $program ? \App\Models\MstProgram::class : null,
                ];

                if ($batchId) {
                    $this->ledgerRepository->updateOrRecordClaimByBatch($batchId, $ledgerData);
                } else {
                    $this->ledgerRepository->recordTransaction($ledgerData);
                }
            }

            return true;
        });
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
