<?php

namespace App\Modules\DistributorItemPrice\Services;

use App\Modules\DistributorItemPrice\Repositories\DistributorItemPriceRepositoryInterface;
use App\Models\DistributorItemPrice;
use Illuminate\Database\Eloquent\Collection;

class DistributorItemPriceService
{
    protected DistributorItemPriceRepositoryInterface $repository;

    /**
     * DistributorItemPriceService constructor.
     *
     * @param  DistributorItemPriceRepositoryInterface  $repository
     */
    public function __construct(DistributorItemPriceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all distributor item prices.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->repository->getAll($filters);
    }

    /**
     * Get distributor item price by ID.
     *
     * @param  int  $id
     * @return DistributorItemPrice|null
     */
    public function getById(int $id): ?DistributorItemPrice
    {
        return $this->repository->getById($id);
    }

    /**
     * Create a new distributor item price.
     *
     * @param  array  $data
     * @param  int  $userId
     * @return DistributorItemPrice
     */
    public function create(array $data, int $userId): DistributorItemPrice
    {
        $data['created_by'] = $userId;
        return $this->repository->create($data);
    }

    /**
     * Update an existing distributor item price.
     *
     * @param  int  $id
     * @param  array  $data
     * @param  int  $userId
     * @return DistributorItemPrice
     */
    public function update(int $id, array $data, int $userId): DistributorItemPrice
    {
        $price = $this->repository->getById($id);
        if (!$price) {
            throw new \Exception('Data harga distributor tidak ditemukan.');
        }

        $data['updated_by'] = $userId;
        return $this->repository->update($price, $data);
    }

    /**
     * Delete a distributor item price.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $price = $this->repository->getById($id);
        if (!$price) {
            throw new \Exception('Data harga distributor tidak ditemukan.');
        }

        return $this->repository->delete($price);
    }
}
