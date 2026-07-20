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
        'januari' => 1, 'january' => 1, 'jan' => 1,
        'februari' => 2, 'february' => 2, 'feb' => 2,
        'maret' => 3, 'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5, 'may' => 5,
        'juni' => 6, 'june' => 6, 'jun' => 6,
        'juli' => 7, 'july' => 7, 'jul' => 7,
        'agustus' => 8, 'august' => 8, 'aug' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'october' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11,
        'desember' => 12, 'december' => 12, 'dec' => 12,
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
        $colItemCode = null;
        $colItemName = null;
        $monthColumns = []; // elements: ['col_index' => int, 'month' => int, 'year' => int]

        foreach ($rows as $index => $row) {
            $trimmedRow = array_map(function ($val) {
                return $val !== null ? mb_strtolower(trim((string)$val)) : '';
            }, $row);

            // Look for signature columns
            $hasDistributor = false;
            $hasProduct = false;

            foreach ($trimmedRow as $colName) {
                if (in_array($colName, ['kode distributor', 'kode customer', 'customer_code', 'customer code'])) {
                    $hasDistributor = true;
                }
                if (in_array($colName, ['kode produk', 'kode barang', 'kode item', 'item_code', 'item code'])) {
                    $hasProduct = true;
                }
            }

            if ($hasDistributor && $hasProduct) {
                $headerRowIndex = $index;
                
                // Map static and monthly columns
                foreach ($trimmedRow as $colIndex => $colName) {
                    if (in_array($colName, ['kode distributor', 'kode customer', 'customer_code', 'customer code'])) {
                        $colCustomerCode = $colIndex;
                    } elseif (in_array($colName, ['nama distributor', 'nama customer', 'customer_name', 'customer name'])) {
                        $colCustomerName = $colIndex;
                    } elseif ($colName === 'depo') {
                        $colDepo = $colIndex;
                    } elseif (in_array($colName, ['kode produk', 'kode barang', 'kode item', 'item_code', 'item code'])) {
                        $colItemCode = $colIndex;
                    } elseif (in_array($colName, ['nama produk', 'nama barang', 'nama item', 'item_name', 'item name'])) {
                        $colItemName = $colIndex;
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

        if ($headerRowIndex === null || $colCustomerCode === null || $colItemCode === null || empty($monthColumns)) {
            throw ValidationException::withMessages([
                'file' => ['Format header Excel tidak valid. Wajib memiliki kolom Kode Distributor, Kode Produk, dan minimal satu kolom bulan (contoh: Januari 2026).']
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
                $itemCode = isset($row[$colItemCode]) ? trim((string)$row[$colItemCode]) : '';
                $itemName = ($colItemName !== null && isset($row[$colItemName])) ? trim((string)$row[$colItemName]) : '';

                if (empty($customerCode) || empty($itemCode)) {
                    $errors[] = "Baris {$rowNum}: Kode Distributor atau Kode Produk tidak boleh kosong.";
                    continue;
                }

                // Default names if empty
                if (empty($customerName)) {
                    $customerName = $customerCode;
                }
                if (empty($itemName)) {
                    $itemName = $itemCode;
                }

                // Process each monthly value
                foreach ($monthColumns as $mCol) {
                    $val = $row[$mCol['col_index']];
                    $amount = $val !== null ? (float)str_replace(',', '', (string)$val) : 0.00;

                    // Match key fields
                    $attributes = [
                        'customer_code' => $customerCode,
                        'item_code' => $itemCode,
                        'month' => $mCol['month'],
                        'year' => $mCol['year'],
                    ];

                    $values = [
                        'customer_name' => $customerName,
                        'depo' => $depo,
                        'item_name' => $itemName,
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
    public function getPaginatedData(array $filters = [], int $perPage = 15)
    {
        return $this->repository->getPaginated($filters, $perPage);
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
                // Call SAP Status API in batch (imploded by comma)
                $commaSeparated = implode(',', $docNums);
                $response = Http::timeout(15)->post('http://103.18.133.187:3100/api/Status', [
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
                                if (strcasecmp($docType, 'DO') === 0 && strcasecmp($sapStatus, 'open') === 0) {
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
     * Get comparison dashboard aggregated data.
     *
     * @param  int  $month
     * @param  int  $year
     * @param  array  $filters
     * @return array
     */
    public function getComparison(int $month, int $year, array $filters = []): array
    {
        $records = $this->repository->getComparisonData($month, $year, $filters);

        $totals = [
            'target' => 0.00,
            'cmo' => 0.00,
            'so' => 0.00,
            'do' => 0.00,
        ];

        $items = [];
        foreach ($records as $rec) {
            $totals['target'] += (float)$rec->target_amount;
            $totals['cmo'] += (float)$rec->cmo_amount;
            $totals['so'] += (float)$rec->so_amount;
            $totals['do'] += (float)$rec->do_amount;

            $items[] = [
                'id' => $rec->id,
                'customer_code' => $rec->customer_code,
                'customer_name' => $rec->customer_name,
                'depo' => $rec->depo,
                'item_code' => $rec->item_code,
                'item_name' => $rec->item_name,
                'target_amount' => (float)$rec->target_amount,
                'cmo_amount' => (float)$rec->cmo_amount,
                'so_amount' => (float)$rec->so_amount,
                'do_amount' => (float)$rec->do_amount,
            ];
        }

        return [
            'month' => $month,
            'year' => $year,
            'totals' => $totals,
            'records' => $items
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

        // 1. Sync CMO
        $cmoDetails = DB::table('customer_monthly_order_details as cmod')
            ->join('customer_monthly_orders as cmo', 'cmo.id', '=', 'cmod.customer_monthly_order_id')
            ->join('items as i', 'i.item_code', '=', 'cmod.item_code')
            ->whereYear('cmo.doc_date', $year)
            ->where('cmo.card_code', $customerCode)
            ->whereIn('i.brand', $brands)
            ->select(
                DB::raw('MONTH(cmo.doc_date) as month'),
                'cmod.item_code',
                'i.item_name',
                DB::raw('SUM(cmod.line_total) as total_cmo')
            )
            ->groupBy('month', 'cmod.item_code', 'i.item_name')
            ->get();

        $cmoSyncedCount = 0;
        foreach ($cmoDetails as $cmo) {
            DB::table('sales_dashboard_data')->updateOrInsert(
                [
                    'customer_code' => $targetCustomerCode,
                    'item_code' => $cmo->item_code,
                    'month' => (int)$cmo->month,
                    'year' => $year
                ],
                [
                    'customer_name' => $targetCustomerName,
                    'depo' => $targetDepo,
                    'item_name' => $cmo->item_name,
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
            ->where(function($q) {
                $q->where('so.approval_id', 6)
                  ->orWhereIn('so.status', ['ORDER_APPROVED', 'DELIVERY', 'ARRIVED']);
            })
            ->select(
                DB::raw('MONTH(so.doc_date) as month'),
                'sod.item_code',
                'i.item_name',
                DB::raw('SUM(sod.line_total) as total_so')
            )
            ->groupBy('month', 'sod.item_code', 'i.item_name')
            ->get();

        $soSyncedCount = 0;
        foreach ($soDetails as $so) {
            DB::table('sales_dashboard_data')->updateOrInsert(
                [
                    'customer_code' => $targetCustomerCode,
                    'item_code' => $so->item_code,
                    'month' => (int)$so->month,
                    'year' => $year
                ],
                [
                    'customer_name' => $targetCustomerName,
                    'depo' => $targetDepo,
                    'item_name' => $so->item_name,
                    'so_amount' => (float)$so->total_so,
                    'updated_at' => now(),
                ]
            );
            $soSyncedCount++;
        }

        // 3. Sync DO dari SAP
        $doSyncedCount = 0;
        try {
            $response = Http::timeout(30)->post('http://103.18.133.187:3100/api/GetTotDO', [
                'Tahun' => (string)$year,
                'CardCode' => $customerCode,
                'Brand' => implode(',', $brands),
            ]);

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['ErrorCode']) && $body['ErrorCode'] === 0) {
                    $results = $body['Result'] ?? [];
                    
                    // Group results by Bulan (month) and Brand
                    $doByBrandMonth = [];
                    foreach ($results as $item) {
                        $month = (int)$item['Bulan'];
                        $brandName = trim(strtoupper($item['Brand']));
                        $doAmount = (float)$item['Col4'];
                        $doByBrandMonth[$month][$brandName] = $doAmount;
                    }

                    // Proses setiap bulan
                    foreach ($doByBrandMonth as $month => $brandsData) {
                        foreach ($brandsData as $brandName => $brandDoAmount) {
                            // Ambil semua item_code yang memiliki brand ini
                            $brandItems = DB::table('items')
                                ->where('brand', $brandName)
                                ->pluck('item_code')
                                ->toArray();

                            if (empty($brandItems)) {
                                continue;
                            }

                            // Query record yang sudah ada di sales_dashboard_data untuk customer target ini
                            $dashboardRecords = DB::table('sales_dashboard_data')
                                ->where('customer_code', $targetCustomerCode)
                                ->where('month', $month)
                                ->where('year', $year)
                                ->whereIn('item_code', $brandItems)
                                ->get();

                            if ($dashboardRecords->isEmpty()) {
                                // Jika belum ada baris target sama sekali, bagikan sama rata ke item brand ini
                                $brandDbItems = DB::table('items')
                                    ->where('brand', $brandName)
                                    ->where('status', 1)
                                    ->get();

                                if ($brandDbItems->isEmpty()) {
                                    $brandDbItems = DB::table('items')
                                        ->where('brand', $brandName)
                                        ->get();
                                }

                                if (!$brandDbItems->isEmpty()) {
                                    $count = $brandDbItems->count();
                                    $equalDo = $brandDoAmount / $count;

                                    foreach ($brandDbItems as $itemObj) {
                                        DB::table('sales_dashboard_data')->updateOrInsert(
                                            [
                                                'customer_code' => $targetCustomerCode,
                                                'item_code' => $itemObj->item_code,
                                                'month' => $month,
                                                'year' => $year
                                            ],
                                            [
                                                'customer_name' => $targetCustomerName,
                                                'depo' => $targetDepo,
                                                'item_name' => $itemObj->item_name,
                                                'do_amount' => $equalDo,
                                                'updated_at' => now(),
                                            ]
                                        );
                                        $doSyncedCount++;
                                    }
                                }
                                continue;
                            }

                            // Cek perbandingan prioritas untuk distribusi proporsional
                            $totalSo = $dashboardRecords->sum(function ($r) { return (float)$r->so_amount; });
                            $totalCmo = $dashboardRecords->sum(function ($r) { return (float)$r->cmo_amount; });
                            $totalTarget = $dashboardRecords->sum(function ($r) { return (float)$r->target_amount; });

                            if ($totalSo > 0) {
                                foreach ($dashboardRecords as $rec) {
                                    $ratio = (float)$rec->so_amount / $totalSo;
                                    DB::table('sales_dashboard_data')
                                        ->where('id', $rec->id)
                                        ->update([
                                            'do_amount' => $brandDoAmount * $ratio,
                                            'updated_at' => now(),
                                        ]);
                                    $doSyncedCount++;
                                }
                            } elseif ($totalCmo > 0) {
                                foreach ($dashboardRecords as $rec) {
                                    $ratio = (float)$rec->cmo_amount / $totalCmo;
                                    DB::table('sales_dashboard_data')
                                        ->where('id', $rec->id)
                                        ->update([
                                            'do_amount' => $brandDoAmount * $ratio,
                                            'updated_at' => now(),
                                        ]);
                                    $doSyncedCount++;
                                }
                            } elseif ($totalTarget > 0) {
                                foreach ($dashboardRecords as $rec) {
                                    $ratio = (float)$rec->target_amount / $totalTarget;
                                    DB::table('sales_dashboard_data')
                                        ->where('id', $rec->id)
                                        ->update([
                                            'do_amount' => $brandDoAmount * $ratio,
                                            'updated_at' => now(),
                                        ]);
                                    $doSyncedCount++;
                                }
                            } else {
                                // Bagi rata ke semua record yang terdaftar
                                $count = $dashboardRecords->count();
                                $equalDo = $brandDoAmount / $count;
                                foreach ($dashboardRecords as $rec) {
                                    DB::table('sales_dashboard_data')
                                        ->where('id', $rec->id)
                                        ->update([
                                            'do_amount' => $equalDo,
                                            'updated_at' => now(),
                                        ]);
                                    $doSyncedCount++;
                                }
                            }
                        }
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
