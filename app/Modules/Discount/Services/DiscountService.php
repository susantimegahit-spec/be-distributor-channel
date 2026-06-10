<?php

namespace App\Modules\Discount\Services;

use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Support\Facades\Http;
use Exception;

class DiscountService
{
    protected AuditLogService $auditLogService;

    /**
     * DiscountService constructor.
     *
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
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
}
