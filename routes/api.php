<?php

use App\Http\Controllers\Api\ExternalApiStatusController;
use App\Http\Controllers\Api\ExternalInventoryController;
use App\Http\Controllers\Api\ExternalOrderController;
use App\Http\Controllers\Api\ExternalServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::get('external/status', ExternalApiStatusController::class)
    ->middleware('throttle:120,1')
    ->name('api.external.status');

Route::prefix('external')
    ->middleware('api.client')
    ->group(function (): void {
        Route::get('/inventory', [ExternalInventoryController::class, 'index'])
            ->name('api.external.inventory.index');
        Route::post('/orders', [ExternalOrderController::class, 'store'])
            ->name('api.external.orders.store');
        Route::post('/service-orders', [ExternalServiceOrderController::class, 'store'])
            ->name('api.external.service-orders.store');
    });
