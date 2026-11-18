<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\PartnerGuestOrderPdfFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PartnerGuestOrderPdfController extends Controller
{
    public function __invoke(Request $request, Order $order, PartnerGuestOrderPdfFactory $pdf): Response
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($order->partner_company_id !== null, 404);

        return $pdf->download($order);
    }
}
