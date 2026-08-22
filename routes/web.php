<?php

use App\Http\Controllers\Dev\BdvConciliationTestController;
use App\Http\Controllers\Finance\AccountsPayableBulkPaymentReportPdfController;
use App\Http\Controllers\Finance\AccountsPayablePaymentReportPdfController;
use App\Http\Controllers\FiscalReceiptController;
use App\Http\Controllers\Hr\EmployeePortalEntryController;
use App\Http\Controllers\Hr\EmployeePortalLogoutController;
use App\Http\Controllers\Hr\EmployeePortalPayrollReceiptController;
use App\Http\Controllers\NominatimProxyController;
use App\Http\Controllers\ProductTransfers\ProductTransferReportPdfController;
use App\Http\Controllers\ProductTransfers\ProductTransferSaleReportPdfController;
use App\Http\Controllers\PublicProductSearchController;
use App\Http\Controllers\Purchases\PurchaseAnnulmentApprovalController;
use App\Http\Controllers\Purchases\PurchaseBookRetentionVoucherPdfController;
use App\Http\Controllers\Purchases\PurchaseDocumentPdfController;
use App\Http\Controllers\Reports\SystemReportsDownloadController;
use App\Http\Controllers\Sales\CashRegisterClosePdfController;
use App\Http\Controllers\Shop\ShopManifestController;
use App\Http\Controllers\StorefrontCheckoutController;
use App\Http\Controllers\StorefrontHomeController;
use App\Http\Middleware\EnsureSystemReportsAccess;
use App\Livewire\EmployeePortal\Account;
use App\Livewire\EmployeePortal\FileEnrollment;
use App\Livewire\EmployeePortal\Home;
use App\Livewire\EmployeePortal\Login;
use App\Livewire\EmployeePortal\Profile;
use App\Livewire\EmployeePortal\Receipts;
use App\Livewire\Shop\Account as ShopAccount;
use App\Livewire\Shop\Cart as ShopCart;
use App\Livewire\Shop\Categories as ShopCategories;
use App\Livewire\Shop\Category as ShopCategory;
use App\Livewire\Shop\Checkout as ShopCheckout;
use App\Livewire\Shop\Home as ShopHome;
use App\Livewire\Shop\OrderConfirmation as ShopOrderConfirmation;
use App\Livewire\Shop\Product as ShopProduct;
use App\Livewire\Shop\Search as ShopSearch;
use App\Livewire\Storefront\Pay as StorefrontPay;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', StorefrontHomeController::class)->name('home');

Route::prefix('portal')->name('employee-portal.')->group(function (): void {
    Route::get('ingresar/{employee}', EmployeePortalEntryController::class)->name('enter');

    Route::middleware('employee.portal.guest')->group(function (): void {
        Route::livewire('entrar', Login::class)->name('login');
    });

    Route::middleware('employee.portal')->group(function (): void {
        Route::get('salir', EmployeePortalLogoutController::class)->name('logout');
        Route::livewire('/', Home::class)->name('home');
        Route::livewire('perfil', Profile::class)->name('profile');
        Route::livewire('expediente', FileEnrollment::class)->name('file');
        Route::livewire('cuenta', Account::class)->name('account');
        Route::livewire('recibos', Receipts::class)->name('receipts');
        Route::get('recibos/{receipt}/descargar', EmployeePortalPayrollReceiptController::class)->name('receipts.download');
    });
});
Route::prefix('app')->name('shop.')->group(function (): void {
    Route::livewire('/', ShopHome::class)->name('home');
    Route::livewire('buscar', ShopSearch::class)->name('search');
    Route::livewire('categorias', ShopCategories::class)->name('categories');
    Route::livewire('categoria/{category}', ShopCategory::class)->name('category');
    Route::livewire('producto/{product}', ShopProduct::class)->whereNumber('product')->name('product');
    Route::livewire('carrito', ShopCart::class)->name('cart');
    Route::livewire('checkout', ShopCheckout::class)->name('checkout');
    Route::livewire('pedido/{order}', ShopOrderConfirmation::class)->name('order');
    Route::livewire('cuenta', ShopAccount::class)->name('account');
    Route::get('manifest.webmanifest', ShopManifestController::class)->name('manifest');
    Route::view('offline', 'shop.offline')->name('offline');
});

