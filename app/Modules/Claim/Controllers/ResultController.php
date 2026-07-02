<?php

namespace App\Modules\Claim\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Claim\Repositories\ResultRepositoryInterface;
use App\Modules\Claim\Services\ExportService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
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
        $filters = $request->only(['batch_id', 'status', 'customer_code', 'program_id']);
        
        // Scope by distributor user if applicable
        if ($request->user() && $request->user()->code_customer) {
            $filters['customer_code'] = $request->user()->code_customer;
        }

        $results = $this->resultRepository->paginateResults($filters, $request->get('per_page', 15));

        return response()->json($results);
    }

    /**
     * Export results to Excel.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function export(Request $request)
    {
        $filters = $request->only(['batch_id', 'status', 'customer_code', 'program_id']);

        // Scope by distributor user if applicable
        if ($request->user() && $request->user()->code_customer) {
            $filters['customer_code'] = $request->user()->code_customer;
        }

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

        return response()->json([
            'message' => count($ids) . ' transaksi sell out berhasil diverifikasi.'
        ]);
    }

    /**
     * Get claim reward balance and summary.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSummary(Request $request)
    {
        $customerCode = null;
        if ($request->user() && $request->user()->code_customer) {
            $customerCode = $request->user()->code_customer;
        } elseif ($request->has('customer_code')) {
            $customerCode = $request->get('customer_code');
        }

        $summary = $this->resultRepository->getRewardSummary($customerCode);

        return response()->json($summary);
    }
}
