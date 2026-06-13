<?php

namespace App\Modules\Claim\Repositories;

use App\Models\MstProgram;
use Illuminate\Support\Facades\DB;

class ProgramRepository implements ProgramRepositoryInterface
{
    /**
     * Get paginated programs with optional filters.
     */
    public function paginate(array $filters, int $perPage = 15)
    {
        $query = MstProgram::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('program_name', 'like', '%' . $search . '%')
                  ->orWhere('program_code', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Find a program by ID.
     */
    public function find(int $id)
    {
        return MstProgram::find($id);
    }

    /**
     * Find a program by ID with items and strata relations.
     */
    public function findWithDetails(int $id)
    {
        return MstProgram::with(['items', 'strata'])->find($id);
    }

    /**
     * Create a new program.
     */
    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $program = MstProgram::create([
                'program_code' => $data['program_code'],
                'program_name' => $data['program_name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'ACTIVE',
                'created_by' => $data['created_by'] ?? null,
            ]);

            if (!empty($data['items'])) {
                $program->items()->sync($data['items']);
            }

            if (!empty($data['strata'])) {
                foreach ($data['strata'] as $strataRow) {
                    $program->strata()->create([
                        'customer_type' => $strataRow['customer_type'],
                        'min_qty_kg' => $strataRow['min_qty_kg'],
                        'max_qty_kg' => $strataRow['max_qty_kg'] ?? null,
                        'harga_program_per_kg' => $strataRow['harga_program_per_kg'],
                        'diskon_per_kg' => $strataRow['diskon_per_kg'],
                    ]);
                }
            }

            return $program;
        });
    }

    /**
     * Update an existing program.
     */
    public function update(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $program = MstProgram::findOrFail($id);
            $program->update([
                'program_code' => $data['program_code'],
                'program_name' => $data['program_name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'ACTIVE',
            ]);

            if (isset($data['items'])) {
                $program->items()->sync($data['items']);
            }

            if (isset($data['strata'])) {
                $program->strata()->delete();
                foreach ($data['strata'] as $strataRow) {
                    $program->strata()->create([
                        'customer_type' => $strataRow['customer_type'],
                        'min_qty_kg' => $strataRow['min_qty_kg'],
                        'max_qty_kg' => $strataRow['max_qty_kg'] ?? null,
                        'harga_program_per_kg' => $strataRow['harga_program_per_kg'],
                        'diskon_per_kg' => $strataRow['diskon_per_kg'],
                    ]);
                }
            }

            return $program;
        });
    }

    /**
     * Delete a program by ID.
     */
    public function delete(int $id)
    {
        $program = MstProgram::findOrFail($id);
        return $program->delete();
    }
}
