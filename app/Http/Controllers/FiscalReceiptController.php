<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\Fiscal\ThermalFiscalReceiptFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class FiscalReceiptController extends Controller
{
    public function show(Request $request, Sale $sale): Response
    {
        abort_unless(Auth::check(), 403);

        $sale->load(['branch', 'client', 'items']);

        $formatter = app(ThermalFiscalReceiptFormatter::class);
        $plain = $formatter->format($sale);

        if ($request->query('format') === 'escpos') {
            return response($formatter->wrapEscPos($plain), 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="factura-'.$sale->sale_number.'.bin"',
            ]);
        }

        return response($plain, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="factura-'.$sale->sale_number.'.txt"',
        ]);
    }
}
