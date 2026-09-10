<?php

namespace App\Modules\Claim\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Claim\Services\ClaimService;
use App\Modules\Claim\Repositories\ResultRepositoryInterface;
use App\Modules\Item\Repositories\ItemRepositoryInterface;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class ClaimController extends Controller
{
    use ApiResponseFormatter;

    /**
     * @var ClaimService
     */
    protected ClaimService $claimService;

    /**
     * @var ItemRepositoryInterface
     */
    protected ItemRepositoryInterface $itemRepository;

    /**
     * @var ResultRepositoryInterface
     */
    protected ResultRepositoryInterface $resultRepository;

    /**
     * ClaimController constructor.
     *
     * @param ClaimService $claimService
     * @param ItemRepositoryInterface $itemRepository
     * @param ResultRepositoryInterface $resultRepository
     */
    public function __construct(
        ClaimService $claimService,
        ItemRepositoryInterface $itemRepository,
        ResultRepositoryInterface $resultRepository
    ) {
        $this->claimService = $claimService;
        $this->itemRepository = $itemRepository;
        $this->resultRepository = $resultRepository;
    }
    // tes commit

    /**
     * Download the claim Excel template.
     *
     * @return Response
     */
    public function downloadTemplate(): Response
    {
        $xmlContent = $this->claimService->generateTemplateExcel();

        return response($xmlContent)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="template_upload_klaim.xls"')
            ->header('Cache-Control', 'max-age=0');
    }

    /**
     * Lookup items for master program forms.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getItems(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $items = $this->itemRepository->getAll(['search' => $search]);

        $mapped = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->item_name,
            ];
        });

        return $this->successResponse($mapped, 'Pencarian item berhasil.');
    }

    /**
     * Get claims dashboard summary statistics.
     *
     * @return JsonResponse
     */
    public function dashboard(): JsonResponse
    {
        $summary = $this->resultRepository->getDashboardSummary();

        return $this->successResponse($summary, 'Statistik dashboard klaim berhasil diambil.');
    }
}
