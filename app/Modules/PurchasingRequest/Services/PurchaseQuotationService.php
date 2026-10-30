<?php

namespace App\Modules\PurchasingRequest\Services;

use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationOffer;
use App\Modules\PurchasingRequest\Repositories\PurchaseQuotationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PurchaseQuotationService
{
    protected PurchaseQuotationRepositoryInterface $repository;

    public function __construct(PurchaseQuotationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getQuotations(array $filters = [], int $perPage = 15): array
    {
        $result = $this->repository->getAll($filters, $perPage);

        if ($result instanceof LengthAwarePaginator) {
            return [
                'current_page' => $result->currentPage(),
                'data'         => collect($result->items())->map(fn($q) => $q->toApiArray(true))->values()->all(),
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'last_page'    => $result->lastPage(),
            ];
        }

        return [
            'data' => $result->map(fn($q) => $q->toApiArray(true))->values()->all(),
        ];
    }

    public function getQuotationById(int $id): ?array
    {
        $quotation = $this->repository->findById($id);
        return $quotation ? $quotation->toApiArray(true) : null;
    }

    public function getQuotationByToken(string $token): ?array
    {
        $quotation = $this->repository->findByToken($token);
        return $quotation ? $quotation->toApiArray(true) : null;
    }

    public function createQuotation(array $payload, ?int $userId = null): array
    {
        $data = [
            'number'      => $payload['number'] ?? null,
            'token'       => $payload['token'] ?? null,
            'status'      => $payload['status'] ?? 'OPEN',
            'title'       => $payload['title'] ?? null,
            'notes'       => $payload['notes'] ?? null,
            'user_id'     => $userId ?? ($payload['user_id'] ?? null),
            'valid_until' => $payload['valid_until'] ?? $payload['validUntil'] ?? null,
        ];

        $items = $payload['items'] ?? [];
        $quotation = $this->repository->create($data, $items);

        return $quotation->toApiArray(true);
    }

    public function updateQuotation(int $id, array $payload): ?array
    {
        $data = [];
        foreach (['status', 'title', 'notes'] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        if (array_key_exists('valid_until', $payload)) {
            $data['valid_until'] = $payload['valid_until'];
        } elseif (array_key_exists('validUntil', $payload)) {
            $data['valid_until'] = $payload['validUntil'];
        }

        $items = array_key_exists('items', $payload) ? $payload['items'] : null;

        $quotation = $this->repository->update($id, $data, $items);

        return $quotation ? $quotation->toApiArray(true) : null;
    }

    public function deleteQuotation(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function getOffers(array $filters = [], int $perPage = 15): array
    {
        $result = $this->repository->getAllOffers($filters, $perPage);

        if ($result instanceof LengthAwarePaginator) {
            return [
                'current_page' => $result->currentPage(),
                'data'         => collect($result->items())->map(fn($o) => $o->toApiArray())->values()->all(),
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'last_page'    => $result->lastPage(),
            ];
        }

        return [
            'data' => $result->map(fn($o) => $o->toApiArray())->values()->all(),
        ];
    }

    public function getOffersByQuotationId(int $quotationId, array $filters = [], int $perPage = 15): array
    {
        $filters['quotation_id'] = $quotationId;
        return $this->getOffers($filters, $perPage);
    }

    public function getOfferById(int $id): ?array
    {
        $offer = $this->repository->findOfferById($id);
        return $offer ? $offer->toApiArray() : null;
    }

    public function submitOffer(string $tokenOrId, array $payload): ?array
    {
        $quotation = is_numeric($tokenOrId)
            ? $this->repository->findById((int) $tokenOrId)
            : $this->repository->findByToken($tokenOrId);

        if (!$quotation) {
            return null;
        }

        $vendorData = $payload['vendor'] ?? [
            'company' => $payload['company_name'] ?? $payload['company'] ?? '',
            'name'    => $payload['pic_name'] ?? $payload['name'] ?? '',
            'email'   => $payload['email'] ?? '',
            'phone'   => $payload['phone'] ?? '',
            'notes'   => $payload['notes'] ?? null,
            'status'  => $payload['status'] ?? 'SUBMITTED',
        ];

        $items = $payload['items'] ?? [];

        $offer = $this->repository->createOffer($quotation, $vendorData, $items);

        return $offer->toApiArray();
    }

    public function updateOffer(int $id, array $payload): ?array
    {
        $offer = $this->repository->updateOffer($id, $payload);
        return $offer ? $offer->toApiArray() : null;
    }

    public function deleteOffer(int $id): bool
    {
        return $this->repository->deleteOffer($id);
    }
}

