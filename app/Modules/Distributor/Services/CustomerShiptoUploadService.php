<?php

namespace App\Modules\Distributor\Services;

use App\Models\CustomerShipto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CustomerShiptoUploadService
{
    /**
     * Upload and process customer shiptos (destinations) Excel/CSV file.
     *
     * @param UploadedFile $file
     * @return array
     */
    public function uploadShiptos(UploadedFile $file): array
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
            'card_code'      => ['card_code', 'kode_customer', 'customer_code', 'cardcode'],
            'alias'          => ['alias', 'nama_alias', 'alias_destination'],
            'transport_mode' => ['transport_mode', 'moda_pengiriman', 'moda', 'transport', 'mode'],
            'street'         => ['street', 'street_address', 'jalan', 'alamat_kirim', 'detail_alamat'],
            'city'           => ['city', 'kota'],
            'address'        => ['address', 'address_id', 'ship_to_code', 'shipto_code', 'kode_shipto', 'kode_alamat'],
            'name'           => ['name', 'nama', 'nama_customer', 'nama_destination', 'customer_name'],
        ]);

        if (!isset($headerMap['card_code'])) {
            throw new \Exception('Header "card_code" atau "kode_customer" wajib ada dalam file.');
        }

        if (!isset($headerMap['alias'])) {
            throw new \Exception('Header "alias" atau "nama_alias" wajib ada dalam file.');
        }

        if (!isset($headerMap['transport_mode'])) {
            throw new \Exception('Header "transport_mode" atau "moda_pengiriman" wajib ada dalam file.');
        }

        if (!isset($headerMap['street'])) {
            throw new \Exception('Header "street" atau "alamat" wajib ada dalam file.');
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

                $cardCode = trim((string) ($this->getValueByMap($row, $headerMap, 'card_code') ?? ''));
                $alias = trim((string) ($this->getValueByMap($row, $headerMap, 'alias') ?? ''));
                $transportModeRaw = trim((string) ($this->getValueByMap($row, $headerMap, 'transport_mode') ?? ''));
                $street = trim((string) ($this->getValueByMap($row, $headerMap, 'street') ?? ''));
                $city = $this->getValueByMap($row, $headerMap, 'city');
                $name = $this->getValueByMap($row, $headerMap, 'name');
                $address = trim((string) ($this->getValueByMap($row, $headerMap, 'address') ?? ''));

                if (empty($cardCode)) {
                    $errors[] = "Baris #{$rowNum}: card_code kosong.";
                    continue;
                }

                if (empty($alias)) {
                    $errors[] = "Baris #{$rowNum}: alias kosong.";
                    continue;
                }

                if (empty($transportModeRaw)) {
                    $errors[] = "Baris #{$rowNum}: transport_mode kosong.";
                    continue;
                }

                if (empty($street)) {
                    $errors[] = "Baris #{$rowNum}: street kosong.";
                    continue;
                }

                // Normalize transport_mode
                $modeUpper = strtoupper($transportModeRaw);
                $transportMode = null;

                if (in_array($modeUpper, ['D', 'DARAT'])) {
                    $transportMode = 'D';
                } elseif (in_array($modeUpper, ['L', 'LAUT'])) {
                    $transportMode = 'L';
                } elseif (in_array($modeUpper, ['U', 'UDARA'])) {
                    $transportMode = 'U';
                } else {
                    $errors[] = "Baris #{$rowNum}: transport_mode '{$transportModeRaw}' tidak valid (harus D/DARAT, L/LAUT, atau U/UDARA).";
                    continue;
                }

                if (empty($address)) {
                    $address = $alias;
                }

                $payload = [
                    'name'           => $name,
                    'alias'          => $alias,
                    'city'           => $city,
                    'street'         => $street,
                    'transport_mode' => $transportMode,
                ];

                $shipto = CustomerShipto::where('card_code', $cardCode)
                    ->where('address', $address)
                    ->first();

                if ($shipto) {
                    $shipto->update($payload);
                    $updated++;
                } else {
                    $payload['card_code'] = $cardCode;
                    $payload['address']   = $address;
                    CustomerShipto::create($payload);
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
     * Get cell value by mapped header key.
     */
    protected function getValueByMap(array $row, array $headerMap, string $key): mixed
    {
        if (!isset($headerMap[$key])) {
            return null;
        }
        $val = $row[$headerMap[$key]] ?? null;
        return $val !== null ? trim((string) $val) : null;
    }

    /**
     * Check if a row is completely empty.
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }
}
