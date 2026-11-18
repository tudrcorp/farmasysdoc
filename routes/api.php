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
use App\Http\Controllers\Api\FiscalAgent\FiscalAgentController;
use App\Http\Controllers\Api\WhatsappAgent\UltramsgWebhookController;
use App\Http\Controllers\Api\WhatsappAgent\WhatsappAgentCartController;
use App\Http\Controllers\Api\WhatsappAgent\WhatsappAgentConversationController;
use App\Http\Controllers\Api\WhatsappAgent\WhatsappAgentMessageController;
use App\Http\Controllers\Api\WhatsappAgent\WhatsappAgentPrescriptionController;
use App\Http\Controllers\Api\WhatsappAgent\WhatsappAgentProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/delivery/auth')
    ->group(function (): void {
        Route::post('/login', [DeliveryAuthController::class, 'login'])
            ->name('api.v1.delivery.auth.login');
    });

Route::prefix('v1/delivery/auth')
    ->middleware(['auth:sanctum'])
    ->group(function (): void {
        Route::post('/logout', [DeliveryAuthController::class, 'logout'])
            ->name('api.v1.delivery.auth.logout');
    });

Route::middleware(['auth:sanctum'])
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

Route::prefix('fiscal-agent/v1')
    ->middleware('fiscal.agent')
    ->group(function (): void {
        Route::post('/jobs/claim', [FiscalAgentController::class, 'claim'])
            ->name('api.fiscal-agent.v1.jobs.claim');
        Route::post('/jobs/{uuid}/started', [FiscalAgentController::class, 'started'])
            ->whereUuid('uuid')
            ->name('api.fiscal-agent.v1.jobs.started');
        Route::post('/jobs/{uuid}/result', [FiscalAgentController::class, 'result'])
            ->whereUuid('uuid')
            ->name('api.fiscal-agent.v1.jobs.result');
        Route::post('/heartbeat', [FiscalAgentController::class, 'heartbeat'])
            ->name('api.fiscal-agent.v1.heartbeat');
    });

Route::post('webhooks/whatsapp/ultramsg/{secret}', UltramsgWebhookController::class)
    ->where('secret', '[A-Za-z0-9]+')
    ->middleware('throttle:600,1')
    ->name('api.whatsapp.ultramsg.webhook');

Route::prefix('whatsapp-agent/v1')
    ->middleware('whatsapp.agent')
    ->group(function (): void {
        Route::get('/conversations/{conversation}/context', [WhatsappAgentConversationController::class, 'context'])
            ->name('api.whatsapp-agent.conversations.context');
        Route::post('/conversations/{conversation}/reply', [WhatsappAgentConversationController::class, 'reply'])
            ->name('api.whatsapp-agent.conversations.reply');
        Route::patch('/conversations/{conversation}/state', [WhatsappAgentConversationController::class, 'updateState'])
            ->name('api.whatsapp-agent.conversations.state');
        Route::post('/conversations/{conversation}/customer', [WhatsappAgentConversationController::class, 'registerCustomer'])
            ->name('api.whatsapp-agent.conversations.customer');
        Route::post('/conversations/{conversation}/confirm-order', [WhatsappAgentConversationController::class, 'confirmOrder'])
            ->name('api.whatsapp-agent.conversations.confirm-order');
        Route::post('/conversations/{conversation}/escalate', [WhatsappAgentConversationController::class, 'escalate'])
            ->name('api.whatsapp-agent.conversations.escalate');
        Route::post('/conversations/{conversation}/items', [WhatsappAgentCartController::class, 'store'])
            ->name('api.whatsapp-agent.conversations.items.store');
        Route::delete('/conversations/{conversation}/items/{item}', [WhatsappAgentCartController::class, 'destroy'])
            ->whereNumber('item')
            ->name('api.whatsapp-agent.conversations.items.destroy');
        Route::post('/conversations/{conversation}/prescription-readings', [WhatsappAgentPrescriptionController::class, 'store'])
            ->name('api.whatsapp-agent.conversations.prescription-readings.store');
        Route::get('/messages/{message}/media', [WhatsappAgentMessageController::class, 'media'])
            ->name('api.whatsapp-agent.messages.media');
        Route::post('/messages/{message}/transcription', [WhatsappAgentMessageController::class, 'transcription'])
            ->name('api.whatsapp-agent.messages.transcription');
        Route::get('/products/search', [WhatsappAgentProductController::class, 'search'])
            ->name('api.whatsapp-agent.products.search');
    });
