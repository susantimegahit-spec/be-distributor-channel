<?php

namespace App\Modules\Claim\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Claim\Services\UploadService;
use App\Modules\Claim\Requests\UploadTransactionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UploadController extends Controller
{
    /**
     * @var UploadService
     */
    protected UploadService $uploadService;

    /**
     * UploadController constructor.
     *
     * @param UploadService $uploadService
     */
    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Upload and calculate transactions batch.
     *
     * @param UploadTransactionRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(UploadTransactionRequest $request)
    {
        $file = $request->file('file');
        $uploadedBy = $request->user()->username ?? 'admin';
        
        $result = $this->uploadService->handleUpload($file, $uploadedBy);

        return response()->json($result, Response::HTTP_CREATED);
    }

    /**
     * Get list of uploaded batches.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBatches(Request $request)
    {
        $batches = $this->uploadService->listBatches($request->get('per_page', 15));
        
        return response()->json($batches);
    }

    /**
     * Show detailed summary stats for a single batch.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showBatch($id)
    {
        $batch = $this->uploadService->getBatchDetail((int)$id);
        if (!$batch) {
            return response()->json(['message' => 'Batch tidak ditemukan'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($batch);
    }
}
