<?php

namespace App\Modules\Claim\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Claim\Repositories\TrxClaimBalanceLedgerRepositoryInterface;
use App\Modules\Claim\Requests\BalanceAdjustmentRequest;
use App\Traits\ApiResponseFormatter;
use App\Traits\HasCustomerCodeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BalanceLedgerController extends Controller
{
    use ApiResponseFormatter, HasCustomerCodeResolver;

    /**
     * @var TrxClaimBalanceLedgerRepositoryInterface
     */
    protected TrxClaimBalanceLedgerRepositoryInterface $ledgerRepository;

    /**
     * BalanceLedgerController constructor.
     */
    public function __construct(TrxClaimBalanceLedgerRepositoryInterface $ledgerRepository)
    {
        $this->ledgerRepository = $ledgerRepository;
    }

    /**
     * Display a listing of the claim balance ledger transactions.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $customerCodes = $this->resolveCustomerCodes($request);

        if (empty($customerCodes) && !$request->user()->role) {
            return $this->successResponse([
                'current_page' => 1,
                'data' => [],
                'total' => 0
            ], 'Daftar riwayat transaksi saldo kosong.');
        }

        $filters = [
            'customer_codes' => $customerCodes,
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'type' => $request->get('type'),
        ];

        $perPage = (int)$request->get('per_page', 15);
        $ledger = $this->ledgerRepository->getLedgerPaginated($filters, $perPage);

        return $this->successResponse($ledger, 'Daftar riwayat transaksi saldo berhasil diambil.');
    }

    /**
     * Store a manual balance adjustment (Admin only).
     *
     * @param BalanceAdjustmentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeAdjustment(BalanceAdjustmentRequest $request)
    {
        $user = $request->user();
        
        // Authorization check for Admin
        $isAdmin = $user && $user->role && in_array(
            strtolower($user->role->name), 
            ['administrator', 'admin finance', 'admin sales', 'admin logistic']
        );

        if (!$isAdmin) {
            return $this->errorResponse(
                'Hanya admin yang dapat membuat koreksi saldo.', 
                null, 
                Response::HTTP_FORBIDDEN
            );
        }

        $customerCode = $request->get('customer_code');
        $adjustmentType = $request->get('adjustment_type');
        $amount = (float)$request->get('amount');
        $description = $request->get('description');
        $type = strtoupper($request->get('type'));
        $inputRefNumber = $request->get('ref_number');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Generate adjustment reference number if not supplied: ADJ-YYYYMMDD-XXX
        $prefix = 'ADJ-' . date('Ymd') . '-';
        
        $ledgerRecord = DB::transaction(function() use ($customerCode, $adjustmentType, $amount, $description, $prefix, $user, $type, $inputRefNumber, $startDate, $endDate) {
            $refNumber = $inputRefNumber;
            if (empty($refNumber)) {
                $lastAdj = DB::table('trx_claim_balance_ledger')
                    ->where('ref_number', 'like', $prefix . '%')
                    ->orderBy('ref_number', 'desc')
                    ->first();

                $num = 1;
                if ($lastAdj) {
                    $lastNum = intval(substr($lastAdj->ref_number, -3));
                    $num = $lastNum + 1;
                }
                $refNumber = $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
            }

            $debit = $adjustmentType === 'DEBIT' ? $amount : 0.00;
            $credit = $adjustmentType === 'CREDIT' ? $amount : 0.00;

            return $this->ledgerRepository->recordTransaction([
                'customer_code' => $customerCode,
                'ref_number' => $refNumber,
                'transaction_date' => now()->toDateString(),
                'type' => $type,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $description,
                'claim_start' => $startDate,
                'claim_end' => $endDate,
                'created_by' => $user->username ?? 'admin',
            ]);
        });

        return $this->successResponse(
            $ledgerRecord, 
            'Koreksi saldo berhasil dibuat.', 
            Response::HTTP_CREATED
        );
    }
}
