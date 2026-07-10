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
            'amount' => 'required|numeric|min:0.01',
        ]);

        $user = $request->user();
        if (!$user || !$user->code_customer) {
            return $this->errorResponse('Hanya distributor yang dapat mengajukan withdraw.', null, Response::HTTP_FORBIDDEN);
        }

        $customerCode = $user->code_customer;
        $amount = (float)$request->get('amount');

        // Check available balance
        $summary = $this->resultRepository->getRewardSummary($customerCode);
        $balance = $summary['available_balance'];

        if ($amount > $balance) {
            return $this->errorResponse('Saldo reward tidak mencukupi untuk melakukan penarikan. Saldo tersedia: Rp ' . number_format($balance, 0, ',', '.'), null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Generate withdraw number: WD-YYYYMMDD-XXX
        $withdraw = DB::transaction(function() use ($customerCode, $amount, $user) {
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

            return $this->withdrawRepository->createWithdraw([
                'withdraw_no' => $withdrawNo,
                'customer_code' => $customerCode,
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
