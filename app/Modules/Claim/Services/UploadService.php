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

        $colCustomerCode = $headersMap['Kode Customer'] ?? $headersMap['Kode Distributor'] ?? null;
        $colCustomerName = $headersMap['Nama Customer'] ?? null;
        $colItemCode = $headersMap['Item'] ?? $headersMap['Kode Item'] ?? null;
        $colItemName = $headersMap['Nama Item'] ?? null;
        $colSellPrice = $headersMap['Harga Jual @ Kg'] ?? $headersMap['Harga Jual'] ?? $headersMap['Harga Jual (kg)'] ?? $headersMap['Harga Jual@Kg'] ?? null;
        $colQty = $headersMap['Qty @ Kg'] ?? $headersMap['Qty'] ?? $headersMap['Qty@Kg'] ?? $headersMap['Qty (kg)'] ?? null;
        $colCustomerType = $headersMap['Type Customer'] ?? $headersMap['Tipe Customer'] ?? null;
        $colDate = $headersMap['Transaction Date'] ?? $headersMap['Transcation Date'] ?? null;

        if ($colCustomerCode === null || $colItemCode === null || $colQty === null || $colCustomerType === null || $colDate === null) {
            throw ValidationException::withMessages([
                'file' => ['Struktur header Excel tidak valid. Kolom wajib (Kode Customer/Distributor, Item/Kode Item, Qty, Type/Tipe Customer, Transaction Date) harus tersedia.']
            ]);
        }

        // 3. Process & Validate Rows
        $errors = [];
        $validatedData = [];

        for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Check if completely empty
            $isEmpty = true;
            foreach ($row as $cell) {
                if ($cell !== null && trim($cell) !== '') {
                    $isEmpty = false;
                    break;
                }
            }
            if ($isEmpty) {
                continue;
            }

            $rowNum = $i + 1;
            $rowErrors = [];

            $customerCode = isset($row[$colCustomerCode]) ? trim($row[$colCustomerCode]) : '';
            $customerName = isset($row[$colCustomerName]) ? trim($row[$colCustomerName]) : '';
            $itemCode = isset($row[$colItemCode]) ? trim($row[$colItemCode]) : '';
            $itemName = isset($row[$colItemName]) ? trim($row[$colItemName]) : '';
            
            $rawSellPrice = isset($row[$colSellPrice]) ? $row[$colSellPrice] : 0.0;
            $sellPrice = floatval(str_replace(',', '', $rawSellPrice));

            $rawQty = isset($row[$colQty]) ? $row[$colQty] : 0.0;
            $qty = floatval(str_replace(',', '', $rawQty));

            $customerType = isset($row[$colCustomerType]) ? strtoupper(trim($row[$colCustomerType])) : '';
            $rawDate = isset($row[$colDate]) ? $row[$colDate] : '';
            $date = $this->parseExcelDate($rawDate);

            if (empty($customerCode)) {
                $rowErrors[] = "Kode Customer tidak boleh kosong.";
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
                    'customer_code' => $customerCode,
                    'customer_name' => $customerName,
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'sell_price_per_kg' => $sellPrice,
                    'qty_kg' => $qty,
                    'customer_type' => $customerType,
                    'transaction_date' => $date,
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
            $batch = $this->uploadRepository->createBatch([
                'batch_no' => $batchNo,
                'file_name' => $file->getClientOriginalName(),
                'uploaded_by' => $uploadedBy,
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
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listBatches(int $perPage = 15)
    {
        return $this->uploadRepository->getBatchesPaginated($perPage);
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
}