Route::view('/docs/api', 'public.api-docs')->name('public.api-docs');
Route::get('/buscar-productos', PublicProductSearchController::class)->name('public.products.search');
Route::post('/realizar-pago', StorefrontCheckoutController::class)->name('storefront.checkout');
Route::livewire('/pagar', StorefrontPay::class)->name('storefront.pay');
Route::get('/sitemap.xml', function () {
    $urls = [
        route('home'),
        route('public.api-docs'),
    ];

    $lastModified = now()->toAtomString();

    $xml = view('public.sitemap', [
        'urls' => $urls,
        'lastModified' => $lastModified,
    ])->render();

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('sitemap');

Route::middleware(['auth'])->prefix('geo')->name('geo.')->group(function (): void {
    Route::get('nominatim/search', [NominatimProxyController::class, 'search'])->name('nominatim.search');
    Route::get('nominatim/reverse', [NominatimProxyController::class, 'reverse'])->name('nominatim.reverse');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('sales/{sale}/fiscal-receipt', [FiscalReceiptController::class, 'show'])
        ->name('sales.fiscal-receipt');

    Route::get('sales/{sale}/fiscal-receipt/print', [FiscalReceiptController::class, 'print'])
        ->name('sales.fiscal-receipt.print');

    Route::get('sales/{sale}/fiscal-receipt/whatsapp-image.jpg', [FiscalReceiptController::class, 'fiscalReceiptWhatsappImage'])
        ->name('sales.fiscal-receipt.whatsapp-image');

    Route::get('sales/{sale}/credit-note', [FiscalReceiptController::class, 'showCreditNote'])
        ->name('sales.credit-note');

    Route::get('sales/{sale}/credit-note/print', [FiscalReceiptController::class, 'printCreditNote'])
        ->name('sales.credit-note.print');

    Route::get('sales/{sale}/credit-note/whatsapp-image.jpg', [FiscalReceiptController::class, 'creditNoteWhatsappImage'])
        ->name('sales.credit-note.whatsapp-image');

    Route::get('sales/{sale}/delivery-note/print', [FiscalReceiptController::class, 'printDeliveryNote'])
        ->name('sales.delivery-note.print');

    Route::get('sales/cash-close-pdf', CashRegisterClosePdfController::class)
        ->middleware('signed')
        ->name('sales.cash-close-pdf');

    Route::get('product-transfers/report-pdf', ProductTransferReportPdfController::class)
        ->middleware('signed')
        ->name('product-transfers.report-pdf');

    Route::get('product-transfer-sales/report-pdf', ProductTransferSaleReportPdfController::class)
        ->middleware('signed')
        ->name('product-transfer-sales.report-pdf');

    Route::get('purchases/{purchase}/document-pdf', PurchaseDocumentPdfController::class)
        ->name('purchases.document-pdf');

    Route::get('purchase-books/retention-voucher-pdf', PurchaseBookRetentionVoucherPdfController::class)
        ->middleware('signed')
        ->name('purchase-books.retention-voucher-pdf');

    Route::get('accounts-payables/{accountsPayable}/payment-report-pdf', AccountsPayablePaymentReportPdfController::class)
        ->middleware('signed')
        ->name('accounts-payables.payment-report-pdf');

    Route::get('accounts-payables/bulk-payment-report-pdf', AccountsPayableBulkPaymentReportPdfController::class)
        ->middleware('signed')
        ->name('accounts-payables.bulk-payment-report-pdf');

    Route::get('purchases/{purchase}/annulment-approve', [PurchaseAnnulmentApprovalController::class, 'show'])
        ->middleware('signed')
        ->name('purchases.annulment.show');

    Route::post('purchases/{purchase}/annulment-confirm', [PurchaseAnnulmentApprovalController::class, 'confirm'])
        ->middleware('signed')
        ->name('purchases.annulment.confirm');

    Route::get('purchases/{purchase}/annulment-complete', [PurchaseAnnulmentApprovalController::class, 'complete'])
        ->name('purchases.annulment.complete');

    Route::middleware([EnsureSystemReportsAccess::class])
        ->prefix('system-reports')
        ->name('system-reports.')
        ->group(function (): void {
            Route::get('download/{slug}', [SystemReportsDownloadController::class, 'download'])->name('download');
        });
});

Route::get('/pp', function () {
    /**
     * POST /api/external/service-orders
     * Body: partner_company (code), paciente, diagnosis, items[name+indicacion]
     */
    $baseUrl = 'https://farmasysdoc.test';
    $token = 'fd_ba51587cdaee4df907ef3a5441206484b5573378ee0f6fbdb7f98256e2c0f52f';

    $payload = [
        'partner_company' => 'ALDO-2026-001',
        'status' => 'en-proceso',
        'priority' => 'media',
        'service_type' => 'consulta',
        'external_reference' => 'EXT-REF-001',
        'patient_name' => 'Maria Gomez',
        'patient_document' => '1234567890',
        'patient_phone' => '3001234567',
        'patient_email' => 'maria@example.com',
        'diagnosis' => 'Control',
        'items' => [
            [
                'name' => 'Paracetamol 500 mg',
                'indicacion' => '1 tableta cada 10 horas',
            ],
            [
                'name' => 'Ibuprofeno 400 mg',
                'indicacion' => '2 tabletas cada 8 horas',
            ],
        ],
    ];

    $url = rtrim($baseUrl, '/').'/api/external/service-orders';
    // dd($url);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer '.$token,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    echo 'HTTP '.$status.PHP_EOL;
    echo $body.PHP_EOL;
})->name('pp');

Route::get('/dev/test-external-branches', function () {
    if (! app()->environment('local')) {
        abort(404);
    }

    /**
     * GET /api/external/branches?partner_company=...
     * Query opcional: ?token=fd_... (si no, edita $token abajo).
     */
    $baseUrl = rtrim((string) config('app.url'), '/');
    $token = request()->query('token', 'fd_ba51587cdaee4df907ef3a5441206484b5573378ee0f6fbdb7f98256e2c0f52f');
    $partnerCompany = request()->query('partner_company', 'ALDO-2026-001');

    $response = Http::withToken($token)
        ->acceptJson()
        ->get($baseUrl.'/api/external/branches', [
            'partner_company' => $partnerCompany,
        ]);

    return response()->json([
        'http_status' => $response->status(),
        'successful' => $response->successful(),
        'body' => $response->json() ?? $response->body(),
    ], $response->status());
})->name('dev.test-external-branches');

Route::middleware('local')->prefix('dev/bdv-conciliation')->group(function (): void {
    Route::get('/', [BdvConciliationTestController::class, 'index'])->name('dev.bdv-conciliation.index');
    Route::get('/get-movement', [BdvConciliationTestController::class, 'getMovementGet'])->name('dev.bdv-conciliation.get-movement.get');
    Route::post('/get-movement', [BdvConciliationTestController::class, 'getMovement'])->name('dev.bdv-conciliation.get-movement');
    Route::get('/try-sample-qa', [BdvConciliationTestController::class, 'trySampleQa'])->name('dev.bdv-conciliation.try-sample-qa');
});

Route::get('/bcv', function () {
    // $response = Http::timeout(5)->get('https://ve.dolarapi.com/v1/dolares');
    // dd($response->json());
    try {
        $response = Http::timeout(config('dolar.timeout', 8))
            ->acceptJson()
            ->get(rtrim((string) config('dolar.base_url'), '/').'/v1/estado');

        if (! $response->successful()) {
            return false;
        }

        $estado = $response->json('estado');

        return is_string($estado) && strcasecmp(trim($estado), 'Disponible') !== 0;
    } catch (Throwable) {
        return false;
    }
})->name('bcv');

require __DIR__.'/settings.php';
