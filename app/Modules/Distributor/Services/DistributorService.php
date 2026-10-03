<?php

namespace App\Modules\Distributor\Services;

use App\Modules\Distributor\Repositories\DistributorRepositoryInterface;
use App\Modules\Distributor\Repositories\OcrCodeRepositoryInterface;
use App\Modules\AuditLog\Services\AuditLogService;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Distributor;

use Illuminate\Support\Facades\Http;

class DistributorService
{
    protected DistributorRepositoryInterface $distributorRepository;
    protected OcrCodeRepositoryInterface $ocrCodeRepository;
    protected AuditLogService $auditLogService;

    /**
     * DistributorService constructor.
     *
     * @param  DistributorRepositoryInterface  $distributorRepository
     * @param  OcrCodeRepositoryInterface  $ocrCodeRepository
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(
        DistributorRepositoryInterface $distributorRepository,
        OcrCodeRepositoryInterface $ocrCodeRepository,
        AuditLogService $auditLogService
    ) {
        $this->distributorRepository = $distributorRepository;
        $this->ocrCodeRepository = $ocrCodeRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all distributors.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->distributorRepository->getAll($filters);
    }

    /**
     * Get distributor by ID.
     *
     * @param  int  $id
     * @return Distributor|null
     */
    public function getById(int $id): ?Distributor
    {
        return $this->distributorRepository->getById($id);
    }

    /**
     * Synchronize distributors from SAP.
     *
     * @param  int|null  $userId
     * @return array
     */
    public function syncFromSap(?int $userId = null): array
    {
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(15)->post("{$sapUrl}/api/ListCust");

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP.');
        }

        $body = $response->json();
        
        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $sapData = $body['Result'] ?? [];
        $synced = [];

        foreach ($sapData as $item) {
            // Kita filter hanya yang SubGroup nya 'Distributor' (case-insensitive)
            if (isset($item['SubGroup']) && strcasecmp($item['SubGroup'], 'distributor') === 0) {
                $isBankEmpty = !isset($item['Bank']) || (string)$item['Bank'] === '-1' || trim((string)$item['Bank']) === '';

                $synced[] = $this->distributorRepository->upsertByCode([
                    'code_customer' => $item['CardCode'],
                    'name' => $item['CardName'],
                    'address' => $item['Address'] ?? null,
                    'phone' => $item['Phone1'] ?? null,
                    'email' => $item['E_Mail'] ?? null,
                    'mail_address' => $item['MailAddres'] ?? null,
                    'contact_person' => $item['CntctPrsn'] ?? null,
                    'sub_group' => $item['SubGroup'] ?? null,
                    'depo' => $item['Depo'] ?? null,
                    'status' => 1,
                    'bank_code' => $isBankEmpty ? null : ($item['Bank'] ?? null),
                    'bank_name' => $isBankEmpty ? null : ($item['BankName'] ?? null),
                    'client_bank_name' => $isBankEmpty ? null : ($item['AtasNama'] ?? null),
                    'account_bank_number' => $isBankEmpty ? null : ($item['Col12'] ?? null),
                ]);
            }
        }

        // Log to Audit Log if user is authenticated
        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_DISTRIBUTORS',
                'Synchronized ' . count($synced) . ' distributors from SAP.'
            );
        }

        return $synced;
    }

    /**
     * Get distributor addresses from SAP.
     *
     * @param  string  $customQuery
     * @return array
     */
    public function getAddressesFromSap(string $customQuery): array
    {
        $sapUrl = config('services.sap.url');
        $response = Http::timeout(15)->post("{$sapUrl}/api/GetAddress", [
            'CustomQuery' => $customQuery,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Gagal menghubungi API SAP untuk mengambil alamat.');
        }

        $body = $response->json();

        if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
            throw new \Exception('API SAP mengembalikan error: ' . ($body['Message'] ?? 'Unknown error'));
        }

        $addresses = $body['Result'] ?? [];

        // Ambil data distributor dari database lokal
        $distributor = \App\Models\Distributor::where('code_customer', $customQuery)->first();
        $depo = ($distributor && $distributor->depo) ? $distributor->depo : '-';

        // Sisipkan info depo ke setiap data alamat
        foreach ($addresses as &$address) {
            $address['depo'] = $depo;
        }

        return $addresses;
    }

    /**
     * Synchronize OCR Codes from SAP.
     *
     * @param  int|null  $userId
     * @return array
     */
    public function syncOcrCodesFromSap(?int $userId = null): array
    {
        $targets = [
            '1' => 'CABANG',
            '2' => 'UNIT',
            '3' => 'DEPARTEMENT',
        ];

        $synced = [];

        foreach ($targets as $queryParam => $targetName) {
            $sapUrl = config('services.sap.url');
            $response = Http::timeout(15)->post("{$sapUrl}/api/ListOcrCode", [
                'CustomQuery' => $queryParam,
            ]);

            if (!$response->successful()) {
                throw new \Exception("Gagal menghubungi API SAP untuk target {$targetName}.");
            }

            $body = $response->json();

            if (isset($body['ErrorCode']) && $body['ErrorCode'] !== 0) {
                throw new \Exception("API SAP mengembalikan error untuk target {$targetName}: " . ($body['Message'] ?? 'Unknown error'));
            }

            $results = $body['Result'] ?? [];
            foreach ($results as $item) {
                if (!empty($item['OcrCode'])) {
                    $synced[] = $this->ocrCodeRepository->upsert([
                        'ocr_code' => $item['OcrCode'],
                        'ocr_name' => $item['OcrName'] ?? $item['OcrCode'],
                        'distribution_target' => $targetName,
                        'status' => 1,
                    ]);
                }
            }
        }

        if ($userId) {
            $this->auditLogService->log(
                $userId,
                'SYNC_OCR_CODES',
                'Synchronized ' . count($synced) . ' OCR Codes from SAP.'
            );
        }

        return $synced;
    }

    /**
     * Get OCR codes from local database.
     *
     * @param  array  $filters
     * @return Collection
     */
    public function getOcrCodesFromDb(array $filters): Collection
    {
        $dbFilters = [];
        
        if (!empty($filters['type'])) {
            $map = [
                '1' => 'CABANG',
                '2' => 'UNIT',
                '3' => 'DEPARTEMENT',
            ];
            $dbFilters['distribution_target'] = $map[$filters['type']] ?? null;
        }

        if (!empty($filters['search'])) {
            $dbFilters['search'] = $filters['search'];
        }

        return $this->ocrCodeRepository->getAll($dbFilters);
    }
}
