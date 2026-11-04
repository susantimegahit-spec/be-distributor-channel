<?php

namespace App\Modules\Ekspedisi\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MasterLeadtime;
use App\Models\WarehouseOrigin;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MasterLeadtimeController extends Controller
{
    use ApiResponseFormatter;

    /**
     * Display a listing of the master lead times.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MasterLeadtime::with(['warehouseOrigin', 'destinationRegency', 'creator', 'updater']);

        if ($request->filled('search')) {
            $search = trim($request->get('search'));
            $likeOp = config('database.default') === 'sqlite' ? 'LIKE' : 'ILIKE';

            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('origin_warehouse_name', $likeOp, "%{$search}%")
                  ->orWhere('origin_warehouse_code', $likeOp, "%{$search}%")
                  ->orWhere('origin_city', $likeOp, "%{$search}%")
                  ->orWhere('destination_name', $likeOp, "%{$search}%")
                  ->orWhere('destination_code', $likeOp, "%{$search}%")
                  ->orWhere('destination_city', $likeOp, "%{$search}%")
                  ->orWhere('destination_province', $likeOp, "%{$search}%")
                  ->orWhere('remarks', $likeOp, "%{$search}%");
            });
        }

        if ($request->filled('origin_warehouse_code')) {
            $query->where('origin_warehouse_code', $request->get('origin_warehouse_code'));
        }

        if ($request->filled('destination_city')) {
            $likeOp = config('database.default') === 'sqlite' ? 'LIKE' : 'ILIKE';
            $query->where('destination_city', $likeOp, "%" . trim($request->get('destination_city')) . "%");
        }

        if ($request->filled('destination_code')) {
            $query->where('destination_code', $request->get('destination_code'));
        }

        if ($request->filled('transport_mode')) {
            $query->where('transport_mode', strtoupper(trim($request->get('transport_mode'))));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper(trim($request->get('status'))));
        }

        $perPage = (int) $request->get('per_page', 15);
        $leadtimes = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse($leadtimes, 'Master lead times retrieved successfully.');
    }

    /**
     * Store a newly created master lead time.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'warehouse_origin_id'   => 'nullable|integer',
            'origin_warehouse_code' => 'nullable|string|max:50',
            'origin_warehouse_name' => 'nullable|string|max:255',
            'origin_city'           => 'nullable|string|max:100',
            'destination_id'        => 'nullable|integer',
            'destination_regency_id'=> 'nullable|integer',
            'destination_code'      => 'nullable|string|max:50',
            'destination_name'      => 'required|string|max:255',
            'destination_city'      => 'nullable|string|max:100',
            'destination_province'  => 'nullable|string|max:100',
            'avg_lead_time_days'    => 'required|numeric|min:0',
            'min_lead_time_days'    => 'nullable|numeric|min:0',
            'max_lead_time_days'    => 'nullable|numeric|min:0',
            'lead_time_unit'        => 'nullable|string|in:DAYS,HOURS',
            'transport_mode'        => 'nullable|string|max:50',
            'distance_km'           => 'nullable|numeric|min:0',
            'status'                => 'nullable|string|in:ACTIVE,INACTIVE',
            'remarks'               => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $data = $validator->validated();

        // Auto-fill origin info if warehouse_origin_id is provided
        if (!empty($data['warehouse_origin_id'])) {
            $origin = WarehouseOrigin::find($data['warehouse_origin_id']);
            if ($origin) {
                $data['origin_warehouse_code'] = $data['origin_warehouse_code'] ?? $origin->whs_code;
                $data['origin_warehouse_name'] = $data['origin_warehouse_name'] ?? $origin->whs_name_origin;
                $data['origin_city'] = $data['origin_city'] ?? ($origin->warehouse ? $origin->warehouse->city : null);
            }
        }

        if (empty($data['origin_warehouse_name'])) {
            $data['origin_warehouse_name'] = $data['origin_warehouse_code'] ?? 'Main Warehouse';
        }

        $data['lead_time_unit'] = strtoupper($data['lead_time_unit'] ?? 'DAYS');
        $data['status'] = strtoupper($data['status'] ?? 'ACTIVE');
        $data['transport_mode'] = strtoupper($data['transport_mode'] ?? 'LAND');
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $leadtime = MasterLeadtime::create($data);

        return $this->successResponse(
            $leadtime->load(['warehouseOrigin', 'destinationRegency', 'creator']),
            'Master lead time created successfully.',
            201
        );
    }

    /**
     * Display the specified master lead time.
     */
    public function show(int $id): JsonResponse
    {
        $leadtime = MasterLeadtime::with(['warehouseOrigin', 'destinationRegency', 'creator', 'updater'])->find($id);

        if (!$leadtime) {
            return $this->errorResponse('Master lead time not found.', [], 404);
        }

        return $this->successResponse($leadtime, 'Master lead time detail retrieved successfully.');
    }

    /**
     * Update the specified master lead time.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $leadtime = MasterLeadtime::find($id);

        if (!$leadtime) {
            return $this->errorResponse('Master lead time not found.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'warehouse_origin_id'   => 'nullable|integer',
            'origin_warehouse_code' => 'nullable|string|max:50',
            'origin_warehouse_name' => 'nullable|string|max:255',
            'origin_city'           => 'nullable|string|max:100',
            'destination_id'        => 'nullable|integer',
            'destination_regency_id'=> 'nullable|integer',
            'destination_code'      => 'nullable|string|max:50',
            'destination_name'      => 'sometimes|required|string|max:255',
            'destination_city'      => 'nullable|string|max:100',
            'destination_province'  => 'nullable|string|max:100',
            'avg_lead_time_days'    => 'sometimes|required|numeric|min:0',
            'min_lead_time_days'    => 'nullable|numeric|min:0',
            'max_lead_time_days'    => 'nullable|numeric|min:0',
            'lead_time_unit'        => 'nullable|string|in:DAYS,HOURS',
            'transport_mode'        => 'nullable|string|max:50',
            'distance_km'           => 'nullable|numeric|min:0',
            'status'                => 'nullable|string|in:ACTIVE,INACTIVE',
            'remarks'               => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), [], 422);
        }

        $data = $validator->validated();

        if (isset($data['lead_time_unit'])) {
            $data['lead_time_unit'] = strtoupper($data['lead_time_unit']);
        }
        if (isset($data['status'])) {
            $data['status'] = strtoupper($data['status']);
        }
        if (isset($data['transport_mode'])) {
            $data['transport_mode'] = strtoupper($data['transport_mode']);
        }

        $data['updated_by'] = auth()->id();

        $leadtime->update($data);

        return $this->successResponse(
            $leadtime->fresh(['warehouseOrigin', 'destinationRegency', 'creator', 'updater']),
            'Master lead time updated successfully.'
        );
    }

    /**
     * Remove the specified master lead time.
     */
    public function destroy(int $id): JsonResponse
    {
        $leadtime = MasterLeadtime::find($id);

        if (!$leadtime) {
            return $this->errorResponse('Master lead time not found.', [], 404);
        }

        $leadtime->delete();

        return $this->successResponse(null, 'Master lead time deleted successfully.');
    }
}
