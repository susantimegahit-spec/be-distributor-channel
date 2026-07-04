<?php

namespace App\Modules\Claim\Services;

use App\Models\TrxProgramUpload;
use App\Models\TrxProgramResult;
use App\Models\Item;
use App\Models\MstProgram;
use App\Models\MstProgramStrata;
use Carbon\Carbon;

class ClaimCalculationService
{
    /**
     * Calculate discount for all raw upload records in a batch.
     *
     * @param int $batchId
     * @return array
     */
    public function calculateBatch(int $batchId): array
    {
        // 1. Get raw transaction uploads
        $uploads = TrxProgramUpload::where('batch_id', $batchId)->get();

        $results = [];

        foreach ($uploads as $upload) {
            $status = 'VALID_PROGRAM';
            $programId = null;
            $hargaProgramPerKg = 0.00;
            $diskonPerKg = 0.00;
            $totalDiskon = 0.00;

            // 2. Find Item by item_code
            $item = Item::where('item_code', $upload->item_code)->first();
            if (!$item) {
                $status = 'ITEM_NOT_FOUND';
                $results[] = $this->buildResultData($upload, null, 0, 0, 0, $status);
                continue;
            }

            // 3. Find Active Program for item_id & transaction_date & code_customer
            $program = MstProgram::where('status', 'ACTIVE')
                ->whereDate('start_date', '<=', $upload->transaction_date)
                ->whereDate('end_date', '>=', $upload->transaction_date)
                ->whereHas('items', function ($query) use ($item) {
                    $query->where('items.id', $item->id);
                })
                ->where(function ($query) use ($upload) {
                    $query->where('code_customer', $upload->customer_code)
                          ->orWhereNull('code_customer');
                })
                ->orderByRaw('CASE WHEN code_customer IS NULL THEN 1 ELSE 0 END ASC')
                ->first();

            if (!$program) {
                $status = 'PROGRAM_NOT_FOUND';
                $results[] = $this->buildResultData($upload, null, 0, 0, 0, $status);
                continue;
            }

            $programId = $program->id;

            // 4. Find Strata
            $strata = MstProgramStrata::where('program_id', $programId)
                ->where('customer_type', $upload->customer_type)
                ->where('min_qty_kg', '<=', $upload->qty_kg)
                ->where(function ($query) use ($upload) {
                    $query->whereNull('max_qty_kg')
                          ->orWhere('max_qty_kg', '>=', $upload->qty_kg);
                })
                ->first();

            if (!$strata) {
                $status = 'STRATA_NOT_FOUND';
                $results[] = $this->buildResultData($upload, $programId, 0, 0, 0, $status);
                continue;
            }

            // 5 & 6. Calculate discount
            $hargaProgramPerKg = (float)$strata->harga_program_per_kg;
            $diskonPerKg = (float)$strata->diskon_per_kg;
            $totalDiskon = (float)$upload->qty_kg * $diskonPerKg;

            $results[] = $this->buildResultData($upload, $programId, $hargaProgramPerKg, $diskonPerKg, $totalDiskon, $status);
        }

        // Insert results in bulk
        if (!empty($results)) {
            TrxProgramResult::insert($results);
        }

        // 7. Generate summary
        $totalRows = count($uploads);
        $validRows = 0;
        $invalidRows = 0;
        $sumDiskon = 0.00;

        foreach ($results as $res) {
            if ($res['status'] === 'VALID_PROGRAM') {
                $validRows++;
                $sumDiskon += $res['total_diskon'];
            } else {
                $invalidRows++;
            }
        }

        return [
            'total_rows' => $totalRows,
            'processed_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'total_diskon' => $sumDiskon
        ];
    }

    /**
     * Build result data array for bulk insertion.
     */
    private function buildResultData($upload, $programId, $hargaProgram, $diskon, $totalDiskon, $status): array
    {
        $dateStr = $upload->transaction_date instanceof Carbon
            ? $upload->transaction_date->format('Y-m-d')
            : (is_string($upload->transaction_date) ? substr($upload->transaction_date, 0, 10) : $upload->transaction_date);

        return [
            'upload_id' => $upload->id,
            'program_id' => $programId,
            'customer_code' => $upload->customer_code,
            'customer_name' => $upload->customer_name,
            'item_code' => $upload->item_code,
            'item_name' => $upload->item_name,
            'qty_kg' => $upload->qty_kg,
            'sell_price_per_kg' => $upload->sell_price_per_kg,
            'harga_program_per_kg' => $hargaProgram,
            'diskon_per_kg' => $diskon,
            'total_diskon' => $totalDiskon,
            'transaction_date' => $dateStr,
            'status' => $status,
            'created_at' => Carbon::now(),
        ];
    }
}
