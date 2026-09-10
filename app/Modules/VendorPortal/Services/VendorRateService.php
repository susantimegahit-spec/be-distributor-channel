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

        $payload = [
            'expedition_id'    => $vendor->expedition_id,
            'warehouse_id'     => $warehouseId,
            'destination_id'   => $destinationId,
            'transport_mode'   => $transportMode,
            'service_type'     => $serviceType,
            'min_tonnage'      => $minTonnage,
            'max_tonnage'      => $maxTonnage,
            'price'            => $price,
            'eta_days'         => !empty($data['eta_days']) ? (int) $data['eta_days'] : null,
            'min_shipment_qty' => floatval($data['min_shipment_qty'] ?? 0),
            'max_shipment_qty' => floatval($data['max_shipment_qty'] ?? 0),
            'valid_from'       => !empty($data['valid_from']) ? $data['valid_from'] : null,
            'valid_until'      => !empty($data['valid_until']) ? $data['valid_until'] : null,
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
    public function uploadRates(Vendor $vendor, UploadedFile $file, ?int $userId = null): array
    {
        if (!$vendor->expedition_id) {
            throw ValidationException::withMessages([
                'vendor' => ['Vendor is not linked to an expedition master record yet.'],
            ]);
        }

        return $this->uploadService->uploadRates($file, $vendor->expedition_id);
    }

    /**
     * Generate sample CSV template content for vendor rate submission.
     */
    public function generateTemplateCsv(): string
    {
        $headers = [
            'warehouse_code',
            'destination_id',
            'transport_mode',
            'service_type',
            'min_tonnage',
            'max_tonnage',
            'price',
            'eta_days',
            'min_shipment_qty',
            'max_shipment_qty',
            'valid_from',
            'valid_until',
            'remarks',
        ];

        $sampleRow = [
            'PRD01-01',
            '1',
            'DARAT',
            'REGULER',
            '0',
            '1000',
            '1500000',
            '3',
            '1',
            '100',
            date('Y-m-d'),
            date('Y-m-d', strtotime('+1 year')),
            'Pengajuan tarif reguler via vendor portal',
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        fputcsv($output, $sampleRow);
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }
}
