<?php

namespace App\Modules\VendorPortal\Services;

use App\Models\CustomerShipto;
use App\Models\ExpeditionRate;
use App\Models\Warehouse;
use App\Modules\Ekspedisi\Services\ExpeditionUploadService;
use App\Modules\VendorPortal\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorRateService
{
    protected ExpeditionUploadService $uploadService;

    public function __construct(ExpeditionUploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Get paginated rates submitted by the vendor.
     */
    public function listRates(Vendor $vendor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        if (!$vendor->expedition_id) {
            throw ValidationException::withMessages([
                'vendor' => ['Vendor is not linked to an expedition master record yet.'],
            ]);
        }

        $query = ExpeditionRate::with(['warehouse', 'destination', 'approver'])
            ->where('expedition_id', $vendor->expedition_id);

        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', strtoupper(trim($filters['approval_status'])));
        }

        if (!empty($filters['transport_mode'])) {
            $query->whereRaw('LOWER(transport_mode) = ?', [strtolower(trim($filters['transport_mode']))]);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['destination_id'])) {
            $query->where('destination_id', $filters['destination_id']);
        }

        if (!empty($filters['batch_id'])) {
            $query->where('upload_batch_id', trim($filters['batch_id']));
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('warehouse', function ($wh) use ($search) {
                    $wh->where('whs_code', 'ILIKE', $search)
                        ->orWhere('whs_name', 'ILIKE', $search);
                })->orWhereHas('destination', function ($dest) use ($search) {
                    $dest->where('alias', 'ILIKE', $search)
                        ->orWhere('name', 'ILIKE', $search)
                        ->orWhere('city', 'ILIKE', $search);
                })->orWhere('remarks', 'ILIKE', $search)
                  ->orWhere('upload_batch_id', 'ILIKE', $search);
            });
        }

        return $query->orderBy('updated_at', 'desc')->paginate($perPage);
    }

    /**
     * Submit a single rate manually from vendor portal form.
     */
    public function submitRate(Vendor $vendor, array $data, ?int $userId = null): ExpeditionRate
    {
        if (!$vendor->expedition_id) {
            throw ValidationException::withMessages([
                'vendor' => ['Vendor is not linked to an expedition master record yet.'],
            ]);
        }

        // Resolve Warehouse
        $warehouseId = $data['warehouse_id'] ?? null;
        if (!$warehouseId && !empty($data['origin'])) {
            $originInput = trim((string) $data['origin']);
            $whs = Warehouse::where('whs_code', $originInput)
                ->orWhere('id', is_numeric($originInput) ? (int) $originInput : 0)
                ->orWhere('whs_name', $originInput)
                ->first();
            if ($whs) {
                $warehouseId = $whs->id;
            }
        }

        if (!$warehouseId) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Origin warehouse is required and must exist in master warehouses.'],
            ]);
        }

        // Resolve Destination
        $destinationId = $data['destination_id'] ?? null;
        if (!$destinationId && !empty($data['destination'])) {
            $destInput = trim((string) $data['destination']);
            $shipto = CustomerShipto::where('id', is_numeric($destInput) ? (int) $destInput : 0)
                ->orWhere('card_code', $destInput)
                ->orWhere('alias', $destInput)
                ->orWhere('name', $destInput)
                ->first();
            if ($shipto) {
                $destinationId = $shipto->id;
            }
        }

        if (!$destinationId) {
            throw ValidationException::withMessages([
                'destination_id' => ['Destination is required and must exist in customer shipto master.'],
            ]);
        }

        $minTonnage = floatval($data['min_tonnage'] ?? $data['min_kg'] ?? 0);
        $maxTonnage = floatval($data['max_tonnage'] ?? $data['max_kg'] ?? 0);
        $price = floatval($data['price'] ?? $data['rate'] ?? 0);

        if ($price <= 0) {
            throw ValidationException::withMessages([
                'price' => ['Rate price must be greater than 0.'],
            ]);
        }

        $transportMode = !empty($data['transport_mode']) ? strtoupper(trim($data['transport_mode'])) : null;
        $serviceType = !empty($data['service_type']) ? trim($data['service_type']) : null;

        // Check if existing rate matches unique combination
        $existingRateQuery = ExpeditionRate::where('expedition_id', $vendor->expedition_id)
            ->where('warehouse_id', $warehouseId)
            ->where('destination_id', $destinationId);

        if ($transportMode) {
            $existingRateQuery->whereRaw('LOWER(transport_mode) = ?', [strtolower($transportMode)]);
        } else {
            $existingRateQuery->whereNull('transport_mode');
        }

        if ($serviceType) {
            $existingRateQuery->whereRaw('LOWER(service_type) = ?', [strtolower($serviceType)]);
        } else {
            $existingRateQuery->whereNull('service_type');
        }

        $existingRate = $existingRateQuery
            ->where('min_tonnage', $minTonnage)
            ->where('max_tonnage', $maxTonnage)
            ->first();

        $leadTime = !empty($data['leadtime']) ? (int) $data['leadtime'] : (!empty($data['lead_time']) ? (int) $data['lead_time'] : (!empty($data['eta_days']) ? (int) $data['eta_days'] : null));
        $validFrom = !empty($data['valid_from']) ? $data['valid_from'] : (!empty($data['periode']) ? $data['periode'] : null);
        $validUntil = !empty($data['valid_until']) ? $data['valid_until'] : (!empty($data['periode']) ? $data['periode'] : null);

        $payload = [
            'expedition_id'    => $vendor->expedition_id,
            'warehouse_id'     => $warehouseId,
            'destination_id'   => $destinationId,
            'transport_mode'   => $transportMode,
            'service_type'     => $serviceType,
            'min_tonnage'      => $minTonnage,
            'max_tonnage'      => $maxTonnage,
            'price'            => $price,
            'eta_days'         => $leadTime,
            'min_shipment_qty' => floatval($data['min_shipment_qty'] ?? 0),
            'max_shipment_qty' => floatval($data['max_shipment_qty'] ?? 0),
            'valid_from'       => $validFrom,
            'valid_until'      => $validUntil,
            'status'           => 'ACTIVE',
            'flag'             => false,
            'approval_status'  => 'PENDING',
            'remarks'          => $data['remarks'] ?? null,
            'upload_batch_id'  => 'MANUAL-SUBMIT-' . date('YmdHis'),
        ];

        $internalUserId = ($userId && \App\Models\User::where('id', $userId)->exists()) ? $userId : null;

        if ($existingRate) {
            $payload['updated_by'] = $internalUserId;
            $existingRate->update($payload);
            return $existingRate->fresh(['warehouse', 'destination']);
        }

        $payload['created_by'] = $internalUserId;
        return ExpeditionRate::create($payload)->fresh(['warehouse', 'destination']);
    }

    /**
     * Upload rates via Excel/CSV spreadsheet locked to the vendor's expedition.
     */
    public function uploadRates(Vendor $vendor, UploadedFile $file, ?int $userId = null, ?array $overridePeriod = null): array
    {
        if (!$vendor->expedition_id) {
            throw ValidationException::withMessages([
                'vendor' => ['Vendor is not linked to an expedition master record yet.'],
            ]);
        }

        return $this->uploadService->uploadRates($file, $vendor->expedition_id, $overridePeriod);
    }

    /**
     * Generate sample CSV template content with final headers for vendor rate submission.
     */
    public function generateTemplateCsv(): string
    {
        $headers = [
            'No',
            'Origin Name',
            'Destination',
            'Transport Mode',
            'Min Weight (Kg)',
            'Max Weight (Kg)',
            'Service Type',
            'Rate',
            'Lead Time',
        ];

        $sampleRow = [
            '1',
            'Gudang Manyar Gresik',
            'CUST-SMG-01',
            'DARAT',
            '0',
            '15000',
            'REGULER',
            '4500000',
            '2',
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        fputcsv($output, $sampleRow);
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * Get paginated batch submission headers for vendor portal dashboard/table.
     */
    public function listRateHeaders(Vendor $vendor, array $filters = [], int $perPage = 15)
    {
        if (!$vendor->expedition_id) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $query = ExpeditionRate::where('expedition_id', $vendor->expedition_id)
            ->selectRaw("
                COALESCE(upload_batch_id, 'BATCH-' || id) as batch_id,
                MIN(valid_from) as valid_from,
                MAX(valid_until) as valid_until,
                MAX(approval_status) as approval_status,
                MAX(status) as status,
                MAX(remarks) as remarks,
                MAX(created_at) as created_at,
                COUNT(*) as total_routes
            ")
            ->groupByRaw("COALESCE(upload_batch_id, 'BATCH-' || id)");

        if (!empty($filters['approval_status'])) {
            $query->havingRaw("MAX(approval_status) = ?", [strtoupper(trim($filters['approval_status']))]);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->havingRaw("(COALESCE(upload_batch_id, 'BATCH-' || id) LIKE ? OR MAX(remarks) LIKE ?)", [$search, $search]);
        }

        if (!empty($filters['date_from'])) {
            $query->havingRaw("MIN(valid_from) >= ?", [$filters['date_from']]);
        }

        if (!empty($filters['date_to'])) {
            $query->havingRaw("MAX(valid_until) <= ?", [$filters['date_to']]);
        }

        $query->orderByRaw('MAX(created_at) DESC');

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function ($item) {
            $validFrom = $item->valid_from ? date('Y-m-d', strtotime((string)$item->valid_from)) : null;
            $validUntil = $item->valid_until ? date('Y-m-d', strtotime((string)$item->valid_until)) : null;
            $periodLabel = ($validFrom && $validUntil) ? "{$validFrom} s/d {$validUntil}" : ($validFrom ?? $validUntil ?? '-');

            return [
                'batch_id'        => $item->batch_id,
                'valid_from'      => $validFrom,
                'valid_until'     => $validUntil,
                'period_label'    => $periodLabel,
                'approval_status' => $item->approval_status ?? 'PENDING',
                'status'          => $item->status ?? 'ACTIVE',
                'total_routes'    => (int) $item->total_routes,
                'remarks'         => $item->remarks,
                'submitted_at'    => $item->created_at ? date('Y-m-d H:i:s', strtotime((string)$item->created_at)) : null,
            ];
        });

        return $paginator;
    }

    /**
     * Get single batch header with detail rate routes.
     */
    public function getRateBatchDetail(Vendor $vendor, string $batchId): array
    {
        if (!$vendor->expedition_id) {
            throw ValidationException::withMessages([
                'vendor' => ['Vendor is not linked to an expedition master record yet.'],
            ]);
        }

        $rates = ExpeditionRate::where('expedition_id', $vendor->expedition_id)
            ->where(function ($q) use ($batchId) {
                $q->where('upload_batch_id', $batchId);
                if (str_starts_with($batchId, 'BATCH-') && is_numeric(substr($batchId, 6))) {
                    $q->orWhere('id', (int) substr($batchId, 6));
                }
            })
            ->with(['warehouse', 'destination', 'expedition'])
            ->get();

        if ($rates->isEmpty()) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Batch rate data not found for batch ID {$batchId}.");
        }

        $first = $rates->first();
        $validFrom = $rates->min('valid_from');
        $validUntil = $rates->max('valid_until');
        $validFromStr = $validFrom ? date('Y-m-d', strtotime((string)$validFrom)) : null;
        $validUntilStr = $validUntil ? date('Y-m-d', strtotime((string)$validUntil)) : null;

        $header = [
            'batch_id'        => $batchId,
            'valid_from'      => $validFromStr,
            'valid_until'     => $validUntilStr,
            'period_label'    => ($validFromStr && $validUntilStr) ? "{$validFromStr} s/d {$validUntilStr}" : ($validFromStr ?? $validUntilStr ?? '-'),
            'approval_status' => $first->approval_status ?? 'PENDING',
            'status'          => $first->status ?? 'ACTIVE',
            'total_routes'    => $rates->count(),
            'submitted_at'    => $first->created_at ? $first->created_at->format('Y-m-d H:i:s') : null,
            'remarks'         => $first->remarks,
            'expedition'      => [
                'id'              => $vendor->expedition->id ?? $vendor->expedition_id,
                'expedition_code' => $vendor->expedition->expedition_code ?? null,
                'expedition_name' => $vendor->expedition->expedition_name ?? $vendor->company_name,
            ],
        ];

        $details = $rates->map(function ($rate, $index) {
            return [
                'no'               => $index + 1,
                'id'               => $rate->id,
                'origin_code'      => $rate->warehouse->whs_code ?? null,
                'origin_name'      => $rate->warehouse->whs_name ?? null,
                'destination_code' => $rate->destination->card_code ?? null,
                'destination_name' => $rate->destination->name ?? null,
                'destination_city' => $rate->destination->city ?? null,
                'transport_mode'   => $rate->transport_mode,
                'service_type'     => $rate->service_type,
                'min_weight_kg'    => (float) $rate->min_tonnage,
                'max_weight_kg'    => (float) $rate->max_tonnage,
                'rate'             => (float) $rate->price,
                'price'            => (float) $rate->price,
                'leadtime'         => $rate->eta_days,
                'eta_days'         => $rate->eta_days,
                'valid_from'       => $rate->valid_from ? date('Y-m-d', strtotime((string)$rate->valid_from)) : null,
                'valid_until'      => $rate->valid_until ? date('Y-m-d', strtotime((string)$rate->valid_until)) : null,
                'approval_status'  => $rate->approval_status ?? 'PENDING',
                'flag'             => (bool) $rate->flag,
                'remarks'          => $rate->remarks,
            ];
        })->values()->all();

        return [
            'header'  => $header,
            'details' => $details,
        ];
    }
}
