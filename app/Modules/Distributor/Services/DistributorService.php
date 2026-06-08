<?php

namespace App\Modules\Distributor\Services;

use App\Modules\Distributor\Repositories\DistributorRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Distributor;

class DistributorService
{
    protected DistributorRepositoryInterface $distributorRepository;
    protected AuditLogService $auditLogService;

    /**
     * DistributorService constructor.
     *
     * @param  DistributorRepositoryInterface  $distributorRepository
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(
        DistributorRepositoryInterface $distributorRepository,
        AuditLogService $auditLogService
    ) {
        $this->distributorRepository = $distributorRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all distributors.
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return $this->distributorRepository->getAll();
    }

    /**
     * Get distributor by ID.
     *
     * @param  int  $id
     * @return Distributor|null
     */
    public function getById(int $id): ?Distributor
    {
        return $this->distributorRepository->getById($id);
    }

    /**
     * Synchronize distributors from SAP (Mock implementation).
     *
     * @param  int|null  $userId
     * @return array
     */
    public function syncFromSap(?int $userId = null): array
    {
        // Mock data from SAP
        $sapData = [
            [
                'code_customer' => 'C110000411',
                'name' => 'PT XYZ',
                'address' => 'Jl. Dummy No. 123, Jakarta',
                'phone' => '021-12345678',
                'email' => 'info@xyz.com',
                'status' => 1,
            ],
            [
                'code_customer' => 'C110000412',
                'name' => 'PT Berkah Abadi',
                'address' => 'Jl. Pahlawan No. 45, Surabaya',
                'phone' => '031-87654321',
                'email' => 'contact@berkahabadi.com',
                'status' => 1,
            ]
        ];

        $synced = [];
        foreach ($sapData as $data) {
            $synced[] = $this->distributorRepository->upsertByCode($data);
        }

        // Log to Audit Log if user is authenticated
        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_DISTRIBUTORS',
                'Synchronized ' . count($synced) . ' distributors from SAP.'
            );
        }

        return $synced;
    }
}
