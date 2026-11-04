<?php

namespace App\Modules\Claim\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\Item;
use App\Models\MstProgram;
use App\Models\TrxProgramUploadBatch;
use App\Modules\Claim\Repositories\UploadRepositoryInterface;
use App\Modules\Claim\Requests\StoreExternalClaimRequest;
use App\Modules\Claim\Services\UploadService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalClaimController extends Controller
{
    use ApiResponseFormatter;

    protected UploadService $uploadService;
    protected UploadRepositoryInterface $uploadRepository;

    public function __construct(
        UploadService $uploadService,
        UploadRepositoryInterface $uploadRepository
    ) {
        $this->uploadService = $uploadService;
        $this->uploadRepository = $uploadRepository;
    }

    /**
     * Submit new distributor claim transactions via External B2B API.
     * Supports JSON payload with item lines and/or Excel file upload (.xlsx, .xls).
     * Enforces API Key distributor authorization and idempotency control.
     *
     * @param StoreExternalClaimRequest $request
     * @return JsonResponse
     */
    public function store(StoreExternalClaimRequest $request): JsonResponse
    {
        // 1. Get allowed distributors from middleware context
        $allowedDistributors = $request->attributes->get('allowed_distributors');
        if (!$allowedDistributors || $allowedDistributors->isEmpty()) {
            return $this->errorResponse('Tidak ada distributor yang terdaftar pada API Key ini.', null, 401);
        }

        // 2. Validate card_code against allowed distributors for this API Key
        $cardCode = $request->input('card_code');
        $distributor = $allowedDistributors->firstWhere('code_customer', $cardCode);

        if (!$distributor) {
            $allowedCodes = $allowedDistributors->pluck('code_customer')->implode(', ');
            return $this->errorResponse(
                "card_code '{$cardCode}' tidak terdaftar untuk API Key ini. Distributor yang diizinkan: [{$allowedCodes}]",
                null,
                403
            );
        }

        $distributorRefNo = $request->input('distributor_ref_no');
        $depo = $request->input('depo');

        // 3. Idempotency Check — check if a batch with distributor_ref_no already exists for this distributor
        $existingBatch = DB::table('trx_program_upload_batch as b')
            ->join('trx_program_upload as u', 'u.batch_id', '=', 'b.id')
            ->where('b.distributor_ref_no', $distributorRefNo)
            ->where('u.customer_code', $distributor->code_customer)
            ->select('b.id', 'b.batch_no')
            ->first();

        if ($existingBatch) {
            $summary = $this->uploadRepository->findBatchWithSummary($existingBatch->id);
            return response()->json([
                'success'      => true,
                'message'      => 'Klaim dengan distributor_ref_no ini sudah pernah dibuat sebelumnya (Idempotent Response).',
                'is_duplicate' => true,
                'data'         => $summary,
            ], 200);
        }

        // 4. Parse transaction rows (from Excel file or JSON lines)
        try {
            if ($request->hasFile('file')) {
                $validatedRows = $this->uploadService->parseExcelForDistributor(
                    $request->file('file'),
                    $distributor->code_customer
                );
                $fileName = $request->file('file')->getClientOriginalName();
            } else {
                $validatedRows = [];
                $lines = $request->input('lines', []);
                $rootDepo = $depo;

                foreach ($lines as $line) {
                    $item = Item::where('item_code', $line['item_code'])->first();
                    $validatedRows[] = [
                        'customer_code'             => $distributor->code_customer,
                        'customer_name'             => $distributor->name,
                        'depo'                      => $line['depo'] ?? $rootDepo,
                        'distributor_customer_code' => $line['distributor_customer_code'] ?? null,
                        'distributor_customer_name' => $line['distributor_customer_name'] ?? null,
                        'address'                   => $line['address'] ?? null,
                        'city'                      => $line['city'] ?? null,
                        'district'                  => $line['district'] ?? null,
                        'salesman_name'             => $line['salesman_name'] ?? null,
                        'item_code'                 => $line['item_code'],
                        'item_name'                 => $item ? $item->item_name : ($line['item_name'] ?? null),
                        'item_category'             => $line['item_category'] ?? null,
                        'sell_price_per_kg'         => isset($line['sell_price_per_kg']) ? (float)$line['sell_price_per_kg'] : 0.0,
                        'qty_kg'                    => (float)$line['qty_kg'],
                        'customer_type'             => strtoupper(trim($line['customer_type'])),
                        'transaction_type'          => $line['transaction_type'] ?? null,
                        'channel'                   => $line['channel'] ?? null,
                        'invoice_no'                => $line['invoice_no'] ?? null,
                        'dpp'                       => isset($line['dpp']) && $line['dpp'] !== '' && $line['dpp'] !== null ? (float)$line['dpp'] : null,
                        'diskon_1'                  => isset($line['diskon_1']) && $line['diskon_1'] !== '' && $line['diskon_1'] !== null ? (float)$line['diskon_1'] : null,
                        'diskon_2'                  => isset($line['diskon_2']) && $line['diskon_2'] !== '' && $line['diskon_2'] !== null ? (float)$line['diskon_2'] : null,
                        'diskon_3'                  => isset($line['diskon_3']) && $line['diskon_3'] !== '' && $line['diskon_3'] !== null ? (float)$line['diskon_3'] : null,
                        'diskon_4'                  => isset($line['diskon_4']) && $line['diskon_4'] !== '' && $line['diskon_4'] !== null ? (float)$line['diskon_4'] : null,
                        'diskon_5'                  => isset($line['diskon_5']) && $line['diskon_5'] !== '' && $line['diskon_5'] !== null ? (float)$line['diskon_5'] : null,
                        'ppn'                       => isset($line['ppn']) && $line['ppn'] !== '' && $line['ppn'] !== null ? (float)$line['ppn'] : null,
                        'net'                       => isset($line['net']) && $line['net'] !== '' && $line['net'] !== null ? (float)$line['net'] : null,
                        'transaction_date'          => $line['transaction_date'],
                    ];
                }

                $fileName = 'API_CLAIM_' . $distributorRefNo;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Gagal memproses data klaim: ' . $e->getMessage(), null, 422);
        }

        if (empty($validatedRows)) {
            return $this->errorResponse('Tidak ada baris transaksi klaim yang valid untuk diproses.', null, 422);
        }

        // 5. Store batch and rows, then trigger automatic calculation
        $result = $this->uploadService->processTransactions(
            $validatedRows,
            $distributor->code_customer,
            $fileName,
            $distributorRefNo,
            $depo
        );

        $responseData = [
            'batch_id'           => $result['batch_id'],
            'batch_no'           => $result['batch_no'],
            'distributor_ref_no' => $distributorRefNo,
            'depo'               => $result['depo'] ?? $depo,
            'card_code'          => $distributor->code_customer,
            'customer_name'      => $distributor->name,
            'total_rows'         => $result['total_rows'],
            'processed_rows'     => $result['processed_rows'],
            'invalid_rows'       => $result['invalid_rows'],
            'total_diskon'       => $result['total_diskon'],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Data transaksi klaim distributor berhasil dikirim dan dikalkulasi secara otomatis.',
            'data'    => $responseData,
        ], 201);
    }

    /**
     * Get list of claim batches for the authenticated distributor(s).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBatches(Request $request): JsonResponse
    {
        $allowedDistributors = $request->attributes->get('allowed_distributors');
        if (!$allowedDistributors || $allowedDistributors->isEmpty()) {
            return $this->errorResponse('Tidak ada distributor yang terdaftar pada API Key ini.', null, 401);
        }

        $allowedCodes = $allowedDistributors->pluck('code_customer')->toArray();

        // Optional filter by card_code
        if ($request->has('card_code')) {
            $requestedCardCode = $request->input('card_code');
            if (!in_array($requestedCardCode, $allowedCodes)) {
                return $this->errorResponse("card_code '{$requestedCardCode}' tidak diizinkan untuk API Key ini.", null, 403);
            }
            $allowedCodes = [$requestedCardCode];
        }

        $perPage = (int)$request->get('per_page', 15);
        $batches = $this->uploadRepository->getBatchesPaginated($allowedCodes, $perPage);

        return $this->successResponse($batches, 'Daftar batch klaim berhasil diambil.');
    }

    /**
     * Get detail and calculation results of a specific claim batch.
     * Looks up by ID, batch_no, or distributor_ref_no.
     *
     * @param Request $request
     * @param string $identifier
     * @return JsonResponse
     */
    public function showBatch(Request $request, string $identifier): JsonResponse
    {
        $allowedDistributors = $request->attributes->get('allowed_distributors');
        if (!$allowedDistributors || $allowedDistributors->isEmpty()) {
            return $this->errorResponse('Tidak ada distributor yang terdaftar pada API Key ini.', null, 401);
        }

        $allowedCodes = $allowedDistributors->pluck('code_customer')->toArray();

        $batchQuery = DB::table('trx_program_upload_batch as b')
            ->join('trx_program_upload as u', 'u.batch_id', '=', 'b.id')
            ->whereIn('u.customer_code', $allowedCodes);

        if (is_numeric($identifier)) {
            $batchQuery->where(function ($q) use ($identifier) {
                $q->where('b.id', (int)$identifier)
                  ->orWhere('b.batch_no', $identifier)
                  ->orWhere('b.distributor_ref_no', $identifier);
            });
        } else {
            $batchQuery->where(function ($q) use ($identifier) {
                $q->where('b.batch_no', $identifier)
                  ->orWhere('b.distributor_ref_no', $identifier);
            });
        }

        $batch = $batchQuery->select('b.id')->first();

        if (!$batch) {
            return $this->errorResponse('Batch klaim tidak ditemukan atau tidak memiliki akses.', null, 404);
        }

        $summary = $this->uploadRepository->findBatchWithSummary($batch->id);

        // Fetch calculation results
        $results = DB::table('trx_program_result as r')
            ->join('trx_program_upload as u', 'r.upload_id', '=', 'u.id')
            ->where('u.batch_id', $batch->id)
            ->whereIn('r.customer_code', $allowedCodes)
            ->select([
                'r.id',
                'u.depo',
                'u.distributor_customer_code',
                'u.distributor_customer_name',
                'u.address',
                'u.city',
                'u.district',
                'u.salesman_name',
                'r.item_code',
                'r.item_name',
                'u.item_category',
                'r.qty_kg',
                'u.customer_type',
                'u.transaction_type',
                'u.channel',
                'u.invoice_no',
                'r.sell_price_per_kg',
                'u.dpp',
                'u.diskon_1',
                'u.diskon_2',
                'u.diskon_3',
                'u.diskon_4',
                'u.diskon_5',
                'u.ppn',
                'u.net',
                'r.harga_program_per_kg',
                'r.diskon_per_kg',
                'r.total_diskon',
                'r.transaction_date',
                'r.status',
                'r.is_verified',
            ])
            ->get();

        $detail = array_merge($summary, [
            'results' => $results,
        ]);

        return $this->successResponse($detail, 'Detail batch klaim berhasil diambil.');
    }

    /**
     * Get active claim programs applicable for the authenticated distributor(s).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPrograms(Request $request): JsonResponse
    {
        $allowedDistributors = $request->attributes->get('allowed_distributors');
        if (!$allowedDistributors || $allowedDistributors->isEmpty()) {
            return $this->errorResponse('Tidak ada distributor yang terdaftar pada API Key ini.', null, 401);
        }

        $allowedCodes = $allowedDistributors->pluck('code_customer')->toArray();

        $now = now()->toDateString();
        $programs = MstProgram::with(['items', 'strata'])
            ->where('status', 'ACTIVE')
            ->whereDate('start_date', '<=', $now)
            ->whereDate('end_date', '>=', $now)
            ->where(function ($query) use ($allowedCodes) {
                $query->whereNull('code_customer');
                foreach ($allowedCodes as $code) {
                    $query->orWhere('code_customer', $code)
                          ->orWhere('code_customer', 'like', $code . ',%')
                          ->orWhere('code_customer', 'like', '%,' . $code)
                          ->orWhere('code_customer', 'like', '%,' . $code . ',%');
                }
            })
            ->get();

        return $this->successResponse($programs, 'Daftar program promosi klaim aktif berhasil diambil.');
    }
}
