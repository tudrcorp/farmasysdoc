<?php

namespace App\Http\Controllers;

use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Storefront\StorefrontCatalog;
use Illuminate\View\View;

final class StorefrontHomeController extends Controller
{
    public function __invoke(StorefrontCatalog $catalog, VenezuelaOfficialUsdVesRateClient $rateClient): View
    {
        $bestsellers = $catalog->bestsellers();
        $offers = $catalog->offers();
        $hasDiscounts = $offers !== [];
        $spotlight = $hasDiscounts
            ? $offers
            : $catalog->recommended(array_column($bestsellers, 'id'));

        $usdVesRate = null;

        try {
            $usdVesRate = $rateClient->rateForDate(now());
        } catch (\Throwable) {
            $usdVesRate = null;
        }

        return view('welcome', [
            'categories' => $catalog->categories(),
            'bestsellers' => $bestsellers,
            'offers' => $spotlight,
            'offersEyebrow' => $hasDiscounts ? 'Promociones' : 'Para ti',
            'offersTitle' => $hasDiscounts ? 'Ofertas de la semana' : 'Recomendados para ti',
            'whatsappUrl' => 'https://wa.me/584127018390',
            'whatsappDisplay' => '0412-701-8390',
            'ordersEmail' => 'pedidos@farmadoc.net',
            'storefrontBoot' => [
                'searchEndpoint' => route('public.products.search'),
                'checkoutEndpoint' => route('storefront.checkout'),
                'whatsappUrl' => 'https://wa.me/584127018390',
                'ordersEmail' => 'pedidos@farmadoc.net',
                'usdVesRate' => $usdVesRate,
            ],
        ]);
    }
}
