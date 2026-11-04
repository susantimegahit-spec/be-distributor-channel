<?php

namespace App\Modules\Ekspedisi\Services;

use App\Models\Item;
use App\Models\LoadingRate;
use App\Models\LoadingRateType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoadingRateUploadService
{
    /**
     * Upload and process master loading rates from Excel/CSV file.
     *
     * @param UploadedFile $file
     * @param int|null $userId
     * @return array
     * @throws \Exception
     */
    public function uploadRates(UploadedFile $file, ?int $userId = null): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (empty($rows) || count($rows) < 2) {
            throw new \Exception('File Excel/CSV kosong atau tidak memiliki baris data.');
        }

        // Parse header row
        $rawHeaders = array_shift($rows);
        $headerMap = $this->mapHeaders($rawHeaders, [
            'item_code' => ['item_code', 'kode_item', 'kode', 'item', 'itemcode', 'item_no'],
            'item_name' => ['item_name', 'nama_item', 'nama_barang', 'nama', 'itemname', 'description'],
            'rate_type' => ['rate_type', 'type_rate', 'jenis_perhitungan', 'tipe_rate', 'tipe', 'type', 'kategori'],
            'rate'      => ['rate', 'biaya', 'ongkos', 'tarif', 'cost', 'harga', 'amount', 'biayanya'],
            'uom'       => ['uom', 'satuan', 'unit_msr', 'unit'],
            'status'    => ['status'],
            'remarks'   => ['remarks', 'keterangan', 'catatan', 'notes'],
        ]);

        if (!isset($headerMap['item_code'])) {
            throw new \Exception('Kolom "item_code" atau "kode_item" wajib ada dalam file Excel.');
        }

        if (!isset($headerMap['rate'])) {
            throw new \Exception('Kolom "rate" atau "biaya" wajib ada dalam file Excel.');
        }

        // Cache rate types
        $rateTypes = LoadingRateType::all()->keyBy(function ($item) {
            return strtoupper(trim($item->code));
        });

        // Ensure default types exist
        if (!$rateTypes->has('REGULAR')) {
            $reg = LoadingRateType::firstOrCreate(
                ['code' => 'REGULAR'],
                ['name' => 'Regular', 'description' => 'Ongkos angkut jam kerja reguler', 'is_active' => true]
            );
            $rateTypes->put('REGULAR', $reg);
        }
        if (!$rateTypes->has('OVERTIME')) {
            $ot = LoadingRateType::firstOrCreate(
                ['code' => 'OVERTIME'],
                ['name' => 'Overtime', 'description' => 'Ongkos angkut lembur', 'is_active' => true]
            );
            $rateTypes->put('OVERTIME', $ot);
        }

        $processed = 0;
        $created = 0;
        $updated = 0;
        $errors = [];

        DB::connection((new LoadingRate())->getConnectionName())->beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;

                // Extract values
                $itemCode = isset($headerMap['item_code']) ? trim((string) ($row[$headerMap['item_code']] ?? '')) : '';
                if ($itemCode === '') {
                    continue; // Skip empty row
                }

                $rawRate = isset($headerMap['rate']) ? $row[$headerMap['rate']] : null;
                $cleanedRate = str_replace([',', ' '], '', trim((string) $rawRate));
                if (!is_numeric($cleanedRate)) {
                    $errors[] = "Baris {$rowNum}: Nilai biaya/tarif '{$rawRate}' tidak valid numerik.";
                    continue;
                }
                $rateValue = (float) $cleanedRate;

                // Resolve Rate Type (default to REGULAR if omitted)
                $rawRateType = isset($headerMap['rate_type']) ? strtoupper(trim((string) ($row[$headerMap['rate_type']] ?? ''))) : '';
                $normalizedTypeCode = $this->normalizeRateType($rawRateType);

                $targetRateType = $rateTypes->get($normalizedTypeCode);
                if (!$targetRateType) {
                    $errors[] = "Baris {$rowNum}: Jenis perhitungan / rate type '{$rawRateType}' tidak dikenali. Gunakan REGULAR atau OVERTIME.";
                    continue;
                }

                // Resolve item name and uom
                $itemName = isset($headerMap['item_name']) ? trim((string) ($row[$headerMap['item_name']] ?? '')) : '';
                $uom = isset($headerMap['uom']) ? strtoupper(trim((string) ($row[$headerMap['uom']] ?? ''))) : '';

                if ($itemName === '' || $uom === '') {
                    $masterItem = Item::where('item_code', $itemCode)->first();
                    if ($masterItem) {
                        if ($itemName === '') {
                            $itemName = (string) $masterItem->item_name;
                        }
                        if ($uom === '') {
                            $uom = (string) ($masterItem->sal_unit_msr ?? $masterItem->invntry_uom ?? 'PCS');
                        }
                    }
                }

                $status = isset($headerMap['status']) ? strtoupper(trim((string) ($row[$headerMap['status']] ?? ''))) : 'ACTIVE';
                if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                    $status = 'ACTIVE';
                }

                $remarks = isset($headerMap['remarks']) ? trim((string) ($row[$headerMap['remarks']] ?? '')) : null;

                // Upsert loading rate
                $existing = LoadingRate::where('item_code', $itemCode)
                    ->where('rate_type_id', $targetRateType->id)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'item_name'  => $itemName ?: $existing->item_name,
                        'rate_type'  => $targetRateType->code,
                        'uom'        => $uom ?: $existing->uom,
                        'rate'       => $rateValue,
                        'status'     => $status,
                        'remarks'    => $remarks ?: $existing->remarks,
                        'updated_by' => $userId,
                    ]);
                    $updated++;
                } else {
                    LoadingRate::create([
                        'item_code'    => $itemCode,
                        'item_name'    => $itemName ?: $itemCode,
                        'rate_type_id' => $targetRateType->id,
                        'rate_type'    => $targetRateType->code,
                        'uom'          => $uom ?: 'PCS',
                        'rate'         => $rateValue,
                        'status'       => $status,
                        'remarks'      => $remarks,
                        'created_by'   => $userId,
                        'updated_by'   => $userId,
                    ]);
                    $created++;
                }

                $processed++;
            }

            DB::connection((new LoadingRate())->getConnectionName())->commit();
        } catch (\Throwable $e) {
            DB::connection((new LoadingRate())->getConnectionName())->rollBack();
            throw new \Exception("Gagal memproses file Excel: " . $e->getMessage(), 500, $e);
        }

        return [
            'total_processed' => $processed,
            'created_count'   => $created,
            'updated_count'   => $updated,
            'error_count'     => count($errors),
            'errors'          => $errors,
        ];
    }

    /**
     * Download sample Excel template for loading rates upload.
     *
     * @return StreamedResponse
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Loading Rates');

        // Headers
        $headers = ['Item Code', 'Item Name', 'Rate Type', 'Rate', 'UOM', 'Remarks'];
        $sheet->fromArray($headers, null, 'A1');

        // Sample rows
        $samples = [
            ['SKU-001', 'Garam Kasar 50kg', 'REGULAR', 1500, 'SAK', 'Tarif reguler bongkar muat kuli'],
            ['SKU-001', 'Garam Kasar 50kg', 'OVERTIME', 2500, 'SAK', 'Tarif lembur bongkar muat kuli'],
            ['SKU-002', 'Garam Halus 25kg', 'REGULAR', 1000, 'SAK', 'Tarif reguler'],
            ['SKU-002', 'Garam Halus 25kg', 'OVERTIME', 1750, 'SAK', 'Tarif lembur'],
        ];
        $sheet->fromArray($samples, null, 'A2');

        // Style header
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'template_master_loading_rates.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Normalize rate type aliases to canonical codes: REGULAR or OVERTIME.
     */
    protected function normalizeRateType(string $raw): string
    {
        $clean = strtoupper(trim($raw));
        if (in_array($clean, ['REGULAR', 'REGULER', 'REG', 'R', 'NORMAL'], true) || $clean === '') {
            return LoadingRateType::TYPE_REGULAR;
        }
        if (in_array($clean, ['OVERTIME', 'LEMBUR', 'OT', 'L'], true)) {
            return LoadingRateType::TYPE_OVERTIME;
        }
        return $clean;
    }

    /**
     * Flexible case-insensitive header mapping.
     */
    protected function mapHeaders(array $rawHeaders, array $definitions): array
    {
        $map = [];
        $cleanHeaders = array_map(function ($h) {
            return strtolower(trim(str_replace([' ', '-', '_', '.'], '', (string) $h)));
        }, $rawHeaders);

        foreach ($definitions as $key => $aliases) {
            foreach ($aliases as $alias) {
                $cleanAlias = strtolower(str_replace([' ', '-', '_', '.'], '', $alias));
                $foundIndex = array_search($cleanAlias, $cleanHeaders, true);
                if ($foundIndex !== false) {
                    $map[$key] = $foundIndex;
                    break;
                }
            }
        }

        return $map;
    }
}
