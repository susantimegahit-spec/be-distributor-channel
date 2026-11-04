<?php

namespace App\Modules\Ekspedisi\Services;

use App\Models\WarehouseOrigin;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class WarehouseOriginUploadService
{
    /**
     * Upload and process master warehouse origins Excel/CSV file.
     *
     * @param UploadedFile $file
     * @return array
     */
    public function uploadOrigins(UploadedFile $file): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (empty($rows) || count($rows) < 2) {
            throw new \Exception('File Excel/CSV kosong atau tidak memiliki data.');
        }

        // Parse header (row 0)
        $rawHeaders = array_shift($rows);
        $headerMap = $this->mapHeaders($rawHeaders, [
            'whs_name_origin' => ['whs_name_origin', 'nama_asal', 'nama_origin', 'gudang_asal', 'nama_gudang_asal'],
            'whs_code'        => ['whs_code', 'kode_gudang', 'kode', 'warehouse_code'],
            'street'          => ['street', 'alamat', 'jalan'],
            'status'          => ['status'],
        ]);

        if (!isset($headerMap['whs_name_origin'])) {
            throw new \Exception('Header "whs_name_origin" atau "nama_gudang_asal" wajib ada dalam file.');
        }

        if (!isset($headerMap['whs_code'])) {
            throw new \Exception('Header "whs_code" atau "kode_gudang" wajib ada dalam file.');
        }

        $processed = 0;
        $created = 0;
        $updated = 0;
        $errors = [];

        // Pre-fetch warehouses for fast lookup
        $warehouseMap = Warehouse::pluck('whs_name', 'whs_code')->toArray();

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // Row 1 is header
                
                // Skip empty row
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $whsNameOrigin = trim((string) ($this->getValueByMap($row, $headerMap, 'whs_name_origin') ?? ''));
                $whsCode = trim((string) ($this->getValueByMap($row, $headerMap, 'whs_code') ?? ''));

                if (empty($whsNameOrigin)) {
                    $errors[] = "Baris #{$rowNum}: Nama origin/asal kosong.";
                    continue;
                }

                if (empty($whsCode)) {
                    $errors[] = "Baris #{$rowNum}: Kode gudang kosong.";
                    continue;
                }

                // Check if warehouse code exists in master warehouses
                if (!isset($warehouseMap[$whsCode])) {
                    $errors[] = "Baris #{$rowNum}: Kode gudang '{$whsCode}' tidak terdaftar di master warehouses.";
                    continue;
                }

                $whsName = $warehouseMap[$whsCode];

                $payload = [
                    'whs_name_origin' => $whsNameOrigin,
                    'whs_name'        => $whsName,
                    'street'          => $this->getValueByMap($row, $headerMap, 'street'),
                    'status'          => strtoupper((string) ($this->getValueByMap($row, $headerMap, 'status') ?? 'ACTIVE')),
                    'updated_by'      => auth()->id(),
                ];

                $origin = WarehouseOrigin::where('whs_code', $whsCode)->first();

                if ($origin) {
                    $origin->update($payload);
                    $updated++;
                } else {
                    $payload['whs_code'] = $whsCode;
                    $payload['created_by'] = auth()->id();
                    WarehouseOrigin::create($payload);
                    $created++;
                }

                $processed++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'processed_count' => $processed,
            'created_count'   => $created,
            'updated_count'   => $updated,
            'errors'          => $errors,
        ];
    }

    /**
     * Map header names to column indexes.
     */
    protected function mapHeaders(array $rawHeaders, array $definitions): array
    {
        $map = [];
        foreach ($rawHeaders as $index => $raw) {
            if (empty($raw)) {
                continue;
            }
            $clean = strtolower(trim(str_replace([' ', '-'], '_', (string) $raw)));
            foreach ($definitions as $key => $aliases) {
                if (in_array($clean, $aliases)) {
                    $map[$key] = $index;
                    break;
                }
            }
        }
        return $map;
    }

    /**
     * Get cell value by mapped column key.
     */
    protected function getValueByMap(array $row, array $map, string $key)
    {
        if (isset($map[$key]) && isset($row[$map[$key]])) {
            $val = $row[$map[$key]];
            return $val !== '' ? $val : null;
        }
        return null;
    }

    /**
     * Check if a row is completely empty.
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string)$cell) !== '') {
                return false;
            }
        }
        return true;
    }
}
