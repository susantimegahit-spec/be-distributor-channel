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
        // 1. Fetch code from GetNumDis
        $codeResponse = Http::timeout(15)->post('http://103.18.133.187:3100/api/GetNumDis');
        
        if (!$codeResponse->successful()) {
            throw new Exception('Gagal menghubungi API SAP untuk mendapatkan kode diskon.');
        }

        $codeBody = $codeResponse->json();
        
        if (isset($codeBody['ErrorCode']) && $codeBody['ErrorCode'] !== 0) {
            throw new Exception('API SAP GetNumDis mengembalikan error: ' . ($codeBody['Message'] ?? 'Unknown error'));
        }

        $code = $codeBody['Result'][0]['Col1'] ?? null;
        if (!$code) {
            throw new Exception('Nomor diskon tidak ditemukan di respon SAP GetNumDis.');
        }

        // 2. Build payload for SAP
        $payload = [
            'Code' => $code,
            'Name' => 'test',
            'CardCode' => $data['CardCode'],
            'CardName' => $data['CardName'],
            'TotalSO' => 0,
            'Lines' => array_map(function ($line) {
                return [
                    'TypeDiscount' => $line['TypeDiscount'],
                    'Persentase' => (float)$line['Persentase'],
                    'TotalDiskon' => (float)$line['TotalDiskon'],
                    'Remarks' => $line['Remarks'] ?? '',
                ];
            }, $data['Lines'])
        ];

        // 3. Post to addudodiskon
        $response = Http::timeout(15)->post('http://103.18.133.187:3100/api/addudodiskon', $payload);

        if (!$response->successful()) {
            throw new Exception('Gagal menghubungi API SAP addudodiskon.');
        }

        $body = $response->json();
        
        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new Exception('API SAP addudodiskon mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

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
                'POST_UDO_DISCOUNT_SAP',
                "Successfully sent UDO Discount with Code {$code} for customer {$data['CardCode']} to SAP."
            );
        }

        return [
            'code' => $code,
            'sap_response' => $body
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
