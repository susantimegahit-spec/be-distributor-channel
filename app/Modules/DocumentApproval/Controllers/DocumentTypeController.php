<?php

namespace App\Modules\DocumentApproval\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = DocumentType::with('activeSchema')->where('is_active', true)->get();
        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    public function show(string $code): JsonResponse
    {
        $type = DocumentType::with(['activeSchema.fields', 'workflows.stages'])->where('code', strtoupper($code))->firstOrFail();
        return response()->json([
            'success' => true,
            'data' => $type,
        ]);
    }
}
