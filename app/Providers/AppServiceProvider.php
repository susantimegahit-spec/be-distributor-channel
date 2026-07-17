<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use App\Modules\Auth\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Role\Repositories\RoleRepositoryInterface;
use App\Modules\Role\Repositories\RoleRepository;
use App\Modules\User\Repositories\UserCrudRepositoryInterface;
use App\Modules\User\Repositories\UserCrudRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );
        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class
        );
        $this->app->bind(
            UserCrudRepositoryInterface::class,
            UserCrudRepository::class
        );
        $this->app->bind(
            \App\Modules\Distributor\Repositories\DistributorRepositoryInterface::class,
            \App\Modules\Distributor\Repositories\DistributorRepository::class
        );
        $this->app->bind(
            \App\Modules\Distributor\Repositories\OcrCodeRepositoryInterface::class,
            \App\Modules\Distributor\Repositories\OcrCodeRepository::class
        );
        $this->app->bind(
            \App\Modules\SalesOrder\Repositories\SalesOrderRepositoryInterface::class,
            \App\Modules\SalesOrder\Repositories\SalesOrderRepository::class
        );
        $this->app->bind(
            \App\Modules\Item\Repositories\ItemRepositoryInterface::class,
            \App\Modules\Item\Repositories\ItemRepository::class
        );
        $this->app->bind(
            \App\Modules\SalesEmployee\Repositories\SalesEmployeeRepositoryInterface::class,
            \App\Modules\SalesEmployee\Repositories\SalesEmployeeRepository::class
        );
        $this->app->bind(
            \App\Modules\Vat\Repositories\VatRepositoryInterface::class,
            \App\Modules\Vat\Repositories\VatRepository::class
        );
        $this->app->bind(
            \App\Modules\Warehouse\Repositories\WarehouseRepositoryInterface::class,
            \App\Modules\Warehouse\Repositories\WarehouseRepository::class
        );
        $this->app->bind(
            \App\Modules\DistributorItemPrice\Repositories\DistributorItemPriceRepositoryInterface::class,
            \App\Modules\DistributorItemPrice\Repositories\DistributorItemPriceRepository::class
        );
        $this->app->bind(
            \App\Modules\Discount\Repositories\DiscountTypeRepositoryInterface::class,
            \App\Modules\Discount\Repositories\DiscountTypeRepository::class
        );
        $this->app->bind(
            \App\Modules\Claim\Repositories\ProgramRepositoryInterface::class,
            \App\Modules\Claim\Repositories\ProgramRepository::class
        );
        $this->app->bind(
            \App\Modules\Claim\Repositories\UploadRepositoryInterface::class,
            \App\Modules\Claim\Repositories\UploadRepository::class
        );
        $this->app->bind(
            \App\Modules\Claim\Repositories\ResultRepositoryInterface::class,
            \App\Modules\Claim\Repositories\ResultRepository::class
        );
        $this->app->bind(
            \App\Modules\Claim\Repositories\WithdrawRepositoryInterface::class,
            \App\Modules\Claim\Repositories\WithdrawRepository::class
        );
        $this->app->bind(
            \App\Modules\Claim\Repositories\TrxClaimBalanceLedgerRepositoryInterface::class,
            \App\Modules\Claim\Repositories\TrxClaimBalanceLedgerRepository::class
        );
        $this->app->bind(
            \App\Modules\SalesDistributor\Repositories\SalesDistributorRepositoryInterface::class,
            \App\Modules\SalesDistributor\Repositories\SalesDistributorRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Dynamically load routes from app/Modules/*/Routes/api.php
        $modulesPath = app_path('Modules');
        if (File::isDirectory($modulesPath)) {
            $modules = File::directories($modulesPath);
            foreach ($modules as $module) {
                $routeFile = $module . '/Routes/api.php';
                if (File::exists($routeFile)) {
                    Route::middleware('api')
                        ->prefix('api/distributor-channel')
                        ->group($routeFile);
                }
            }
        }

        // Dynamically load migrations from custom folders
        $this->loadMigrationsFrom([
            database_path('migrations/ekspedisi'),
            database_path('migrations/production'),
        ]);
    }
}
