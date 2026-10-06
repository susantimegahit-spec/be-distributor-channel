<?php

namespace App\Modules\MasterUnit\Services;

use App\Models\MasterUnit;
use App\Modules\AuditLog\Services\AuditLogService;
use App\Modules\MasterUnit\Repositories\MasterUnitRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MasterUnitService
{
    protected MasterUnitRepositoryInterface $masterUnitRepository;
    protected ?AuditLogService $auditLogService;

    /**
     * MasterUnitService constructor.
     *
     * @param  MasterUnitRepositoryInterface  $masterUnitRepository
     * @param  AuditLogService|null  $auditLogService
     */
    public function __construct(
        MasterUnitRepositoryInterface $masterUnitRepository,
        ?AuditLogService $auditLogService = null
    ) {
        $this->masterUnitRepository = $masterUnitRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all master units.
     *
     * @param  array  $filters
     * @return Collection|LengthAwarePaginator
     */
    public function getAll(array $filters = []): Collection|LengthAwarePaginator
    {
        return $this->masterUnitRepository->getAll($filters);
    }

    /**
     * Get master unit by ID.
     *
     * @param  int  $id
     * @return MasterUnit|null
     */
    public function getById(int $id): ?MasterUnit
    {
        return $this->masterUnitRepository->findById($id);
    }

    /**
     * Create a new master unit.
     *
     * @param  array  $data
     * @param  int|null  $userId
     * @return MasterUnit
     */
    public function create(array $data, ?int $userId = null): MasterUnit
    {
        $unit = $this->masterUnitRepository->create($data);

        if ($userId && $this->auditLogService) {
            $this->auditLogService->log(
                $userId,
                'CREATE_MASTER_UNIT',
                "Created master unit: {$unit->unit_code} - {$unit->unit_name}"
            );
        }

        return $unit;
    }

    /**
     * Update an existing master unit.
     *
     * @param  int  $id
     * @param  array  $data
     * @param  int|null  $userId
     * @return MasterUnit
     */
    public function update(int $id, array $data, ?int $userId = null): MasterUnit
    {
        $unit = $this->masterUnitRepository->update($id, $data);

        if ($userId && $this->auditLogService) {
            $this->auditLogService->log(
                $userId,
                'UPDATE_MASTER_UNIT',
                "Updated master unit ID {$id}: {$unit->unit_code} - {$unit->unit_name}"
            );
        }

        return $unit;
    }

    /**
     * Delete a master unit.
     *
     * @param  int  $id
     * @param  int|null  $userId
     * @return bool
     */
    public function delete(int $id, ?int $userId = null): bool
    {
        $unit = $this->masterUnitRepository->findById($id);
        if (!$unit) {
            throw new \Exception("Master unit dengan ID {$id} tidak ditemukan.");
        }

        $code = $unit->unit_code;
        $name = $unit->unit_name;

        $result = $this->masterUnitRepository->delete($id);

        if ($userId && $this->auditLogService) {
            $this->auditLogService->log(
                $userId,
                'DELETE_MASTER_UNIT',
                "Deleted master unit ID {$id}: {$code} - {$name}"
            );
        }

        return $result;
    }
}
