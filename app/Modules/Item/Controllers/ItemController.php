<?php

namespace App\Modules\Item\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Item\Services\ItemService;
use App\Traits\ApiResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    use ApiResponseFormatter;

    protected ItemService $itemService;

    /**
     * ItemController constructor.
     *
     * @param  ItemService  $itemService
     */
    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * Display a listing of items.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['search', 'code_customer']);

        $roleName = strtolower($user?->role?->name ?? '');
        $adminRoles = ['administrator', 'admin finance', 'admin sales', 'admin logistic', 'super admin', 'superadmin'];
        $isAdmin = in_array($roleName, $adminRoles, true);

        // If user is a distributor, restrict code_customer to their own customer code
        if (!$isAdmin && $user && $user->code_customer) {
            $allowedCodes = array_filter(array_map('trim', explode(',', $user->code_customer)));
            if (!empty($filters['code_customer'])) {
                if (!in_array($filters['code_customer'], $allowedCodes, true)) {
                    return $this->errorResponse('Anda tidak memiliki akses ke data customer ini.', 403);
                }
            } else {
                // If not specified, default to the distributor's primary code to load their prices
                $filters['code_customer'] = $allowedCodes[0] ?? null;
            }
        }

        $items = $this->itemService->getAll($filters);

        return $this->successResponse($items, 'Daftar item berhasil diambil.');
    }

    /**
     * Synchronize items from SAP.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        try {
            $syncedData = $this->itemService->syncFromSap($userId);
            return $this->successResponse($syncedData, 'Data item berhasil disinkronisasi dari SAP.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
