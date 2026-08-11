<?php

namespace App\Modules\PurchasingRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PurchasingRequest\Services\DocumentTypeService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    use ApiResponseFormatter;

    protected DocumentTypeService $service;

    public function __construct(DocumentTypeService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search']);
        $documentTypes = $this->service->getAll($filters);

        $data = $documentTypes->map(function ($docType) {
            return [
                'code' => $docType->code,
                'name' => $docType->name,
                'object_type' => $docType->object_type,
            ];
        });

        return $this->successResponse($data, 'Daftar document type berhasil diambil.');
    }
}
