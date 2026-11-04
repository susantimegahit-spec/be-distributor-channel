<?php

namespace App\Modules\Claim\Services;

use App\Modules\Claim\Repositories\ProgramRepositoryInterface;

class ProgramService
{
    /**
     * @var ProgramRepositoryInterface
     */
    protected ProgramRepositoryInterface $programRepository;

    /**
     * ProgramService constructor.
     *
     * @param ProgramRepositoryInterface $programRepository
     */
    public function __construct(ProgramRepositoryInterface $programRepository)
    {
        $this->programRepository = $programRepository;
    }

    /**
     * List paginated programs.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listPrograms(array $filters, int $perPage = 15)
    {
        return $this->programRepository->paginate($filters, $perPage);
    }

    /**
     * Get details of a single program.
     *
     * @param int $id
     * @return \App\Models\MstProgram|null
     */
    public function getProgramDetail(int $id)
    {
        return $this->programRepository->findWithDetails($id);
    }

    /**
     * Create a program.
     *
     * @param array $data
     * @return \App\Models\MstProgram
     */
    public function createProgram(array $data)
    {
        return $this->programRepository->create($data);
    }

    /**
     * Update an existing program.
     *
     * @param int $id
     * @param array $data
     * @return \App\Models\MstProgram
     */
    public function updateProgram(int $id, array $data)
    {
        return $this->programRepository->update($id, $data);
    }

    /**
     * Delete a program.
     *
     * @param int $id
     * @return bool
     */
    public function deleteProgram(int $id)
    {
        return $this->programRepository->delete($id);
    }
}
