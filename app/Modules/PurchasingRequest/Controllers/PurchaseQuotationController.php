<?php

namespace App\Modules\PurchasingRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PurchasingRequest\Requests\StoreQuotationRequest;
use App\Modules\PurchasingRequest\Requests\SubmitQuotationOfferRequest;
use App\Modules\PurchasingRequest\Requests\UpdateQuotationOfferRequest;
use App\Modules\PurchasingRequest\Requests\UpdateQuotationRequest;
use App\Modules\PurchasingRequest\Services\PurchaseQuotationService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseQuotationController extends Controller
{
    use ApiResponseFormatter;

    protected PurchaseQuotationService $service;

    public function __construct(PurchaseQuotationService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of quotations.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'from', 'to', 'paginate']);
        $perPage = (int) ($request->query('per_page') ?? $request->input('per_page') ?? 15);

        $result = $this->service->getQuotations($filters, $perPage);

        return $this->successResponse($result, 'Daftar Request for Quotation berhasil diambil.');
    }

    /**
     * Store a newly created quotation.
     */
    public function store(StoreQuotationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $userId = $request->user()?->id;

        $quotation = $this->service->createQuotation($payload, $userId);

        return $this->successResponse($quotation, 'Request for Quotation berhasil dibuat.', 201);
    }

    /**
     * Display the specified quotation.
     */
    public function show(string|int $id): JsonResponse
    {
        $quotation = is_numeric($id)
            ? $this->service->getQuotationById((int) $id)
            : $this->service->getQuotationByToken((string) $id);

        if (!$quotation) {
            return $this->errorResponse('Request for Quotation tidak ditemukan.', null, 404);
        }

        return $this->successResponse($quotation, 'Detail Request for Quotation berhasil diambil.');
    }

    /**
     * Update the specified quotation.
     */
    public function update(UpdateQuotationRequest $request, int $id): JsonResponse
    {
        $payload = $request->validated();
        $quotation = $this->service->updateQuotation($id, $payload);

        if (!$quotation) {
            return $this->errorResponse('Request for Quotation tidak ditemukan.', null, 404);
        }

        return $this->successResponse($quotation, 'Request for Quotation berhasil diperbarui.');
    }

    /**
     * Remove the specified quotation.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->service->deleteQuotation($id);

        if (!$deleted) {
            return $this->errorResponse('Request for Quotation tidak ditemukan.', null, 404);
        }

        return $this->successResponse(null, 'Request for Quotation berhasil dihapus.');
    }

    /**
     * Display a listing of vendor offers across quotations.
     */
    public function listOffers(Request $request): JsonResponse
    {
        $filters = $request->only(['quotation_id', 'status', 'search', 'paginate']);
        $perPage = (int) ($request->query('per_page') ?? $request->input('per_page') ?? 15);

        $result = $this->service->getOffers($filters, $perPage);

        return $this->successResponse($result, 'Daftar penawaran vendor berhasil diambil.');
    }

    /**
     * Display offers for a specific quotation.
     */
    public function getQuotationOffers(Request $request, int $quotationId): JsonResponse
    {
        $filters = $request->only(['status', 'search', 'paginate']);
        $perPage = (int) ($request->query('per_page') ?? $request->input('per_page') ?? 15);

        $result = $this->service->getOffersByQuotationId($quotationId, $filters, $perPage);

        return $this->successResponse($result, 'Daftar penawaran vendor untuk quotation ini berhasil diambil.');
    }

    /**
     * Display the specified vendor offer.
     */
    public function showOffer(int $id): JsonResponse
    {
        $offer = $this->service->getOfferById($id);

        if (!$offer) {
            return $this->errorResponse('Penawaran vendor tidak ditemukan.', null, 404);
        }

        return $this->successResponse($offer, 'Detail penawaran vendor berhasil diambil.');
    }

    /**
     * Update the specified vendor offer.
     */
    public function updateOffer(UpdateQuotationOfferRequest $request, int $id): JsonResponse
    {
        $payload = $request->validated();
        $offer = $this->service->updateOffer($id, $payload);

        if (!$offer) {
            return $this->errorResponse('Penawaran vendor tidak ditemukan.', null, 404);
        }

        return $this->successResponse($offer, 'Penawaran vendor berhasil diperbarui.');
    }

    /**
     * Remove the specified vendor offer.
     */
    public function destroyOffer(int $id): JsonResponse
    {
        $deleted = $this->service->deleteOffer($id);

        if (!$deleted) {
            return $this->errorResponse('Penawaran vendor tidak ditemukan.', null, 404);
        }

        return $this->successResponse(null, 'Penawaran vendor berhasil dihapus.');
    }

    /**
     * Public endpoint: retrieve quotation info by token or ID (path, query, or body).
     */
    public function showPublic(Request $request, ?string $token = null): JsonResponse
    {
        $identifier = $token 
            ?? $request->route('token') 
            ?? $request->query('token') 
            ?? $request->input('token') 
            ?? $request->query('id') 
            ?? $request->input('id');

        if (!$identifier) {
            return $this->errorResponse('Parameter token atau id quotation wajib diisi.', null, 400);
        }

        $quotation = is_numeric($identifier)
            ? $this->service->getQuotationById((int) $identifier)
            : $this->service->getQuotationByToken((string) $identifier);

        if (!$quotation) {
            return $this->errorResponse('Request for Quotation tidak ditemukan atau token tidak valid.', null, 404);
        }

        return $this->successResponse($quotation, 'Informasi Request for Quotation berhasil diambil.');
    }

    /**
     * Public endpoint: vendor submits offer by token or ID (path, query, or body).
     */
    public function submitPublicOffer(SubmitQuotationOfferRequest $request, ?string $token = null): JsonResponse
    {
        $identifier = $token 
            ?? $request->route('token') 
            ?? $request->input('token') 
            ?? $request->query('token') 
            ?? $request->input('id') 
            ?? $request->input('quotation_id') 
            ?? $request->query('id');

        if (!$identifier) {
            return $this->errorResponse('Parameter token atau id quotation wajib diisi.', null, 400);
        }

        $payload = $request->validated();
        $offer = $this->service->submitOffer($identifier, $payload);

        if (!$offer) {
            return $this->errorResponse('Request for Quotation tidak ditemukan atau token tidak valid.', null, 404);
        }

        return $this->successResponse($offer, 'Penawaran harga vendor berhasil dikirim.', 201);
    }
}

