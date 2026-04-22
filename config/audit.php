<?php

use App\Models\ApiClient;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Delivery;
use App\Models\FinancialSetting;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\MarketingBroadcast;
use App\Models\MarketingCampaign;
use App\Models\MarketingContent;
use App\Models\MarketingCoupon;
use App\Models\MarketingSegment;
use App\Models\MarketingUtmLink;
use App\Models\Order;
use App\Models\OrderService;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyUser;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTransfer;
use App\Models\Purchase;
use App\Models\Rol;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Registro HTTP en panel Farmaadmin
    |--------------------------------------------------------------------------
    |
    | Evita inundar la tabla: solo se escribe una fila por ventana de segundos
    | por usuario y combinación de ruta (o nombre de ruta).
    |
    */
    'http_log_window_seconds' => (int) env('AUDIT_HTTP_LOG_WINDOW_SECONDS', 45),

    /*
    |--------------------------------------------------------------------------
    | Modelos auditados (Eloquent)
    |--------------------------------------------------------------------------
    |
    | created / updated / deleted. Se omiten tablas hijas muy ruidosas
    | (p. ej. líneas de venta) para priorizar entidades de negocio.
    |
    | @var list<class-string<\Illuminate\Database\Eloquent\Model>>
    */
    'models' => [
        Sale::class,
        Purchase::class,
        Product::class,
        Inventory::class,
        InventoryMovement::class,
        Client::class,
        Supplier::class,
        Order::class,
        Branch::class,
        User::class,
        Rol::class,
        ProductTransfer::class,
        Delivery::class,
        PartnerCompany::class,
        OrderService::class,
        ApiClient::class,
        FinancialSetting::class,
        ProductCategory::class,
        MarketingCampaign::class,
        MarketingBroadcast::class,
        MarketingContent::class,
        MarketingCoupon::class,
        MarketingSegment::class,
        MarketingUtmLink::class,
        PartnerCompanyUser::class,
    ],

];
