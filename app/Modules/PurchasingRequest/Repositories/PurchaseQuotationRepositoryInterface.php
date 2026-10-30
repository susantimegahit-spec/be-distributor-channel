<?php

namespace App\Modules\PurchasingRequest\Repositories;

use App\Models\PurchaseQuotation;
use App\Models\PurchaseQuotationOffer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PurchaseQuotationRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    public function findById(int $id): ?PurchaseQuotation;

    public function findByToken(string $token): ?PurchaseQuotation;

    public function create(array $data, array $items = []): PurchaseQuotation;

    public function update(int $id, array $data, ?array $items = null): ?PurchaseQuotation;

    public function delete(int $id): bool;

    public function getAllOffers(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection;

    public function findOfferById(int $id): ?PurchaseQuotationOffer;

    public function createOffer(PurchaseQuotation $quotation, array $vendorData, array $offerItems = []): PurchaseQuotationOffer;

    public function updateOffer(int $id, array $data): ?PurchaseQuotationOffer;

    public function deleteOffer(int $id): bool;
}

