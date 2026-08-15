<?php

namespace App\Modules\Ekspedisi\Services;

use App\Models\Expedition;
use App\Models\ExpeditionRate;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ExpeditionUploadService
{
    /**
     * Upload and process master expeditions Excel/CSV file.
     *
     * @param UploadedFile $file
     * @return array
     */
    public function uploadExpeditions(UploadedFile $file): array
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
            'expedition_code' => ['expedition_code', 'kode_ekspedisi', 'kode'],
            'expedition_name' => ['expedition_name', 'nama_ekspedisi', 'nama', 'nama_vendor'],
            'address'         => ['address', 'alamat'],
            'city'            => ['city', 'kota'],
            'province'        => ['province', 'provinsi'],
            'postal_code'     => ['postal_code', 'kode_pos'],
            'pic_name'        => ['pic_name', 'pic', 'nama_pic'],
            'pic_phone'       => ['pic_phone', 'no_hp_pic', 'no_hp', 'telepon'],
            'email'           => ['email'],
            'npwp'            => ['npwp'],
            'vehicle_type'    => ['vehicle_type', 'jenis_kendaraan'],
            'transport_mode'  => ['transport_mode', 'moda_pengiriman', 'moda'],
            'status'          => ['status'],
        ]);

        if (!isset($headerMap['expedition_name'])) {
            throw new \Exception('Header "expedition_name" atau "nama_ekspedisi" wajib ada dalam file.');
        }

        $processed = 0;
        $created = 0;
        $updated = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // Row 1 is header
                
                // Skip empty row
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $name = trim((string) ($this->getValueByMap($row, $headerMap, 'expedition_name') ?? ''));

                if (empty($name)) {
                    $errors[] = "Baris #{$rowNum}: Nama ekspedisi kosong.";
                    continue;
                }

                $code = trim((string) ($this->getValueByMap($row, $headerMap, 'expedition_code') ?? ''));
                
                if (empty($code)) {
                    $code = Expedition::generateCode();
                }

                $payload = [
                    'expedition_name' => $name,
                    'address'         => $this->getValueByMap($row, $headerMap, 'address'),
                    'city'            => $this->getValueByMap($row, $headerMap, 'city'),
                    'province'        => $this->getValueByMap($row, $headerMap, 'province'),
                    'postal_code'     => $this->getValueByMap($row, $headerMap, 'postal_code'),
                    'pic_name'        => $this->getValueByMap($row, $headerMap, 'pic_name'),
                    'pic_phone'       => $this->getValueByMap($row, $headerMap, 'pic_phone'),
                    'email'           => $this->getValueByMap($row, $headerMap, 'email'),
                    'npwp'            => $this->getValueByMap($row, $headerMap, 'npwp'),
                    'vehicle_type'    => $this->getValueByMap($row, $headerMap, 'vehicle_type'),
                    'transport_mode'  => $this->getValueByMap($row, $headerMap, 'transport_mode'),
                    'status'          => strtoupper((string) ($this->getValueByMap($row, $headerMap, 'status') ?? 'ACTIVE')),
                    'updated_by'      => auth()->id(),
                ];

                $expedition = Expedition::where('expedition_code', $code)->first();

                if ($expedition) {
                    $expedition->update($payload);
                    $updated++;
                } else {
                    $payload['expedition_code'] = $code;
                    $payload['created_by'] = auth()->id();
                    Expedition::create($payload);
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
     * Upload and process master expedition rates Excel/CSV file.
     *
     * @param UploadedFile $file
     * @return array
     */
    public function uploadRates(UploadedFile $file): array
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
            'expedition_code'  => ['expedition_code', 'kode_ekspedisi', 'expedition_id', 'expedition'],
            'warehouse_code'   => ['warehouse_code', 'whs_code', 'kode_gudang', 'warehouse_id', 'origin'],
            'destination_id'   => ['destination_id', 'tujuan_id', 'tujuan', 'destination'],
            'transport_mode'   => ['transport_mode', 'moda', 'moda_pengiriman'],
            'service_type'     => ['service_type', 'jenis_layanan', 'layanan'],
            'min_tonnage'      => ['min_tonnage', 'tonase_min', 'tonase_minimal', 'min_kg'],
            'max_tonnage'      => ['max_tonnage', 'tonase_max', 'tonase_maksimal', 'max_kg'],
            'price'            => ['price', 'harga', 'tarif', 'rate'],
            'eta_days'         => ['eta_days', 'eta_hari', 'eta'],
            'min_shipment_qty' => ['min_shipment_qty', 'minimal_pengiriman'],
            'max_shipment_qty' => ['max_shipment_qty', 'maksimal_pengiriman'],
            'valid_from'       => ['valid_from', 'berlaku_mulai'],
            'valid_until'      => ['valid_until', 'berlaku_sampai'],
            'status'           => ['status'],
            'remarks'          => ['remarks', 'keterangan'],
        ]);

        if (!isset($headerMap['expedition_code'])) {
            throw new \Exception('Header "expedition_code" atau "kode_ekspedisi" wajib ada dalam file.');
        }

        if (!isset($headerMap['price'])) {
            throw new \Exception('Header "price" atau "harga" wajib ada dalam file.');
        }

        $batchId = $this->generateRateBatchId();
        $processed = 0;
        $created = 0;
        $errors = [];

        // Pre-fetch expeditions & warehouses for fast lookup
        $expeditionMap = Expedition::pluck('id', 'expedition_code')->toArray();
        $warehouseMap  = Warehouse::pluck('id', 'whs_code')->toArray();

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $expCode = trim((string) ($this->getValueByMap($row, $headerMap, 'expedition_code') ?? ''));
                if (str_contains($expCode, '-')) {
                    $expCode = trim(explode('-', $expCode)[0]);
                }

                // Find expedition ID either by code or numeric ID
                $expeditionId = null;
                if (isset($expeditionMap[$expCode])) {
                    $expeditionId = $expeditionMap[$expCode];
                } elseif (is_numeric($expCode)) {
                    $expeditionId = (int) $expCode;
                }

                if (!$expeditionId) {
                    $errors[] = "Baris #{$rowNum}: Ekspedisi dengan kode '{$expCode}' tidak ditemukan.";
                    continue;
                }

                $priceVal = $this->getValueByMap($row, $headerMap, 'price');
                if (!is_numeric($priceVal) || floatval($priceVal) < 0) {
                    $errors[] = "Baris #{$rowNum}: Harga/tarif tidak valid.";
                    continue;
                }

                // Match warehouse ID by code or name
                $whsInput = trim((string) ($this->getValueByMap($row, $headerMap, 'warehouse_code') ?? ''));
                $whsCode = $whsInput;
                if (str_contains($whsCode, '-')) {
                    $whsCode = trim(explode('-', $whsCode)[0]);
                }
                
                $warehouseId = null;
                if (isset($warehouseMap[$whsCode])) {
                    $warehouseId = $warehouseMap[$whsCode];
                } elseif (is_numeric($whsCode)) {
                    $warehouseId = (int) $whsCode;
                } else {
                    $whsObj = Warehouse::where('whs_name', $whsInput)
                        ->orWhere('whs_name', 'LIKE', "%{$whsInput}%")
                        ->orWhere('whs_code', $whsCode)
                        ->first();
                    if ($whsObj) {
                        $warehouseId = $whsObj->id;
                    }
                }

                $destId = $this->getValueByMap($row, $headerMap, 'destination_id');
                $destinationId = null;
                if ($destId !== null) {
                    $destStr = trim((string) $destId);
                    
                    if (is_numeric($destStr)) {
                        $existsAsId = DB::table('customer_shiptos')->where('id', (int) $destStr)->exists();
                        if ($existsAsId) {
                            $destinationId = (int) $destStr;
                        } else {
                            $shipto = DB::table('customer_shiptos')->where('card_code', $destStr)->first();
                            if ($shipto) {
                                $destinationId = $shipto->id;
                            }
                        }
                    } else {
                        // Check exact card_code
                        $shipto = DB::table('customer_shiptos')->where('card_code', $destStr)->first();
                        
                        // If not found, try parsing alias - street format or matching alias/name/street
                        if (!$shipto && str_contains($destStr, ' - ')) {
                            $parts = explode(' - ', $destStr, 2);
                            $aliasPart = trim($parts[0]);
                            $streetPart = trim($parts[1]);

                            $shipto = DB::table('customer_shiptos')
                                ->where(function ($q) use ($aliasPart, $streetPart) {
                                    $q->where('alias', $aliasPart)->orWhere('name', $aliasPart);
                                })
                                ->where(function ($q) use ($streetPart) {
                                    $q->where('street', 'LIKE', "%{$streetPart}%")->orWhere('address', 'LIKE', "%{$streetPart}%");
                                })
                                ->first();

                            if (!$shipto) {
                                $shipto = DB::table('customer_shiptos')
                                    ->where('alias', $aliasPart)
                                    ->orWhere('name', $aliasPart)
                                    ->first();
                            }
                        }

                        if (!$shipto) {
                            $shipto = DB::table('customer_shiptos')
                                ->where('alias', $destStr)
                                ->orWhere('name', $destStr)
                                ->orWhere('street', $destStr)
                                ->first();
                        }

                        if ($shipto) {
                            $destinationId = $shipto->id;
                        }
                    }
                }

                ExpeditionRate::create([
                    'expedition_id'    => $expeditionId,
                    'warehouse_id'     => $warehouseId,
                    'destination_id'   => $destinationId,
                    'transport_mode'   => $this->getValueByMap($row, $headerMap, 'transport_mode'),
                    'service_type'     => $this->getValueByMap($row, $headerMap, 'service_type'),
                    'min_tonnage'      => floatval($this->getValueByMap($row, $headerMap, 'min_tonnage') ?? 0),
                    'max_tonnage'      => floatval($this->getValueByMap($row, $headerMap, 'max_tonnage') ?? 0),
                    'price'            => floatval($priceVal),
                    'eta_days'         => is_numeric($this->getValueByMap($row, $headerMap, 'eta_days')) ? (int) $this->getValueByMap($row, $headerMap, 'eta_days') : null,
                    'min_shipment_qty' => floatval($this->getValueByMap($row, $headerMap, 'min_shipment_qty') ?? 0),
                    'max_shipment_qty' => floatval($this->getValueByMap($row, $headerMap, 'max_shipment_qty') ?? 0),
                    'valid_from'       => $this->parseDate($this->getValueByMap($row, $headerMap, 'valid_from')),
                    'valid_until'      => $this->parseDate($this->getValueByMap($row, $headerMap, 'valid_until')),
                    'status'           => strtoupper((string) ($this->getValueByMap($row, $headerMap, 'status') ?? 'ACTIVE')),
                    'remarks'          => $this->getValueByMap($row, $headerMap, 'remarks'),
                    'upload_batch_id'  => $batchId,
                    'created_by'       => auth()->id(),
                ]);

                $created++;
                $processed++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'upload_batch_id' => $batchId,
            'processed_count' => $processed,
            'created_count'   => $created,
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

    /**
     * Parse date string into Y-m-d format.
     */
    protected function parseDate($dateVal): ?string
    {
        if (empty($dateVal)) {
            return null;
        }
        try {
            return date('Y-m-d', strtotime((string)$dateVal));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Generate rate upload batch ID.
     */
    protected function generateRateBatchId(): string
    {
        $date = date('Ymd');
        $prefix = "BATCH-RATE-{$date}-";

        $last = ExpeditionRate::where('upload_batch_id', 'LIKE', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $num = 1;
        if ($last && $last->upload_batch_id) {
            $lastNum = (int) substr($last->upload_batch_id, -3);
            $num = $lastNum + 1;
        }

        return $prefix . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
    }
}
