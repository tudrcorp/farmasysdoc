<?php

use App\Http\Controllers\Api\Delivery\DeliveryActiveJobsController;
use App\Http\Controllers\Api\Delivery\DeliveryAuthController;
use App\Http\Controllers\Api\Delivery\DeliveryCompleteOrderController;
use App\Http\Controllers\Api\Delivery\DeliveryOrderDetailController;
use App\Http\Controllers\Api\Delivery\DeliveryOrderNavigationController;
use App\Http\Controllers\Api\Delivery\DeliveryPendingOrdersController;
use App\Http\Controllers\Api\Delivery\DeliveryTakeOrderController;
use App\Http\Controllers\Api\Delivery\DeliveryTransferDetailController;
use App\Http\Controllers\Api\Delivery\DeliveryTransferNavigationController;
use App\Http\Controllers\Api\Delivery\DeliveryTransferTakeController;
use App\Http\Controllers\Api\ExternalApiStatusController;
use App\Http\Controllers\Api\ExternalBranchesController;
use App\Http\Controllers\Api\ExternalBranchInventoryController;
use App\Http\Controllers\Api\ExternalInventoryController;
use App\Http\Controllers\Api\ExternalOrderController;
use App\Http\Controllers\Api\ExternalServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/delivery/auth')
    ->middleware('throttle:12,1')
    ->group(function (): void {
        Route::post('/login', [DeliveryAuthController::class, 'login'])
            ->name('api.v1.delivery.auth.login');
    });

Route::prefix('v1/delivery/auth')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->group(function (): void {
        Route::post('/logout', [DeliveryAuthController::class, 'logout'])
            ->name('api.v1.delivery.auth.logout');
    });

Route::middleware(['auth:sanctum', 'throttle:120,1'])
    ->prefix('v1/delivery')
    ->group(function (): void {
        Route::get('/orders/pending', DeliveryPendingOrdersController::class)
            ->name('api.v1.delivery.orders.pending');
        Route::get('/jobs/active', DeliveryActiveJobsController::class)
            ->name('api.v1.delivery.jobs.active');
        Route::get('/orders/{id}/navigation', DeliveryOrderNavigationController::class)
            ->whereNumber('id')
            ->name('api.v1.delivery.orders.navigation');
        Route::get('/orders/{id}', DeliveryOrderDetailController::class)
            ->whereNumber('id')
            ->name('api.v1.delivery.orders.show');
        Route::post('/orders/{id}/take', DeliveryTakeOrderController::class)
            ->whereNumber('id')
            ->name('api.v1.delivery.orders.take');
        Route::post('/orders/{id}/complete', DeliveryCompleteOrderController::class)
            ->whereNumber('id')
            ->name('api.v1.delivery.orders.complete');
        Route::get('/transfers/{id}', DeliveryTransferDetailController::class)
            ->whereNumber('id')
            ->name('api.v1.delivery.transfers.show');
        Route::post('/transfers/{id}/take', DeliveryTransferTakeController::class)
            ->whereNumber('id')
            ->name('api.v1.delivery.transfers.take');
        Route::get('/transfers/{id}/navigation', DeliveryTransferNavigationController::class)
            ->whereNumber('id')
            ->name('api.v1.delivery.transfers.navigation');
    });

Route::get('external/status', ExternalApiStatusController::class)
    ->middleware('throttle:120,1')
    ->name('api.external.status');

Route::prefix('external')
    ->middleware('api.client')
    ->group(function (): void {
        Route::get('/branches', [ExternalBranchesController::class, 'index'])
            ->name('api.external.branches.index');
        Route::get('/inventory', [ExternalInventoryController::class, 'index'])
            ->name('api.external.inventory.index');
        Route::get('/inventory-by-branch', [ExternalBranchInventoryController::class, 'index'])
            ->name('api.external.inventory-by-branch.index');
        Route::post('/orders', [ExternalOrderController::class, 'store'])
            ->name('api.external.orders.store');
        Route::post('/service-orders', [ExternalServiceOrderController::class, 'store'])
            ->name('api.external.service-orders.store');
    });
