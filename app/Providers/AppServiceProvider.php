<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use App\Modules\Auth\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Repositories\UserRepository;

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
    }
}
