<?php

namespace App\Modules\Claim\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Claim\Repositories\WithdrawRepositoryInterface;
use App\Modules\Claim\Repositories\ResultRepositoryInterface;
use App\Traits\ApiResponseFormatter;
use App\Traits\HasCustomerCodeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class WithdrawController extends Controller
{
    use ApiResponseFormatter, HasCustomerCodeResolver;

    /**
     * @var WithdrawRepositoryInterface
     */
    protected WithdrawRepositoryInterface $withdrawRepository;

    /**
     * @var ResultRepositoryInterface
     */
    protected ResultRepositoryInterface $resultRepository;

    /**
     * WithdrawController constructor.
     */
    public function __construct(
        WithdrawRepositoryInterface $withdrawRepository,
        ResultRepositoryInterface $resultRepository
    ) {
        $this->withdrawRepository = $withdrawRepository;
        $this->resultRepository = $resultRepository;
    }

    /**
     * Get list of withdrawals.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $filters = $request->only(['status']);

        $filters['customer_codes'] = $this->resolveCustomerCodes($request);

        $withdraws = $this->withdrawRepository->getWithdrawsPaginated($filters, (int)$request->get('per_page', 15));

        return $this->successResponse($withdraws, 'Daftar withdraw berhasil diambil.');
    }

    /**
     * Submit a new withdrawal request.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'lines'            => 'required|array|min:1',
            'lines.*.batch_id' => 'required|integer|exists:trx_program_upload_batch,id',
            'lines.*.amount'   => 'required|numeric|min:0.01',
        ]);

        $user = $request->user();
        if (!$user || !$user->code_customer) {
            return $this->errorResponse('Hanya distributor yang dapat mengajukan withdraw.', null, Response::HTTP_FORBIDDEN);
        }

        $customerCode = $user->code_customer;
        $amount = (float)$request->get('amount');
        $lines = $request->get('lines');

        // Verify that sum of lines matches total amount
        $sumLinesAmount = 0.00;
        foreach ($lines as $line) {
            $sumLinesAmount += (float)$line['amount'];
        }

        if (abs($amount - $sumLinesAmount) > 0.01) {
            return $this->errorResponse('Total nominal detail batch tidak sama dengan total nominal withdraw.', null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify available balance for each batch
        foreach ($lines as $line) {
            $batchId = (int)$line['batch_id'];
            $lineAmount = (float)$line['amount'];

            // 1. Get the total verified discount for this batch and customer
            $totalVerifiedDiskon = (float) DB::table('trx_program_result as r')
                ->join('trx_program_upload as u', 'r.upload_id', '=', 'u.id')
                ->where('u.batch_id', $batchId)
                ->where('r.customer_code', $customerCode)
                ->where('r.status', 'VALID_PROGRAM')
                ->where('r.is_verified', true)
                ->sum('r.total_diskon');

            // 2. Get total credit (deducted/withdrawn/usages) for this batch in ledger (approved/settled)
            $totalDeducted = (float) DB::table('trx_claim_balance_ledger as l')
                ->leftJoin('trx_program_withdraw as w', function($join) {
                    $join->on('l.referenceable_id', '=', 'w.id')
                         ->where('l.referenceable_type', '=', 'App\Models\TrxProgramWithdraw');
                })
                ->where('l.customer_code', $customerCode)
                ->where('l.batch_id', $batchId)
                ->where(function($query) {
                    $query->whereNull('w.status')
                          ->orWhere('w.status', '=', 'APPROVED');
                })
                ->sum('l.credit');

            // 3. Get total pending withdrawals for this batch
            $pendingWithdraws = (float) DB::table('trx_claim_balance_ledger as l')
                ->join('trx_program_withdraw as w', function($join) {
                    $join->on('l.referenceable_id', '=', 'w.id')
                         ->where('l.referenceable_type', '=', 'App\Models\TrxProgramWithdraw');
                })
                ->where('l.customer_code', $customerCode)
                ->where('l.batch_id', $batchId)
                ->where('w.status', '=', 'PENDING')
                ->sum('l.credit');

            $availableToWithdraw = $totalVerifiedDiskon - $totalDeducted - $pendingWithdraws;

            if ($lineAmount > $availableToWithdraw) {
                $batchNo = DB::table('trx_program_upload_batch')->where('id', $batchId)->value('batch_no') ?: $batchId;
                return $this->errorResponse("Saldo reward batch {$batchNo} tidak mencukupi. Saldo yang dapat ditarik: Rp " . number_format($availableToWithdraw, 0, ',', '.'), null, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Generate withdraw number: WD-YYYYMMDD-XXX
        $withdraw = DB::transaction(function() use ($customerCode, $amount, $lines, $user) {
            $dateStr = date('Ymd');
            $prefix = "WD-{$dateStr}-";
            
            $lastWd = DB::table('trx_program_withdraw')
                ->where('withdraw_no', 'like', $prefix . '%')
                ->orderBy('withdraw_no', 'desc')
                ->lockForUpdate()
                ->first();

            $num = 1;
            if ($lastWd) {
                $lastNum = intval(substr($lastWd->withdraw_no, -3));
                $num = $lastNum + 1;
            }
            $withdrawNo = $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);

            // Backward compatibility for single batch id
            $firstBatchId = isset($lines[0]['batch_id']) ? (int)$lines[0]['batch_id'] : null;

            return $this->withdrawRepository->createWithdraw([
                'withdraw_no' => $withdrawNo,
                'customer_code' => $customerCode,
                'batch_id' => $firstBatchId,
                'lines' => $lines,
                'amount' => $amount,
                'status' => 'PENDING',
                'created_by' => $user->username ?? 'distributor',
            ]);
        });

        return $this->successResponse($withdraw, 'Pengajuan withdraw berhasil dibuat.', Response::HTTP_CREATED);
    }

    /**
     * Update status of a withdrawal.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:APPROVED,REJECTED,PENDING',
            'transfer_date' => 'nullable|date',
        ]);

        $withdraw = $this->withdrawRepository->updateStatus(
            (int)$id, 
            $request->get('status'), 
            $request->get('transfer_date')
        );

        return $this->successResponse($withdraw, 'Status withdraw berhasil diperbarui.');
    }
}
