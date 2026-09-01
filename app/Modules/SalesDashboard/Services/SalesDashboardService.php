<?php

namespace App\Modules\SalesDashboard\Services;

use App\Models\SalesDashboardData;
use App\Modules\SalesDashboard\Repositories\SalesDashboardRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SalesDashboardService
{
    protected SalesDashboardRepositoryInterface $repository;

    /**
     * Map of Indonesian and English month names to month numbers.
     */
    protected array $monthMap = [
        'januari' => 1,
        'january' => 1,
        'jan' => 1,
        'februari' => 2,
        'february' => 2,
        'feb' => 2,
        'maret' => 3,
        'march' => 3,
        'mar' => 3,
        'april' => 4,
        'apr' => 4,
        'mei' => 5,
        'may' => 5,
        'juni' => 6,
        'june' => 6,
        'jun' => 6,
        'juli' => 7,
        'july' => 7,
        'jul' => 7,
        'agustus' => 8,
        'august' => 8,
        'aug' => 8,
        'september' => 9,
        'sep' => 9,
        'sept' => 9,
        'oktober' => 10,
        'october' => 10,
        'oct' => 10,
        'november' => 11,
        'nov' => 11,
        'desember' => 12,
        'december' => 12,
        'dec' => 12,
    ];

    public function __construct(SalesDashboardRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Handle Target or CMO file upload and parse dynamic monthly columns.
     *
     * @param  UploadedFile  $file
     * @param  string  $type  'target'|'cmo'
     * @return array
     * @throws ValidationException
     */
    public function handleUpload(UploadedFile $file, string $type): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // 1. Identify headers and monthly columns
        $headerRowIndex = null;
        $colCustomerCode = null;
        $colCustomerName = null;
        $colDepo = null;
        $colBrand = null;
        $monthColumns = []; // elements: ['col_index' => int, 'month' => int, 'year' => int]

        foreach ($rows as $index => $row) {
            $trimmedRow = array_map(function ($val) {
                return $val !== null ? mb_strtolower(trim((string)$val)) : '';
            }, $row);

            // Look for signature columns
            $hasDistributor = false;
            $hasBrand = false;

            foreach ($trimmedRow as $colName) {
                if (in_array($colName, ['kode distributor', 'kode customer', 'customer_code', 'customer code'])) {
                    $hasDistributor = true;
                }
                if (in_array($colName, ['brand', 'merek', 'brand name', 'nama brand'])) {
                    $hasBrand = true;
                }
            }

            if ($hasDistributor && $hasBrand) {
                $headerRowIndex = $index;

                // Map static and monthly columns
                foreach ($trimmedRow as $colIndex => $colName) {
                    if (in_array($colName, ['kode distributor', 'kode customer', 'customer_code', 'customer code'])) {
                        $colCustomerCode = $colIndex;
                    } elseif (in_array($colName, ['nama distributor', 'nama customer', 'customer_name', 'customer name'])) {
                        $colCustomerName = $colIndex;
                    } elseif ($colName === 'depo') {
                        $colDepo = $colIndex;
                    } elseif (in_array($colName, ['brand', 'merek', 'brand name', 'nama brand'])) {
                        $colBrand = $colIndex;
                    }

                    // Parse month columns (e.g. "Januari 2026", "December 2026")
                    if (preg_match('/^([a-zA-Z]+)\s+(\d{4})$/', $colName, $matches)) {
                        $monthName = $matches[1];
                        $year = (int)$matches[2];
                        if (isset($this->monthMap[$monthName])) {
                            $monthColumns[] = [
                                'col_index' => $colIndex,
                                'month' => $this->monthMap[$monthName],
                                'year' => $year,
                            ];
                        }
                    }
                }
                break;
            }
        }

        if ($headerRowIndex === null || $colCustomerCode === null || $colBrand === null || empty($monthColumns)) {
            throw ValidationException::withMessages([
                'file' => ['Format header Excel tidak valid. Wajib memiliki kolom Kode Distributor, Brand/Merek, dan minimal satu kolom bulan (contoh: Januari 2026).']
            ]);
        }

        // 2. Parse data rows
        $errors = [];
        $insertedCount = 0;

        DB::beginTransaction();
        try {
            for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                // Skip if row is completely empty
                $isEmpty = true;
                foreach ($row as $cell) {
                    if ($cell !== null && trim((string)$cell) !== '') {
                        $isEmpty = false;
                        break;
                    }
                }
                if ($isEmpty) {
                    continue;
                }

                $rowNum = $i + 1;
                $customerCode = isset($row[$colCustomerCode]) ? trim((string)$row[$colCustomerCode]) : '';
                $customerName = ($colCustomerName !== null && isset($row[$colCustomerName])) ? trim((string)$row[$colCustomerName]) : '';
                $depo = ($colDepo !== null && isset($row[$colDepo])) ? trim((string)$row[$colDepo]) : null;
                $brand = isset($row[$colBrand]) ? trim(strtoupper((string)$row[$colBrand])) : '';

                if (empty($customerCode) || empty($brand)) {
                    $errors[] = "Baris {$rowNum}: Kode Distributor atau Brand/Merek tidak boleh kosong.";
                    continue;
                }

                // Default names if empty
                if (empty($customerName)) {
                    $customerName = $customerCode;
                }

                // Process each monthly value
                foreach ($monthColumns as $mCol) {
                    $val = $row[$mCol['col_index']];
                    $amount = $val !== null ? (float)str_replace(',', '', (string)$val) : 0.00;

                    // Match key fields
                    $attributes = [
                        'customer_code' => $customerCode,
                        'brand' => $brand,
                        'month' => $mCol['month'],
                        'year' => $mCol['year'],
                    ];

                    $values = [
                        'customer_name' => $customerName,
                        'depo' => $depo,
                    ];

                    if ($type === 'target') {
                        $values['target_amount'] = $amount;
                    } elseif ($type === 'cmo') {
                        $values['cmo_amount'] = $amount;
                    }

                    $this->repository->updateOrCreateRecord($attributes, $values);
                    $insertedCount++;
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                throw ValidationException::withMessages(['file' => $errors]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'success' => true,
            'message' => "Berhasil memproses upload data {$type}.",
            'processed_rows_count' => $insertedCount
        ];
    }

    /**
     * Get paginated records.
     *
     * @param  array  $filters
     * @param  int  $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    /**
     * Get raw sales dashboard records list.
     *
     * @param  array  $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRawData(array $filters = [])
    {
        return $this->repository->getRawData($filters);
    }

    /**
     * Delete a single record.
     *
     * @param  int  $id
     * @return bool
     */
    public function deleteRecord(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Bulk reset amount.
     *
     * @param  string  $type  'target'|'cmo'
     * @param  int  $month
     * @param  int  $year
     * @param  string|null  $customerCode
     * @return int
     */
    public function bulkReset(string $type, int $month, int $year, ?string $customerCode = null): int
    {
        return $this->repository->bulkResetAmount($type, $month, $year, $customerCode);
    }

    /**
     * Sync local PO/SO actual amounts and DO actual amounts (from SAP).
     *
     * @param  int  $month
     * @param  int  $year
     * @param  string|null  $customerCode
     * @return array
     */
    public function syncActuals(int $month, int $year, ?string $customerCode = null): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        // 1. Sync SO (PO/SO) from local database
        $soQuery = DB::table('sales_order_details as sod')
            ->join('sales_orders as so', 'so.id', '=', 'sod.sales_order_id')
            ->leftJoin('items', 'items.item_code', '=', 'sod.item_code')
            ->whereBetween('so.doc_date', [$startDate, $endDate])
            ->whereNotIn('so.status', ['DRAFT', 'REJECTED', 'FAILED'])
            ->select(
                'so.card_code as customer_code',
                'so.customer_name',
                'sod.item_code',
                DB::raw('COALESCE(items.item_name, sod.item_code) as item_name'),
                DB::raw('SUM(sod.line_total) as total_so')
            )
            ->groupBy('so.card_code', 'so.customer_name', 'sod.item_code', 'items.item_name');

        if ($customerCode) {
            $soQuery->where('so.card_code', $customerCode);
        }

        $soRecords = $soQuery->get();
        $soUpdated = 0;

        foreach ($soRecords as $soRow) {
            $this->repository->updateOrCreateRecord([
                'customer_code' => $soRow->customer_code,
                'item_code' => $soRow->item_code,
                'month' => $month,
                'year' => $year
            ], [
                'customer_name' => $soRow->customer_name,
                'item_name' => $soRow->item_name,
                'so_amount' => (float)$soRow->total_so
            ]);
            $soUpdated++;
        }

        // 2. Sync DO from SAP (or local orders marked as DELIVERY/ARRIVED)
        // If we query SAP, we can call Status for matching orders, or query ListInvoice.
        // As designed in the implementation plan: we pull the integrated SOs for the month/customer,
        // check their DO status from SAP (using /api/Status), and then sum their total amount.
        // We also have local records already updated to DELIVERY/ARRIVED by order status synchronization.
        // Let's query local sales orders where status is 'DELIVERY' or 'ARRIVED' to get DO amounts,
        // and also call the SAP Status API for any integrated orders in this period to make sure they are up-to-date.

        $integratedOrdersQuery = DB::table('sales_orders')
            ->whereBetween('doc_date', [$startDate, $endDate])
            ->whereNotNull('sap_doc_num')
            ->whereNotIn('status', ['DRAFT', 'REJECTED', 'FAILED']);

        if ($customerCode) {
            $integratedOrdersQuery->where('card_code', $customerCode);
        }

        $orders = $integratedOrdersQuery->get();
        $docNums = $orders->pluck('sap_doc_num')->filter()->toArray();

        if (!empty($docNums)) {
            try {
                $commaSeparated = implode(',', $docNums);
                $sapUrl = config('services.sap.url');
                $response = Http::timeout(15)->post("{$sapUrl}/api/Status", [
                    'CustomQuery' => $commaSeparated
                ]);

                if ($response->successful()) {
                    $body = $response->json();
                    if (isset($body['ErrorCode']) && $body['ErrorCode'] === 0) {
                        $results = $body['Result'] ?? [];
                        foreach ($results as $sapData) {
                            $noso = $sapData['NOSO'] ?? null;
                            $docType = $sapData['Doc'] ?? '';
                            $sapStatus = $sapData['StatusOrder'] ?? '';

                            if ($noso) {
                                // If SAP returned DO or OINV status, update local status to DELIVERY/ARRIVED
                                $updateData = [];
                                if ((strcasecmp($docType, 'DO') === 0 && strcasecmp($sapStatus, 'open') === 0) || strcasecmp($docType, 'AR') === 0) {
                                    $updateData['status'] = 'DELIVERY';
                                }
                                if (!empty($updateData)) {
                                    DB::table('sales_orders')
                                        ->where('sap_doc_num', $noso)
                                        ->update($updateData);
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Warning only
                \Illuminate\Support\Facades\Log::warning("SalesDashboardService: SAP Status check failed during sync: " . $e->getMessage());
            }
        }

        // Sum the order detail totals for orders that are DELIVERY or ARRIVED
        $doQuery = DB::table('sales_order_details as sod')
            ->join('sales_orders as so', 'so.id', '=', 'sod.sales_order_id')
            ->leftJoin('items', 'items.item_code', '=', 'sod.item_code')
            ->whereBetween('so.doc_date', [$startDate, $endDate])
            ->whereIn('so.status', ['DELIVERY', 'ARRIVED'])
            ->select(
                'so.card_code as customer_code',
                'so.customer_name',
                'sod.item_code',
                DB::raw('COALESCE(items.item_name, sod.item_code) as item_name'),
                DB::raw('SUM(sod.line_total) as total_do')
            )
            ->groupBy('so.card_code', 'so.customer_name', 'sod.item_code', 'items.item_name');

        if ($customerCode) {
            $doQuery->where('so.card_code', $customerCode);
        }

        $doRecords = $doQuery->get();
        $doUpdated = 0;

        foreach ($doRecords as $doRow) {
            $this->repository->updateOrCreateRecord([
                'customer_code' => $doRow->customer_code,
                'item_code' => $doRow->item_code,
                'month' => $month,
                'year' => $year
            ], [
                'customer_name' => $doRow->customer_name,
                'item_name' => $doRow->item_name,
                'do_amount' => (float)$doRow->total_do
            ]);
            $doUpdated++;
        }

        return [
            'so_updated' => $soUpdated,
            'do_updated' => $doUpdated
        ];
    }

    /**
    /**
     * Get comparison dashboard aggregated data by brand and month.
     *
     * @param  int  $year
     * @param  array  $filters
     * @return array
     */
    public function getComparison(int $year, array $filters = []): array
    {
        $driver = DB::connection()->getDriverName();
        $cmoMonthSql = $driver === 'pgsql' ? 'EXTRACT(MONTH FROM cmo.doc_date)' : 'MONTH(cmo.doc_date)';
        $soMonthSql = $driver === 'pgsql' ? 'EXTRACT(MONTH FROM so.doc_date)' : 'MONTH(so.doc_date)';

        // --- 1. Data dari sales_dashboard_data (Target, SO, DO) ---
        $dashQuery = DB::table('sales_dashboard_data')
            ->where('year', $year);

        if (!empty($filters['customer_code'])) {
            $custCodes = array_map('trim', explode(',', $filters['customer_code']));
            if (count($custCodes) > 1) {
                $dashQuery->whereIn('customer_code', $custCodes);
            } else {
                $dashQuery->where('customer_code', $custCodes[0]);
            }
        }

        if (!empty($filters['month'])) {
            $dashQuery->where('month', (int)$filters['month']);
        }

        if (!empty($filters['brands'])) {
            $dashQuery->whereIn('brand', $filters['brands']);
        }

        // tes push

        $dashRecords = $dashQuery->select(
            'month',
            'brand',
            DB::raw('SUM(target_amount) as target_amount'),
            DB::raw('SUM(cmo_amount) as cmo_amount'),
            DB::raw('SUM(so_amount) as so_amount'),
            DB::raw('SUM(do_amount) as do_amount')
        )
            ->groupBy('month', 'brand')
            ->get();

        // --- 2. CMO realtime langsung dari customer_monthly_orders ---
        // Hanya hitung CMO status DRAFT (belum diposting jadi SO)
        $cmoQuery = DB::table('customer_monthly_order_details as cmod')
            ->join('customer_monthly_orders as cmo', 'cmo.id', '=', 'cmod.customer_monthly_order_id')
            ->join('items as i', 'i.item_code', '=', 'cmod.item_code')
            ->whereYear('cmo.doc_date', $year)
            ->where('cmo.status', 'DRAFT')
            ->whereNotNull('i.brand')
            ->where('i.brand', '!=', '');

        if (!empty($filters['customer_code'])) {
            $custCodes = array_map('trim', explode(',', $filters['customer_code']));
            if (count($custCodes) > 1) {
                $cmoQuery->whereIn('cmo.card_code', $custCodes);
            } else {
                $cmoQuery->where('cmo.card_code', $custCodes[0]);
            }
        }

        if (!empty($filters['month'])) {
            $cmoQuery->whereRaw("{$cmoMonthSql} = ?", [(int)$filters['month']]);
        }

        if (!empty($filters['brands'])) {
            $cmoQuery->whereIn('i.brand', $filters['brands']);
        }

        $cmoRecords = $cmoQuery->select(
            DB::raw($cmoMonthSql . ' as month'),
            'i.brand',
            DB::raw('SUM(cmod.quantity * COALESCE(NULLIF(i.per_kg, 0), 1)) as cmo_amount')
        )
            ->groupBy(DB::raw($cmoMonthSql), 'i.brand')
            ->get()
            ->keyBy(fn($r) => $r->brand . '_' . (int)$r->month);

        // --- 3. SO realtime langsung dari sales_orders & sales_order_details ---
        // Hitung semua SO yang sudah diproses (status selain DRAFT, REJECTED, FAILED)
        $soQuery = DB::table('sales_order_details as sod')
            ->join('sales_orders as so', 'so.id', '=', 'sod.sales_order_id')
            ->leftJoin('items as i', 'i.item_code', '=', 'sod.item_code')
            ->whereYear('so.doc_date', $year)
            ->whereNotIn('so.status', ['DRAFT', 'REJECTED', 'FAILED'])
            ->whereNotNull('i.brand')
            ->where('i.brand', '!=', '');

        if (!empty($filters['customer_code'])) {
            $custCodes = array_map('trim', explode(',', $filters['customer_code']));
            if (count($custCodes) > 1) {
                $soQuery->whereIn('so.card_code', $custCodes);
            } else {
                $soQuery->where('so.card_code', $custCodes[0]);
            }
        }

        if (!empty($filters['month'])) {
            $soQuery->whereRaw("{$soMonthSql} = ?", [(int)$filters['month']]);
        }

        if (!empty($filters['brands'])) {
            $soQuery->whereIn('i.brand', $filters['brands']);
        }

        $soRecords = $soQuery->select(
            DB::raw($soMonthSql . ' as month'),
            'i.brand',
            DB::raw('SUM(sod.quantity * COALESCE(NULLIF(i.per_kg, 0), 1)) as so_amount')
        )
            ->groupBy(DB::raw($soMonthSql), 'i.brand')
            ->get()
            ->keyBy(fn($r) => $r->brand . '_' . (int)$r->month);

        // --- 4. DO realtime langsung dari sales_orders (DELIVERY / ARRIVED / COMPLETED) ---
        $doQuery = DB::table('sales_order_details as sod')
            ->join('sales_orders as so', 'so.id', '=', 'sod.sales_order_id')
            ->leftJoin('items as i', 'i.item_code', '=', 'sod.item_code')
            ->whereYear('so.doc_date', $year)
            ->whereIn('so.status', ['DELIVERY', 'ARRIVED', 'COMPLETED'])
            ->whereNotNull('i.brand')
            ->where('i.brand', '!=', '');

        if (!empty($filters['customer_code'])) {
            $custCodes = array_map('trim', explode(',', $filters['customer_code']));
            if (count($custCodes) > 1) {
                $doQuery->whereIn('so.card_code', $custCodes);
            } else {
                $doQuery->where('so.card_code', $custCodes[0]);
            }
        }

        if (!empty($filters['month'])) {
            $doQuery->whereRaw("{$soMonthSql} = ?", [(int)$filters['month']]);
        }

        if (!empty($filters['brands'])) {
            $doQuery->whereIn('i.brand', $filters['brands']);
        }

        $doRecords = $doQuery->select(
            DB::raw($soMonthSql . ' as month'),
            'i.brand',
            DB::raw('SUM(sod.quantity * COALESCE(NULLIF(i.per_kg, 0), 1)) as do_amount')
        )
            ->groupBy(DB::raw($soMonthSql), 'i.brand')
            ->get()
            ->keyBy(fn($r) => $r->brand . '_' . (int)$r->month);

        // --- 5. Tentukan unique brands ---
        $brandsFromDash = $dashRecords->pluck('brand')->filter()->unique();
        $brandsFromCmo = $cmoRecords->values()->pluck('brand')->filter()->unique();
        $brandsFromSo = $soRecords->values()->pluck('brand')->filter()->unique();
        $brandsFromDo = $doRecords->values()->pluck('brand')->filter()->unique();

        if (!empty($filters['brands'])) {
            $uniqueBrands = $filters['brands'];
        } else {
            $uniqueBrands = $brandsFromDash
                ->merge($brandsFromCmo)
                ->merge($brandsFromSo)
                ->merge($brandsFromDo)
                ->unique()->filter()->values()->toArray();

            if (empty($uniqueBrands)) {
                // Fallback ke brand dari items jika belum ada data sama sekali
                $uniqueBrands = DB::table('items')
                    ->whereNotNull('brand')
                    ->where('brand', '!=', '')
                    ->distinct()
                    ->pluck('brand')
                    ->toArray();
            }
        }

        // --- 6. Tentukan range bulan ---
        $monthsToGenerate = !empty($filters['month']) ? [(int)$filters['month']] : range(1, 12);

        // --- 7. Pre-populate grid ---
        $grid = [];
        foreach ($uniqueBrands as $brand) {
            foreach ($monthsToGenerate as $m) {
                $grid[$brand][$m] = [
                    'month' => $m,
                    'brand' => $brand,
                    'target_amount' => 0.00,
                    'cmo_amount' => 0.00,
                    'so_amount' => 0.00,
                    'do_amount' => 0.00,
                ];
            }
        }

        // --- 8. Isi dari sales_dashboard_data (target, so, do; cmo sebagai fallback) ---
        foreach ($dashRecords as $rec) {
            $brand = $rec->brand;
            $month = (int)$rec->month;
            if (isset($grid[$brand][$month])) {
                $grid[$brand][$month]['target_amount'] = (float)$rec->target_amount;
                $grid[$brand][$month]['cmo_amount'] = (float)$rec->cmo_amount;
                $grid[$brand][$month]['so_amount'] = (float)$rec->so_amount;
                $grid[$brand][$month]['do_amount'] = (float)$rec->do_amount;
            }
        }

        // --- 9. Override cmo_amount dengan data realtime dari CMO table ---
        foreach ($cmoRecords as $key => $rec) {
            $brand = $rec->brand;
            $month = (int)$rec->month;
            if (isset($grid[$brand][$month])) {
                $grid[$brand][$month]['cmo_amount'] = (float)$rec->cmo_amount;
            }
        }

        // --- 10. Override/Merge SO realtime dari sales_orders table ---
        foreach ($soRecords as $key => $rec) {
            $brand = $rec->brand;
            $month = (int)$rec->month;
            if (isset($grid[$brand][$month])) {
                // Ambil nilai maksimal antara realtime SO dan data dashboard
                $grid[$brand][$month]['so_amount'] = max((float)$grid[$brand][$month]['so_amount'], (float)$rec->so_amount);
            }
        }

        // --- 11. Override/Merge DO realtime dari sales_orders table ---
        foreach ($doRecords as $key => $rec) {
            $brand = $rec->brand;
            $month = (int)$rec->month;
            if (isset($grid[$brand][$month])) {
                $grid[$brand][$month]['do_amount'] = max((float)$grid[$brand][$month]['do_amount'], (float)$rec->do_amount);
            }
        }

        // --- 12. Flatten data ---
        $formatted = [];
        $totals = [
            'target' => 0.00,
            'cmo' => 0.00,
            'so' => 0.00,
            'do' => 0.00,
        ];

        foreach ($grid as $brand => $months) {
            foreach ($months as $m => $data) {
                $formatted[] = $data;
                $totals['target'] += $data['target_amount'];
                $totals['cmo'] += $data['cmo_amount'];
                $totals['so'] += $data['so_amount'];
                $totals['do'] += $data['do_amount'];
            }
        }

        return [
            'year' => $year,
            'totals' => $totals,
            'lines' => $formatted
        ];
    }

    /**
     * Update a record by ID.
     *
     * @param  int  $id
     * @param  array  $data
     * @return SalesDashboardData|null
     */
    public function updateRecord(int $id, array $data): ?SalesDashboardData
    {
        return $this->repository->update($id, $data);
    }

    /**
     * Sync data CMO, SO, dan DO dari SAP ke tabel sales_dashboard_data.
     *
     * @param  int  $year
     * @param  string  $customerCode
     * @param  array  $brands
     * @param  string|null  $targetCustomerOverride
     * @return array
     */
    public function syncDashboardData(int $year, string $customerCode, array $brands, ?string $targetCustomerOverride = null): array
    {
        $targetCustomerCode = $targetCustomerOverride ?? $customerCode;

        // Cari detail customer target di tabel distributors
        $distributor = DB::table('distributors')->where('code_customer', $targetCustomerCode)->first();
        $targetCustomerName = $distributor?->name ?? 'Distributor ' . $targetCustomerCode;
        $targetDepo = $distributor?->depo;

        $driver = DB::connection()->getDriverName();
        $cmoMonthSql = $driver === 'pgsql' ? 'EXTRACT(MONTH FROM cmo.doc_date)' : 'MONTH(cmo.doc_date)';
        $soMonthSql = $driver === 'pgsql' ? 'EXTRACT(MONTH FROM so.doc_date)' : 'MONTH(so.doc_date)';

        // 1. Sync CMO
        // Gunakan line_total dari cmod (sudah mencerminkan qty * price dengan diskon)
        // JANGAN pakai per_kg karena bisa 0/NULL dan menyebabkan cmo_amount = 0
        // Hanya sync CMO status DRAFT (belum diposting jadi SO)
        $cmoDetails = DB::table('customer_monthly_order_details as cmod')
            ->join('customer_monthly_orders as cmo', 'cmo.id', '=', 'cmod.customer_monthly_order_id')
            ->join('items as i', 'i.item_code', '=', 'cmod.item_code')
            ->whereYear('cmo.doc_date', $year)
            ->where('cmo.card_code', $customerCode)
            ->whereIn('i.brand', $brands)
            ->where('cmo.status', 'DRAFT')
            ->select(
                DB::raw($cmoMonthSql . ' as month'),
                'i.brand',
                DB::raw('SUM(cmod.line_total) as total_cmo')
            )
            ->groupBy(DB::raw($cmoMonthSql), 'i.brand')
            ->get();

        $cmoSyncedCount = 0;
        foreach ($cmoDetails as $cmo) {
            DB::table('sales_dashboard_data')->updateOrInsert(
                [
                    'customer_code' => $targetCustomerCode,
                    'brand' => $cmo->brand,
                    'month' => (int)$cmo->month,
                    'year' => $year
                ],
                [
                    'customer_name' => $targetCustomerName,
                    'depo' => $targetDepo,
                    'cmo_amount' => (float)$cmo->total_cmo,
                    'updated_at' => now(),
                ]
            );
            $cmoSyncedCount++;
        }

        // 2. Sync SO
        $soDetails = DB::table('sales_order_details as sod')
            ->join('sales_orders as so', 'so.id', '=', 'sod.sales_order_id')
            ->join('items as i', 'i.item_code', '=', 'sod.item_code')
            ->whereYear('so.doc_date', $year)
            ->where('so.card_code', $customerCode)
            ->whereIn('i.brand', $brands)
            ->where(function ($q) {
                $q->where('so.approval_id', 6)
                    ->orWhereIn('so.status', ['ORDER_APPROVED', 'DELIVERY', 'ARRIVED']);
            })
            ->select(
                DB::raw($soMonthSql . ' as month'),
                'i.brand',
                DB::raw('SUM(sod.quantity * COALESCE(i.per_kg, 0)) as total_so')
            )
            ->groupBy(DB::raw($soMonthSql), 'i.brand')
            ->get();

        $soSyncedCount = 0;
        foreach ($soDetails as $so) {
            DB::table('sales_dashboard_data')->updateOrInsert(
                [
                    'customer_code' => $targetCustomerCode,
                    'brand' => $so->brand,
                    'month' => (int)$so->month,
                    'year' => $year
                ],
                [
                    'customer_name' => $targetCustomerName,
                    'depo' => $targetDepo,
                    'so_amount' => (float)$so->total_so,
                    'updated_at' => now(),
                ]
            );
            $soSyncedCount++;
        }

        // 3. Sync DO dari SAP
        $doSyncedCount = 0;
        try {
            $sapUrl = config('services.sap.url');
            $response = Http::timeout(30)->post("{$sapUrl}/api/GetTotDO", [
                'Tahun' => (string)$year,
                'CardCode' => $customerCode,
                'Brand' => implode(',', $brands),
            ]);

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['ErrorCode']) && $body['ErrorCode'] === 0) {
                    $results = $body['Result'] ?? [];

                    foreach ($results as $item) {
                        $month = (int)$item['Bulan'];
                        $brandName = trim(strtoupper($item['Brand']));
                        $doAmount = (float)$item['Col4'];

                        DB::table('sales_dashboard_data')->updateOrInsert(
                            [
                                'customer_code' => $targetCustomerCode,
                                'brand' => $brandName,
                                'month' => $month,
                                'year' => $year
                            ],
                            [
                                'customer_name' => $targetCustomerName,
                                'depo' => $targetDepo,
                                'do_amount' => $doAmount,
                                'updated_at' => now(),
                            ]
                        );
                        $doSyncedCount++;
                    }
                } else {
                    \Illuminate\Support\Facades\Log::warning("SalesDashboardService: GetTotDO SAP API returned error: " . ($body['Message'] ?? 'Unknown'));
                }
            } else {
                \Illuminate\Support\Facades\Log::warning("SalesDashboardService: Failed to contact SAP GetTotDO API: " . $response->status());
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("SalesDashboardService: Exception in SAP GetTotDO sync: " . $e->getMessage());
        }

        return [
            'year' => $year,
            'customer_code' => $targetCustomerCode,
            'cmo_synced' => $cmoSyncedCount,
            'so_synced' => $soSyncedCount,
            'do_synced' => $doSyncedCount,
        ];
    }
}
