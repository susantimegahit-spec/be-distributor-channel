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
            'name'           => ['name', 'nama', 'nama_customer', 'nama_destination', 'customer_name'],
            'address'        => ['address', 'address_id', 'ship_to_code', 'shipto_code', 'kode_shipto', 'kode_alamat'],
            'city'           => ['city', 'kota'],
            'street'         => ['street', 'street_address', 'jalan', 'alamat_kirim', 'detail_alamat'],
            'alias'          => ['alias', 'nama_alias', 'alias_destination'],
            'transport_mode' => ['transport_mode', 'moda_pengiriman', 'moda', 'transport', 'mode'],
            'created_at'     => ['created_at', 'created_date', 'tanggal_buat'],
            'updated_at'     => ['updated_at', 'updated_date', 'tanggal_update'],
        ]);

        if (!isset($headerMap['card_code'])) {
            throw new \Exception('Header "card_code" atau "kode_customer" wajib ada dalam file.');
        }

        if (!isset($headerMap['name']) && !isset($headerMap['alias']) && !isset($headerMap['address'])) {
            throw new \Exception('Header "name", "address", atau "alias" wajib ada dalam file.');
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
                $name = $this->getValueByMap($row, $headerMap, 'name');
                $address = trim((string) ($this->getValueByMap($row, $headerMap, 'address') ?? ''));
                $city = $this->getValueByMap($row, $headerMap, 'city');
                $street = trim((string) ($this->getValueByMap($row, $headerMap, 'street') ?? ''));
                $alias = trim((string) ($this->getValueByMap($row, $headerMap, 'alias') ?? ''));
                $transportModeRaw = trim((string) ($this->getValueByMap($row, $headerMap, 'transport_mode') ?? ''));

                if (empty($cardCode)) {
                    $errors[] = "Baris #{$rowNum}: card_code kosong.";
                    continue;
                }

                // If address is empty, fallback to alias or name
                if (empty($address)) {
                    $address = !empty($alias) ? $alias : (!empty($name) ? $name : $street);
                }

                // If alias is empty, fallback to address or name
                if (empty($alias)) {
                    $alias = !empty($name) ? $name : $address;
                }

                // If name is empty, fallback to alias or address
                if (empty($name)) {
                    $name = !empty($alias) ? $alias : $address;
                }

                // Normalize transport_mode (optional / fallback to D)
                $transportMode = 'D';
                if (!empty($transportModeRaw)) {
                    $modeUpper = strtoupper($transportModeRaw);
                    if (in_array($modeUpper, ['D', 'DARAT'])) {
                        $transportMode = 'D';
                    } elseif (in_array($modeUpper, ['L', 'LAUT'])) {
                        $transportMode = 'L';
                    } elseif (in_array($modeUpper, ['U', 'UDARA'])) {
                        $transportMode = 'U';
                    } else {
                        // If specific text not recognized, keep raw or fallback
                        $transportMode = in_array($modeUpper, ['D', 'L', 'U']) ? $modeUpper : 'D';
                    }
                }

                $payload = [
                    'name'           => $name,
                    'alias'          => $alias,
                    'city'           => $city,
                    'street'         => $street ?: $address,
                    'transport_mode' => $transportMode,
                ];

                $shipto = CustomerShipto::where('card_code', $cardCode)
                    ->where(function ($q) use ($address, $name) {
                        $q->where('address', $address);
                        if (!empty($name)) {
                            $q->orWhere('name', $name);
                        }
                    })
                    ->first();

                if ($shipto) {
                    $payload['address'] = $address;
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
