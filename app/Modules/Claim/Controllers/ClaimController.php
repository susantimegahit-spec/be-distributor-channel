<?php

namespace App\Modules\Claim\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Claim\Services\ClaimService;
use Illuminate\Http\Response;

class ClaimController extends Controller
{
    protected ClaimService $claimService;

    /**
     * ClaimController constructor.
     *
     * @param  ClaimService  $claimService
     */
    public function __construct(ClaimService $claimService)
    {
        $this->claimService = $claimService;
    }

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
}
