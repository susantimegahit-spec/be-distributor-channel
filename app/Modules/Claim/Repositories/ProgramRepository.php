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
        $query = MstProgram::with('items');

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

        if (isset($filters['code_customer'])) {
            $customerCode = $filters['code_customer'];
            $query->where(function ($q) use ($customerCode, $filters) {
                $q->where('code_customer', $customerCode)
                  ->orWhere('code_customer', 'like', $customerCode . ',%')
                  ->orWhere('code_customer', 'like', '%,' . $customerCode)
                  ->orWhere('code_customer', 'like', '%,' . $customerCode . ',%');
                if (!empty($filters['include_general'])) {
                    $q->orWhereNull('code_customer');
                }
            });
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
            $programCode = !empty($data['program_code']) ? $data['program_code'] : $this->generateProgramCode();

            $program = MstProgram::create([
                'program_code' => $programCode,
                'program_name' => $data['program_name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'ACTIVE',
                'created_by' => $data['created_by'] ?? null,
                'code_customer' => $data['code_customer'] ?? null,
            ]);

            if (!empty($data['items'])) {
                $itemIds = \App\Models\Item::whereIn('item_code', $data['items'])->pluck('id')->toArray();
                $program->items()->sync($itemIds);
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
            
            $updateData = [
                'program_name' => $data['program_name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'ACTIVE',
                'code_customer' => $data['code_customer'] ?? null,
            ];

            if (!empty($data['program_code'])) {
                $updateData['program_code'] = $data['program_code'];
            }

            $program->update($updateData);

            if (isset($data['items'])) {
                $itemIds = \App\Models\Item::whereIn('item_code', $data['items'])->pluck('id')->toArray();
                $program->items()->sync($itemIds);
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
     * Generate sequential program code in the format PRGtahunbulan001.
     * E.g., PRG202606001
     */
    private function generateProgramCode(): string
    {
        $prefix = 'PRG' . date('Ym');
        
        $latest = MstProgram::withTrashed()
            ->where('program_code', 'like', $prefix . '%')
            ->orderBy('program_code', 'desc')
            ->first();
            
        if ($latest) {
            $seqStr = substr($latest->program_code, strlen($prefix));
            $nextSeq = is_numeric($seqStr) ? intval($seqStr) + 1 : 1;
        } else {
            $nextSeq = 1;
        }
        
        return $prefix . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
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
