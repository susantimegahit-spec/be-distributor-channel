<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Frequent Status Sync
Schedule::command('sap:sync-pdo-status')->everyTenMinutes();
Schedule::command('sap:sync-order-status')->everyTenMinutes();

// Daily Master Data Sync from SAP B1
Schedule::command('sap:sync-discount-types')->daily();
Schedule::command('sap:sync-sales-employees')->daily();
Schedule::command('sap:sync-warehouses')->daily();
Schedule::command('sap:sync-ocr-codes')->daily();
