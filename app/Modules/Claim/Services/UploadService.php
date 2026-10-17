<?php

namespace App\Modules\Claim\Services;

use App\Modules\Claim\Repositories\UploadRepositoryInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UploadService
{
    /**
     * @var UploadRepositoryInterface
     */
    protected UploadRepositoryInterface $uploadRepository;

    /**
     * @var ClaimCalculationService
     */
    protected ClaimCalculationService $calculationService;

    /**
     * UploadService constructor.
     *
     * @param UploadRepositoryInterface $uploadRepository
     * @param ClaimCalculationService $calculationService
     */
    public function __construct(
        UploadRepositoryInterface $uploadRepository,
        ClaimCalculationService $calculationService
    ) {
        $this->uploadRepository = $uploadRepository;
        $this->calculationService = $calculationService;
    }

    /**
     * Generate unique batch number.
     *
     * @return string
     */
    public function generateBatchNo()
    {
        $dateStr = date('Ymd');
        $prefix = "UPLOAD-{$dateStr}-";

        $lastBatch = DB::table('trx_program_upload_batch')
            ->where('batch_no', 'like', $prefix . '%')
            ->orderBy('batch_no', 'desc')
            ->first();

        $num = 1;
        if ($lastBatch) {
            $lastNum = intval(substr($lastBatch->batch_no, -3));
            $num = $lastNum + 1;
        }

        return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Handle Excel upload, parsing, validation, database storing and automatic calculation.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string|null $uploadedBy
     * @return array
     * @throws ValidationException
     */
    public function handleUpload($file, $uploadedBy)
    {
        // 1. Read file rows
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // 2. Find headers
        $headerRowIndex = null;
        $headersMap = [];

        foreach ($rows as $index => $row) {
            $trimmedRow = array_map(function($val) {
                return $val !== null ? trim($val) : '';
            }, $row);

            $hasCustomer = false;
            $hasItem = false;
            foreach ($trimmedRow as $colName) {
                if (in_array($colName, ['Kode Customer', 'Kode Distributor'])) {
                    $hasCustomer = true;
                }
                if (in_array($colName, ['Item', 'Kode Item'])) {
                    $hasItem = true;
                }
            }

            if ($hasCustomer && $hasItem) {
                $headerRowIndex = $index;
                foreach ($trimmedRow as $colIndex => $colName) {
                    $headersMap[$colName] = $colIndex;
                }
                break;
            }
        }

        // 1. Distributor code & name
        $colDistributorCode = $headersMap['Kode Distributor'] ?? $headersMap['Distributor Code'] ?? null;
        $colCustomerCode    = $colDistributorCode ?? $headersMap['Kode Customer'] ?? $headersMap['Customer Code'] ?? null;
        $colCustomerName    = $headersMap['Nama Distributor'] ?? $headersMap['Distributor Name'] ?? null;

        // 2. Downstream customer (Outlet)
        if ($colDistributorCode !== null) {
            $colDistCustomerCode = $headersMap['Kode Customer'] ?? $headersMap['Customer Code'] ?? $headersMap['Kode Outlet'] ?? $headersMap['Kode Customer (Distributor)'] ?? null;
            $colDistCustomerName = $headersMap['Nama Customer'] ?? $headersMap['Customer Name'] ?? $headersMap['Nama Outlet'] ?? $headersMap['Nama Customer (Distributor)'] ?? null;
        } else {
            $colDistCustomerCode = $headersMap['Kode Outlet'] ?? $headersMap['Kode Customer (Distributor)'] ?? null;
            $colDistCustomerName = $headersMap['Nama Outlet'] ?? $headersMap['Nama Customer (Distributor)'] ?? null;
        }

        // 3. Extended location & salesman
        $colDepo         = $headersMap['Depo'] ?? $headersMap['depo'] ?? $headersMap['Cabang'] ?? null;
        $colAddress      = $headersMap['Alamat'] ?? $headersMap['alamat'] ?? $headersMap['Address'] ?? null;
        $colCity         = $headersMap['Kota / Kabupaten'] ?? $headersMap['Kota/Kabupaten'] ?? $headersMap['Kota'] ?? $headersMap['Kabupaten'] ?? $headersMap['City'] ?? null;
        $colDistrict     = $headersMap['Kecamatan'] ?? $headersMap['kecamatan'] ?? $headersMap['District'] ?? null;
        $colSalesmanName = $headersMap['Salesman Distributor'] ?? $headersMap['Salesman'] ?? $headersMap['Nama Salesman'] ?? $headersMap['Salesman Name'] ?? null;

        // 4. Item columns
        $colItemCode     = $headersMap['Item'] ?? $headersMap['Kode Item'] ?? $headersMap['Item Code'] ?? null;
        $colItemName     = $headersMap['Nama Item'] ?? $headersMap['Item Name'] ?? null;
        $colItemCategory = $headersMap['Jenis Barang'] ?? $headersMap['Kategori Barang'] ?? $headersMap['Item Category'] ?? null;

        // 5. Price, Qty, Customer Type, Transaction Date
        $colSellPrice    = $headersMap['Harga Jual @ Kg'] ?? $headersMap['Harga Jual @Kg'] ?? $headersMap['Harga Jual@Kg'] ?? $headersMap['Harga Jual'] ?? $headersMap['Harga Jual (kg)'] ?? $headersMap['Selling Price'] ?? null;
        $colQty          = $headersMap['Qty @ Kg'] ?? $headersMap['Qty @Kg'] ?? $headersMap['Qty@Kg'] ?? $headersMap['Qty'] ?? $headersMap['Qty (kg)'] ?? $headersMap['Quantity'] ?? null;
        $colCustomerType = $headersMap['Type Customer'] ?? $headersMap['Tipe Customer'] ?? $headersMap['Customer Type'] ?? null;
        $colDate         = $headersMap['Transaction Date'] ?? $headersMap['Transcation Date'] ?? $headersMap['Tanggal Transaksi'] ?? null;

        // 6. Transaction classification & invoice
        $colTransactionType = $headersMap['Jenis Transaksi'] ?? $headersMap['Tipe Transaksi'] ?? $headersMap['Transaction Type'] ?? null;
        $colChannel         = $headersMap['Channel'] ?? $headersMap['channel'] ?? null;
        $colInvoiceNo       = $headersMap['Invoice'] ?? $headersMap['No Invoice'] ?? $headersMap['No. Invoice'] ?? $headersMap['Nomor Invoice'] ?? $headersMap['Invoice No'] ?? null;

        // 7. Amounts & Discounts
        $colDpp     = $headersMap['DPP'] ?? $headersMap['dpp'] ?? null;
        $colDiskon1 = $headersMap['Diskon 1'] ?? $headersMap['diskon_1'] ?? $headersMap['Diskon1'] ?? null;
        $colDiskon2 = $headersMap['Diskon 2'] ?? $headersMap['diskon_2'] ?? $headersMap['Diskon2'] ?? null;
        $colDiskon3 = $headersMap['Diskon 3'] ?? $headersMap['diskon_3'] ?? $headersMap['Diskon3'] ?? null;
        $colDiskon4 = $headersMap['Diskon 4'] ?? $headersMap['diskon_4'] ?? $headersMap['Diskon4'] ?? null;
        $colDiskon5 = $headersMap['Diskon 5'] ?? $headersMap['diskon_5'] ?? $headersMap['Diskon5'] ?? null;
        $colPpn     = $headersMap['PPN'] ?? $headersMap['ppn'] ?? null;
        $colNet     = $headersMap['NET'] ?? $headersMap['Net'] ?? $headersMap['net'] ?? null;

        if ($colCustomerCode === null || $colItemCode === null || $colQty === null || $colCustomerType === null || $colDate === null) {
            throw ValidationException::withMessages([
                'file' => ['Struktur header Excel tidak valid. Kolom wajib (Kode Distributor, Item/Kode Item, Qty, Type/Tipe Customer, Transaction Date) harus tersedia.']
            ]);
        }

        // 3. Process & Validate Rows
        $errors = [];
        $validatedData = [];

        $parseAmount = function ($idx, $row) {
            if ($idx === null || !isset($row[$idx])) return null;
            $val = trim((string)$row[$idx]);
            if ($val === '') return null;
            $cleaned = str_replace([',', ' '], '', $val);
            return is_numeric($cleaned) ? (float)$cleaned : null;
        };

        $parseStr = function ($idx, $row) {
            if ($idx === null || !isset($row[$idx])) return null;
            $val = trim((string)$row[$idx]);
            return $val !== '' ? $val : null;
        };

        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Check if completely empty or contains instructions starting with '*'
            $isEmpty = true;
            $isInstruction = false;
            foreach ($row as $cell) {
                if ($cell !== null && trim($cell) !== '') {
                    $isEmpty = false;
                    if (str_starts_with(trim($cell), '*')) {
                        $isInstruction = true;
                        break;
                    }
                }
            }
            if ($isEmpty || $isInstruction) {
                continue;
            }

            $rowNum = $i + 1;
            $rowErrors = [];

            $customerCode = isset($row[$colCustomerCode]) ? trim($row[$colCustomerCode]) : '';
            $customerName = ($colCustomerName !== null && isset($row[$colCustomerName])) ? trim($row[$colCustomerName]) : '';
            $itemCode     = isset($row[$colItemCode]) ? trim($row[$colItemCode]) : '';
            $itemName     = ($colItemName !== null && isset($row[$colItemName])) ? trim($row[$colItemName]) : '';
            
            $rawSellPrice = isset($row[$colSellPrice]) ? $row[$colSellPrice] : 0.0;
            $sellPrice    = floatval(str_replace(',', '', (string)$rawSellPrice));

            $rawQty       = isset($row[$colQty]) ? $row[$colQty] : 0.0;
            $qty          = floatval(str_replace(',', '', (string)$rawQty));

            $customerType = isset($row[$colCustomerType]) ? strtoupper(trim($row[$colCustomerType])) : '';
            $rawDate      = isset($row[$colDate]) ? $row[$colDate] : '';
            $date         = $this->parseExcelDate($rawDate);

            if (empty($customerCode)) {
                $rowErrors[] = "Kode Distributor tidak boleh kosong.";
            }
            if (empty($itemCode)) {
                $rowErrors[] = "Kode Item tidak boleh kosong.";
            }
            if ($qty <= 0) {
                $rowErrors[] = "Qty harus lebih besar dari 0.";
            }
            if (empty($customerType) || !in_array($customerType, ['GT', 'MT'])) {
                $rowErrors[] = "Type Customer harus GT atau MT.";
            }
            if (!$date) {
                $rowErrors[] = "Transaction Date tidak valid (Nilai: '{$rawDate}').";
            }

            if (!empty($rowErrors)) {
                $errors[$rowNum] = $rowErrors;
            } else {
                $validatedData[] = [
                    'customer_code'             => $customerCode,
                    'customer_name'             => $customerName,
                    'depo'                      => $parseStr($colDepo, $row),
                    'distributor_customer_code' => $parseStr($colDistCustomerCode, $row),
                    'distributor_customer_name' => $parseStr($colDistCustomerName, $row),
                    'address'                   => $parseStr($colAddress, $row),
                    'city'                      => $parseStr($colCity, $row),
                    'district'                  => $parseStr($colDistrict, $row),
                    'salesman_name'             => $parseStr($colSalesmanName, $row),
                    'item_code'                 => $itemCode,
                    'item_name'                 => $itemName,
                    'item_category'             => $parseStr($colItemCategory, $row),
                    'sell_price_per_kg'         => $sellPrice,
                    'qty_kg'                    => $qty,
                    'customer_type'             => $customerType,
                    'transaction_type'          => $parseStr($colTransactionType, $row),
                    'channel'                   => $parseStr($colChannel, $row),
                    'invoice_no'                => $parseStr($colInvoiceNo, $row),
                    'dpp'                       => $parseAmount($colDpp, $row),
                    'diskon_1'                  => $parseAmount($colDiskon1, $row),
                    'diskon_2'                  => $parseAmount($colDiskon2, $row),
                    'diskon_3'                  => $parseAmount($colDiskon3, $row),
                    'diskon_4'                  => $parseAmount($colDiskon4, $row),
                    'diskon_5'                  => $parseAmount($colDiskon5, $row),
                    'ppn'                       => $parseAmount($colPpn, $row),
                    'net'                       => $parseAmount($colNet, $row),
                    'transaction_date'          => $date,
                ];
            }
        }

        if (!empty($errors)) {
            $formattedErrors = [];
            foreach ($errors as $rowNum => $msgs) {
                $formattedErrors["Baris {$rowNum}"] = $msgs;
            }

            $firstKey = array_key_first($errors);
            $firstErrorMsg = $errors[$firstKey][0];
            $detailedMessage = "File Excel berisi data tidak valid. Baris {$firstKey}: {$firstErrorMsg}";

            throw ValidationException::withMessages([
                'file' => [$detailedMessage],
                'details' => [$formattedErrors]
            ]);
        }

        // 4. Save Batch and Rows in Database transaction
        return DB::transaction(function () use ($validatedData, $file, $uploadedBy) {
            $batchNo = $this->generateBatchNo();

            $firstDepo = null;
            foreach ($validatedData as $vRow) {
                if (!empty($vRow['depo'])) {
                    $firstDepo = $vRow['depo'];
                    break;
                }
            }

            $batch = $this->uploadRepository->createBatch([
                'batch_no'    => $batchNo,
                'file_name'   => $file->getClientOriginalName(),
                'uploaded_by' => $uploadedBy,
                'depo'        => $firstDepo,
            ]);

            $rowsToInsert = array_map(function ($row) use ($batch) {
                $row['batch_id'] = $batch->id;
                $row['uploaded_at'] = Carbon::now();
                return $row;
            }, $validatedData);

            $this->uploadRepository->insertUploadRows($rowsToInsert);

            // 5. Trigger Claim Calculation
            $summary = $this->calculationService->calculateBatch($batch->id);

            return array_merge([
                'batch_id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'depo'     => $batch->depo,
            ], $summary);
        });
    }

    /**
     * Parse excel cell date into Y-m-d string format.
     */
    private function parseExcelDate($value)
    {
        if (empty($value)) return null;

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                // Fallback
            }
        }

        $formats = [
            'Y-m-d',
            'd-m-Y',
            'd/m/Y',
            'm/d/Y',
            'Y/m/d',
            'd-M-Y',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, trim($value))->format('Y-m-d');
            } catch (\Exception $e) {
                // Continue
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * List paginated batches.
     *
     * @param array $customerCodes
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listBatches(array $customerCodes = [], int $perPage = 15)
    {
        return $this->uploadRepository->getBatchesPaginated($customerCodes, $perPage);
    }

    /**
     * Get details of a single batch with its calculation summary.
     *
     * @param int $id
     * @return array|null
     */
    public function getBatchDetail(int $id)
    {
        return $this->uploadRepository->findBatchWithSummary($id);
    }

    /**
     * Process validated transaction rows directly (e.g. from JSON payload),
     * store batch, store rows, and execute claim calculation.
     *
     * @param array $validatedData
     * @param string $uploadedBy
     * @param string|null $fileName
     * @param string|null $distributorRefNo
     * @param string|null $depo
     * @return array
     */
    public function processTransactions(array $validatedData, string $uploadedBy, ?string $fileName = null, ?string $distributorRefNo = null, ?string $depo = null): array
    {
        return DB::transaction(function () use ($validatedData, $uploadedBy, $fileName, $distributorRefNo, $depo) {
            $batchNo = $this->generateBatchNo();
            $batch = $this->uploadRepository->createBatch([
                'batch_no'           => $batchNo,
                'file_name'          => $fileName ?: ('API_CLAIM_' . ($distributorRefNo ?: date('YmdHis'))),
                'uploaded_by'        => $uploadedBy,
                'distributor_ref_no' => $distributorRefNo,
                'depo'               => $depo,
            ]);

            $rowsToInsert = array_map(function ($row) use ($batch, $depo) {
                $row['batch_id'] = $batch->id;
                $row['uploaded_at'] = Carbon::now();
                if (!isset($row['depo']) && $depo !== null) {
                    $row['depo'] = $depo;
                }
                return $row;
            }, $validatedData);

            $this->uploadRepository->insertUploadRows($rowsToInsert);

            // Trigger Claim Calculation
            $summary = $this->calculationService->calculateBatch($batch->id);

            return array_merge([
                'batch_id'           => $batch->id,
                'batch_no'           => $batch->batch_no,
                'distributor_ref_no' => $batch->distributor_ref_no,
                'depo'               => $batch->depo,
            ], $summary);
        });
    }

    /**
     * Parse excel file specifically for a given distributor, verifying that
     * customer code matches the authenticated distributor.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $expectedCustomerCode
     * @return array
     * @throws ValidationException
     */
    public function parseExcelForDistributor($file, string $expectedCustomerCode): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $headerRowIndex = null;
        $headersMap = [];

        foreach ($rows as $index => $row) {
            $trimmedRow = array_map(function($val) {
                return $val !== null ? trim($val) : '';
            }, $row);

            $hasCustomer = false;
            $hasItem = false;
            foreach ($trimmedRow as $colName) {
                if (in_array($colName, ['Kode Customer', 'Kode Distributor'])) {
                    $hasCustomer = true;
                }
                if (in_array($colName, ['Item', 'Kode Item'])) {
                    $hasItem = true;
                }
            }

            if ($hasCustomer && $hasItem) {
                $headerRowIndex = $index;
                foreach ($trimmedRow as $colIndex => $colName) {
                    $headersMap[$colName] = $colIndex;
                }
                break;
            }
        }

        // 1. Distributor code & name
        $colDistributorCode = $headersMap['Kode Distributor'] ?? $headersMap['Distributor Code'] ?? null;
        $colCustomerCode    = $colDistributorCode ?? $headersMap['Kode Customer'] ?? $headersMap['Customer Code'] ?? null;
        $colCustomerName    = $headersMap['Nama Distributor'] ?? $headersMap['Distributor Name'] ?? null;

        // 2. Downstream customer (Outlet)
        if ($colDistributorCode !== null) {
            $colDistCustomerCode = $headersMap['Kode Customer'] ?? $headersMap['Customer Code'] ?? $headersMap['Kode Outlet'] ?? $headersMap['Kode Customer (Distributor)'] ?? null;
            $colDistCustomerName = $headersMap['Nama Customer'] ?? $headersMap['Customer Name'] ?? $headersMap['Nama Outlet'] ?? $headersMap['Nama Customer (Distributor)'] ?? null;
        } else {
            $colDistCustomerCode = $headersMap['Kode Outlet'] ?? $headersMap['Kode Customer (Distributor)'] ?? null;
            $colDistCustomerName = $headersMap['Nama Outlet'] ?? $headersMap['Nama Customer (Distributor)'] ?? null;
        }

        // 3. Extended location & salesman
        $colDepo         = $headersMap['Depo'] ?? $headersMap['depo'] ?? $headersMap['Cabang'] ?? null;
        $colAddress      = $headersMap['Alamat'] ?? $headersMap['alamat'] ?? $headersMap['Address'] ?? null;
        $colCity         = $headersMap['Kota / Kabupaten'] ?? $headersMap['Kota/Kabupaten'] ?? $headersMap['Kota'] ?? $headersMap['Kabupaten'] ?? $headersMap['City'] ?? null;
        $colDistrict     = $headersMap['Kecamatan'] ?? $headersMap['kecamatan'] ?? $headersMap['District'] ?? null;
        $colSalesmanName = $headersMap['Salesman Distributor'] ?? $headersMap['Salesman'] ?? $headersMap['Nama Salesman'] ?? $headersMap['Salesman Name'] ?? null;

        // 4. Item columns
        $colItemCode     = $headersMap['Item'] ?? $headersMap['Kode Item'] ?? $headersMap['Item Code'] ?? null;
        $colItemName     = $headersMap['Nama Item'] ?? $headersMap['Item Name'] ?? null;
        $colItemCategory = $headersMap['Jenis Barang'] ?? $headersMap['Kategori Barang'] ?? $headersMap['Item Category'] ?? null;

        // 5. Price, Qty, Customer Type, Transaction Date
        $colSellPrice    = $headersMap['Harga Jual @ Kg'] ?? $headersMap['Harga Jual @Kg'] ?? $headersMap['Harga Jual@Kg'] ?? $headersMap['Harga Jual'] ?? $headersMap['Harga Jual (kg)'] ?? $headersMap['Selling Price'] ?? null;
        $colQty          = $headersMap['Qty @ Kg'] ?? $headersMap['Qty @Kg'] ?? $headersMap['Qty@Kg'] ?? $headersMap['Qty'] ?? $headersMap['Qty (kg)'] ?? $headersMap['Quantity'] ?? null;
        $colCustomerType = $headersMap['Type Customer'] ?? $headersMap['Tipe Customer'] ?? $headersMap['Customer Type'] ?? null;
        $colDate         = $headersMap['Transaction Date'] ?? $headersMap['Transcation Date'] ?? $headersMap['Tanggal Transaksi'] ?? null;

        // 6. Transaction classification & invoice
        $colTransactionType = $headersMap['Jenis Transaksi'] ?? $headersMap['Tipe Transaksi'] ?? $headersMap['Transaction Type'] ?? null;
        $colChannel         = $headersMap['Channel'] ?? $headersMap['channel'] ?? null;
        $colInvoiceNo       = $headersMap['Invoice'] ?? $headersMap['No Invoice'] ?? $headersMap['No. Invoice'] ?? $headersMap['Nomor Invoice'] ?? $headersMap['Invoice No'] ?? null;

        // 7. Amounts & Discounts
        $colDpp     = $headersMap['DPP'] ?? $headersMap['dpp'] ?? null;
        $colDiskon1 = $headersMap['Diskon 1'] ?? $headersMap['diskon_1'] ?? $headersMap['Diskon1'] ?? null;
        $colDiskon2 = $headersMap['Diskon 2'] ?? $headersMap['diskon_2'] ?? $headersMap['Diskon2'] ?? null;
        $colDiskon3 = $headersMap['Diskon 3'] ?? $headersMap['diskon_3'] ?? $headersMap['Diskon3'] ?? null;
        $colDiskon4 = $headersMap['Diskon 4'] ?? $headersMap['diskon_4'] ?? $headersMap['Diskon4'] ?? null;
        $colDiskon5 = $headersMap['Diskon 5'] ?? $headersMap['diskon_5'] ?? $headersMap['Diskon5'] ?? null;
        $colPpn     = $headersMap['PPN'] ?? $headersMap['ppn'] ?? null;
        $colNet     = $headersMap['NET'] ?? $headersMap['Net'] ?? $headersMap['net'] ?? null;

        if ($colCustomerCode === null || $colItemCode === null || $colQty === null || $colCustomerType === null || $colDate === null) {
            throw ValidationException::withMessages([
                'file' => ['Struktur header Excel tidak valid. Kolom wajib (Kode Distributor, Item/Kode Item, Qty, Type/Tipe Customer, Transaction Date) harus tersedia.']
            ]);
        }

        $errors = [];
        $validatedData = [];

        $parseAmount = function ($idx, $row) {
            if ($idx === null || !isset($row[$idx])) return null;
            $val = trim((string)$row[$idx]);
            if ($val === '') return null;
            $cleaned = str_replace([',', ' '], '', $val);
            return is_numeric($cleaned) ? (float)$cleaned : null;
        };

        $parseStr = function ($idx, $row) {
            if ($idx === null || !isset($row[$idx])) return null;
            $val = trim((string)$row[$idx]);
            return $val !== '' ? $val : null;
        };

        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            $isEmpty = true;
            $isInstruction = false;
            foreach ($row as $cell) {
                if ($cell !== null && trim($cell) !== '') {
                    $isEmpty = false;
                    if (str_starts_with(trim($cell), '*')) {
                        $isInstruction = true;
                        break;
                    }
                }
            }
            if ($isEmpty || $isInstruction) {
                continue;
            }

            $rowNum = $i + 1;
            $rowErrors = [];

            $customerCode = isset($row[$colCustomerCode]) ? trim($row[$colCustomerCode]) : '';
            $customerName = ($colCustomerName !== null && isset($row[$colCustomerName])) ? trim($row[$colCustomerName]) : '';
            $itemCode     = isset($row[$colItemCode]) ? trim($row[$colItemCode]) : '';
            $itemName     = ($colItemName !== null && isset($row[$colItemName])) ? trim($row[$colItemName]) : '';

            $rawSellPrice = isset($row[$colSellPrice]) ? $row[$colSellPrice] : 0.0;
            $sellPrice    = floatval(str_replace(',', '', (string)$rawSellPrice));

            $rawQty       = isset($row[$colQty]) ? $row[$colQty] : 0.0;
            $qty          = floatval(str_replace(',', '', (string)$rawQty));

            $customerType = isset($row[$colCustomerType]) ? strtoupper(trim($row[$colCustomerType])) : '';
            $rawDate      = isset($row[$colDate]) ? $row[$colDate] : '';
            $date         = $this->parseExcelDate($rawDate);

            if (empty($customerCode)) {
                $rowErrors[] = "Kode Distributor tidak boleh kosong.";
            } elseif ($customerCode !== $expectedCustomerCode) {
                $rowErrors[] = "Kode Distributor '{$customerCode}' tidak sesuai dengan distributor yang terautentikasi ('{$expectedCustomerCode}').";
            }

            if (empty($itemCode)) {
                $rowErrors[] = "Kode Item tidak boleh kosong.";
            }
            if ($qty <= 0) {
                $rowErrors[] = "Qty harus lebih besar dari 0.";
            }
            if (empty($customerType) || !in_array($customerType, ['GT', 'MT'])) {
                $rowErrors[] = "Type Customer harus GT atau MT.";
            }
            if (!$date) {
                $rowErrors[] = "Transaction Date tidak valid (Nilai: '{$rawDate}').";
            }

            if (!empty($rowErrors)) {
                $errors[$rowNum] = $rowErrors;
            } else {
                $validatedData[] = [
                    'customer_code'             => $customerCode,
                    'customer_name'             => $customerName,
                    'depo'                      => $parseStr($colDepo, $row),
                    'distributor_customer_code' => $parseStr($colDistCustomerCode, $row),
                    'distributor_customer_name' => $parseStr($colDistCustomerName, $row),
                    'address'                   => $parseStr($colAddress, $row),
                    'city'                      => $parseStr($colCity, $row),
                    'district'                  => $parseStr($colDistrict, $row),
                    'salesman_name'             => $parseStr($colSalesmanName, $row),
                    'item_code'                 => $itemCode,
                    'item_name'                 => $itemName,
                    'item_category'             => $parseStr($colItemCategory, $row),
                    'sell_price_per_kg'         => $sellPrice,
                    'qty_kg'                    => $qty,
                    'customer_type'             => $customerType,
                    'transaction_type'          => $parseStr($colTransactionType, $row),
                    'channel'                   => $parseStr($colChannel, $row),
                    'invoice_no'                => $parseStr($colInvoiceNo, $row),
                    'dpp'                       => $parseAmount($colDpp, $row),
                    'diskon_1'                  => $parseAmount($colDiskon1, $row),
                    'diskon_2'                  => $parseAmount($colDiskon2, $row),
                    'diskon_3'                  => $parseAmount($colDiskon3, $row),
                    'diskon_4'                  => $parseAmount($colDiskon4, $row),
                    'diskon_5'                  => $parseAmount($colDiskon5, $row),
                    'ppn'                       => $parseAmount($colPpn, $row),
                    'net'                       => $parseAmount($colNet, $row),
                    'transaction_date'          => $date,
                ];
            }
        }

        if (!empty($errors)) {
            $formattedErrors = [];
            foreach ($errors as $rowNum => $msgs) {
                $formattedErrors["Baris {$rowNum}"] = $msgs;
            }
            $firstKey = array_key_first($errors);
            $firstErrorMsg = $errors[$firstKey][0];
            throw ValidationException::withMessages([
                'file' => ["File Excel berisi data tidak valid. Baris {$firstKey}: {$firstErrorMsg}"],
                'details' => [$formattedErrors]
            ]);
        }

        return $validatedData;
    }

    /**
     * Delete a batch and its associated details.
     *
     * @param int $id
     * @return bool
     */
    public function deleteBatch(int $id)
    {
        return $this->uploadRepository->deleteBatch($id);
    }
}
