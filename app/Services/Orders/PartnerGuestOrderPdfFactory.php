<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Fiscal\CompanyFiscalAddress;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprobante del pedido que el aliado descarga después de confirmarlo, sin entrar al panel.
 */
final class PartnerGuestOrderPdfFactory
{
    public function download(Order $order): Response
    {
        $order->loadMissing(['items.product', 'branch', 'partnerCompany']);

        return Pdf::loadView('pdf.partner-guest-order', $this->viewData($order))
            ->setPaper('a4', 'portrait')
            ->download($this->filename($order));
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(Order $order): array
    {
        $logoPath = public_path('images/logos/farmadoc-dark.png');
        if (! is_readable($logoPath)) {
            $logoPath = public_path('images/logos/farmadoc-ligth.png');
        }

        $company = $order->partnerCompany;
        $allyName = filled($company?->trade_name) ? (string) $company->trade_name : (string) ($company?->legal_name ?? 'Aliado');

        return [
            'order' => $order,
            'order_number' => (string) $order->order_number,
            'status_label' => $order->status?->label() ?? 'Pendiente',
            'issued_at' => $order->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
            'ally_name' => $allyName,
            'ally_code' => (string) ($company?->code ?? $order->partner_company_code ?? ''),
            'branch_name' => $order->branch?->name ?? 'Farmadoc Las Delicias',
            'fulfillment_label' => $order->partner_fulfillment_type?->label() ?? '—',
            'payment_label' => $order->partner_payment_terms?->label() ?? '—',
            'cash_method_label' => $order->partner_cash_payment_method?->label(),
            'company_name' => (string) config('fiscal.retention_agent.name'),
            'company_rif' => (string) config('fiscal.retention_agent.rif'),
            'company_address' => CompanyFiscalAddress::line(),
            'subtotal' => (float) $order->subtotal,
            'tax_total' => (float) $order->tax_total,
            'discount_total' => (float) $order->discount_total,
            'total' => (float) $order->total,
            'lines' => $order->items->map(function (OrderItem $item): array {
                $ingredients = $item->product?->active_ingredient;
                $ingredient = collect(is_array($ingredients) ? $ingredients : [$ingredients])
                    ->filter(fn (mixed $value): bool => filled($value))
                    ->implode(', ');

                return [
                    'name' => (string) ($item->product_name_snapshot ?: $item->product?->name),
                    'code' => (string) ($item->sku_snapshot ?: $item->product?->barcode ?: $item->product?->sku ?: ''),
                    'ingredient' => $ingredient,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total,
                ];
            })->all(),
            'pdf_logo_data_uri' => is_readable($logoPath)
                ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
                : null,
        ];
    }

    public function filename(Order $order): string
    {
        $number = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $order->order_number) ?: 'pedido';

        return 'pedido-'.$number.'.pdf';
    }
}
