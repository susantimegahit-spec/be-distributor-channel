<?php

namespace App\Modules\Claim\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Claim\Repositories\TrxClaimBalanceLedgerRepositoryInterface;
use App\Modules\Claim\Requests\BalanceAdjustmentRequest;
use App\Modules\Claim\Services\UploadService;
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
     * @var UploadService
     */
    protected UploadService $uploadService;

    /**
     * BalanceLedgerController constructor.
     */
    public function __construct(
        TrxClaimBalanceLedgerRepositoryInterface $ledgerRepository,
        UploadService $uploadService
    ) {
        $this->ledgerRepository = $ledgerRepository;
        $this->uploadService = $uploadService;
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
     * Store a manual balance adjustment.
     *
     * When type is CLAIM and a file is uploaded, the file will be processed
     * automatically via UploadService and the resulting batch_id will be used
     * as ref_number — no changes needed on the FE side.
     *
     * @param BalanceAdjustmentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeAdjustment(BalanceAdjustmentRequest $request)
    {
        $user = $request->user();

        $customerCode = $request->get('customer_code');
        $adjustmentType = $request->get('adjustment_type');
        $amount = (float)$request->get('amount', 0.00);
        $description = $request->get('description');
        $type = strtoupper($request->get('type'));
        $inputRefNumber = $request->get('ref_number');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // If type is CLAIM and a file is provided, process the upload first
        // and automatically use the resulting batch_id as ref_number.
        if ($type === 'CLAIM' && $request->hasFile('file')) {
            $uploadedBy = $user->username ?? 'admin';
            $uploadResult = $this->uploadService->handleUpload($request->file('file'), $uploadedBy);
            $inputRefNumber = (string)($uploadResult['batch_id'] ?? $inputRefNumber);
        }

        // Generate adjustment reference number if not supplied: ADJ-YYYYMMDD-XXX
        $prefix = 'ADJ-' . date('Ymd') . '-';

        $ledgerRecord = DB::transaction(function () use ($customerCode, $adjustmentType, $amount, $description, $prefix, $user, $type, $inputRefNumber, $startDate, $endDate) {
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
