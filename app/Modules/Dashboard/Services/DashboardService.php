<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\SalesOrder\Repositories\SalesOrderRepositoryInterface;
use App\Modules\Claim\Repositories\ResultRepositoryInterface;

class DashboardService
{
    protected SalesOrderRepositoryInterface $salesOrderRepository;
    protected ResultRepositoryInterface $resultRepository;

    /**
     * DashboardService constructor.
     *
     * @param  SalesOrderRepositoryInterface  $salesOrderRepository
     * @param  ResultRepositoryInterface  $resultRepository
     */
    public function __construct(
        SalesOrderRepositoryInterface $salesOrderRepository,
        ResultRepositoryInterface $resultRepository
    ) {
        $this->salesOrderRepository = $salesOrderRepository;
        $this->resultRepository = $resultRepository;
    }

    /**
     * Get summary metrics for Admin.
     *
     * @return array
     */
    public function getAdminSummary(): array
    {
        $salesData = $this->salesOrderRepository->getDashboardSummary(null);
        $claimData = $this->resultRepository->getDashboardSummary();

        return [
            'sales_summary' => $salesData['sales_summary'],
            'order_statuses' => $salesData['order_statuses'],
            'claims_summary' => [
                'total_program' => $claimData['total_program'] ?? 0,
                'total_batch' => $claimData['total_batch'] ?? 0,
                'total_valid_rows' => $claimData['total_valid_rows'] ?? 0,
                'total_diskon' => $claimData['total_diskon'] ?? 0.00,
            ]
        ];
    }

    /**
     * Get chart metrics for Admin.
     *
     * @return array
     */
    public function getAdminCharts(): array
    {
        $salesData = $this->salesOrderRepository->getDashboardSummary(null);

        return [
            'daily_sales_trend' => $salesData['daily_sales_trend'] ?? [],
            'top_products' => $salesData['top_products'] ?? [],
            'top_distributors' => $salesData['top_distributors'] ?? [],
        ];
    }

    /**
     * Get summary and stats for a specific Distributor.
     *
     * @param  int  $distributorId
     * @param  string  $customerCode
     * @return array
     */
    public function getDistributorSummary(int $distributorId, string $customerCode): array
    {
        $salesData = $this->salesOrderRepository->getDashboardSummary($distributorId);
        $claimData = $this->resultRepository->getRewardSummary($customerCode);

        return [
            'sales_summary' => $salesData['sales_summary'],
            'order_statuses' => $salesData['order_statuses'],
            'rewards' => [
                'total_accrued' => (float) ($claimData['total_claimed'] ?? 0.00),
                'available_balance' => (float) ($claimData['balance'] ?? 0.00),
                'pending_verification' => (float) (($claimData['total_claimed'] ?? 0.00) - ($claimData['total_verified'] ?? 0.00)),
                'withdrawn' => (float) ($claimData['withdrawn'] ?? 0.00),
            ]
        ];
    }

    /**
     * Get chart metrics for a specific Distributor.
     *
     * @param  int  $distributorId
     * @return array
     */
    public function getDistributorCharts(int $distributorId): array
    {
        $salesData = $this->salesOrderRepository->getDashboardSummary($distributorId);

        return [
            'daily_sales_trend' => $salesData['daily_sales_trend'] ?? [],
            'top_products' => $salesData['top_products'] ?? [],
        ];
    }
}
