<?php

namespace App\Modules\Claim\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Claim\Repositories\ResultRepositoryInterface;
use App\Modules\Claim\Services\ExportService;
use App\Traits\ApiResponseFormatter;
use App\Traits\HasCustomerCodeResolver;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    use ApiResponseFormatter, HasCustomerCodeResolver;

    /**
     * @var ResultRepositoryInterface
     */
    protected ResultRepositoryInterface $resultRepository;

    /**
     * @var ExportService
     */
    protected ExportService $exportService;

    /**
     * ResultController constructor.
     *
     * @param ResultRepositoryInterface $resultRepository
     * @param ExportService $exportService
     */
    public function __construct(
        ResultRepositoryInterface $resultRepository,
        ExportService $exportService
    ) {
        $this->resultRepository = $resultRepository;
        $this->exportService = $exportService;
    }

    /**
     * Get list of calculation results.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $filters = $request->only(['batch_id', 'status', 'program_id']);
        $filters['customer_code'] = $this->resolveCustomerCodes($request);

        $results = $this->resultRepository->getResults($filters);

        return $this->successResponse($results, 'Daftar hasil kalkulasi berhasil diambil.');
    }

    /**
     * Export results to Excel.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function export(Request $request)
    {
        $filters = $request->only(['batch_id', 'status', 'program_id']);
        $filters['customer_code'] = $this->resolveCustomerCodes($request);

        return $this->exportService->exportResults($filters);
    }

    /**
     * Bulk verify calculation results.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyBulk(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:trx_program_result,id',
            'is_verified' => 'boolean',
        ]);

        $ids = $request->get('ids');
        $isVerified = $request->get('is_verified', true);

        $this->resultRepository->verifyResults($ids, $isVerified);

        return $this->successResponse(null, count($ids) . ' transaksi sell out berhasil diverifikasi.');
    }

    /**
     * Get claim reward balance and summary.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSummary(Request $request)
    {
        $customerCodes = $this->resolveCustomerCodes($request);

        $summary = $this->resultRepository->getRewardSummary($customerCodes);

        return $this->successResponse($summary, 'Summary saldo reward berhasil diambil.');
    }
}
