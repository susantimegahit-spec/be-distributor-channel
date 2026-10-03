<?php

namespace App\Modules\Claim\Repositories;

interface ProgramRepositoryInterface
{
    /**
     * Get paginated programs with optional filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate(array $filters, int $perPage = 15);

    /**
     * Find a program by ID.
     *
     * @param int $id
     * @return \App\Models\MstProgram|null
     */
    public function find(int $id);

    /**
     * Find a program by ID with items and strata relations.
     *
     * @param int $id
     * @return \App\Models\MstProgram|null
     */
    public function findWithDetails(int $id);

    /**
     * Create a new program.
     *
     * @param array $data
     * @return \App\Models\MstProgram
     */
    public function create(array $data);

    /**
     * Update an existing program.
     *
     * @param int $id
     * @param array $data
     * @return \App\Models\MstProgram
     */
    public function update(int $id, array $data);

    /**
     * Delete a program by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id);
}
