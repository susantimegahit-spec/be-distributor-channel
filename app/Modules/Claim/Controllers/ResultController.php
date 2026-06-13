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
        return $this->exportService->exportResults($filters);
    }
}
