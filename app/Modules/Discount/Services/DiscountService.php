<?php

namespace App\Modules\Discount\Services;

use App\Modules\Discount\Repositories\DiscountTypeRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Collection;
use App\Models\SapDiscountHeader;
use App\Models\SapDiscountDetail;
use Illuminate\Support\Facades\DB;
use Exception;

class DiscountService
{
    protected AuditLogService $auditLogService;
    protected DiscountTypeRepositoryInterface $discountTypeRepository;

    /**
     * DiscountService constructor.
     *
     * @param  AuditLogService  $auditLogService
     * @param  DiscountTypeRepositoryInterface  $discountTypeRepository
     */
    public function __construct(
        AuditLogService $auditLogService,
        DiscountTypeRepositoryInterface $discountTypeRepository
    ) {
        $this->auditLogService = $auditLogService;
        $this->discountTypeRepository = $discountTypeRepository;
    }

    /**
     * Send UDO Discount to SAP.
     *
     * @param  array  $data
     * @param  int|null  $userId
     * @return array
     * @throws Exception
     */
    public function sendToSap(array $data, ?int $userId = null): array
    {
        // 0. If OldIdDiscount is provided, delete it from local database
        if (!empty($data['OldIdDiscount'])) {
            $oldDiscount = SapDiscountHeader::where('discount_code', $data['OldIdDiscount'])->first();
            if ($oldDiscount) {
                $oldDiscount->delete();
            }
        }

        // 1. Generate Discount Code manually (YYYYMMDD + 8-digit running counter starting from 1)
        $todayPrefix = date('Ymd');
        $maxDiscountCode = SapDiscountHeader::where('discount_code', 'like', $todayPrefix . '%')
            ->max('discount_code');

        if ($maxDiscountCode) {
            $lastSequence = (int) substr($maxDiscountCode, 8);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        $code = $todayPrefix . str_pad($newSequence, 8, '0', STR_PAD_LEFT);

        // Save to local database
        DB::transaction(function () use ($code, $data, $userId) {
            $header = SapDiscountHeader::create([
                'discount_code' => $code,
                'card_code' => $data['CardCode'],
                'card_name' => $data['CardName'],
                'total_so' => 0,
                'user_id' => $userId,
            ]);

            foreach ($data['Lines'] as $line) {
                SapDiscountDetail::create([
                    'sap_discount_header_id' => $header->id,
                    'type_discount' => $line['TypeDiscount'],
                    'percentage' => (float)$line['Persentase'],
                    'total_discount' => (float)$line['TotalDiskon'],
                    'remarks' => $line['Remarks'] ?? null,
                ]);
            }
        });

        // 4. Log to Audit Log if user is authenticated
        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'CREATE_LOCAL_DISCOUNT',
                "Successfully created local Discount with Code {$code} for customer {$data['CardCode']}."
            );
        }

        return [
            'code' => $code,
            'sap_response' => [
                'ErrorCode' => 0,
                'Message' => 'Discount saved locally'
            ]
        ];
    }

    /**
     * Synchronize Discount Types from SAP.
     *
     * @param  int|null  $userId
     * @return array
     * @throws Exception
     */
    public function syncDiscountTypesFromSap(?int $userId = null): array
    {
        $response = Http::timeout(15)->post('http://103.18.133.187:3100/api/ListType');

        if (!$response->successful()) {
            throw new Exception('Gagal menghubungi API SAP untuk menyinkronkan tipe diskon.');
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new Exception('API SAP ListType mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $sapData = $body['Result'] ?? $body; // In case the array is wrapped or not
        // Wait, looking at the user's description:
        // Response dari SAP: [{ "FldValue": "Cash Diskon", "Descr": "Cash Diskon" }] or is it wrapped in "Result"?
        // Let's handle both! If $body is an array directly or has a Result wrapper.
        $types = [];
        if (isset($body['Result'])) {
            $types = $body['Result'];
        } elseif (is_array($body)) {
            $types = $body;
        }

        $synced = [];
        foreach ($types as $item) {
            if (isset($item['FldValue']) && isset($item['Descr'])) {
                $synced[] = $this->discountTypeRepository->upsert([
                    'fld_value' => $item['FldValue'],
                    'descr' => $item['Descr'],
                    'status' => 1,
                ]);
            }
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_DISCOUNT_TYPES',
                'Synchronized ' . count($synced) . ' discount types from SAP.'
            );
        }

        return $synced;
    }

    /**
     * Get Discount Types from local database.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getDiscountTypesFromDb(array $filters = []): Collection
    {
        return $this->discountTypeRepository->getAll($filters);
    }
}
