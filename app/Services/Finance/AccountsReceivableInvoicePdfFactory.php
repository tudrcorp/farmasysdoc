<?php

namespace App\Services\Finance;

use App\Models\AccountsReceivable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\Finance\AccountsReceivableStatus;
use App\Support\Fiscal\CompanyFiscalAddress;
use App\Support\Sales\SalePaymentMethodLabels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Factura comercial interna de una cuenta por cobrar.
 * No es comprobante fiscal ni sustituye la factura del SENIAT.
 */
final class AccountsReceivableInvoicePdfFactory
{
    /**
     * @return array<string, mixed>
     */
    public function viewData(AccountsReceivable $accountsReceivable, ?User $actor = null): array
    {
        $accountsReceivable->loadMissing(['branch', 'client', 'sale.client', 'sale.items']);

        $sale = $accountsReceivable->sale;
        $generatedAt = now();
        $logoPath = public_path('images/logos/farmadoc-ligth.png');

        return [
            'accounts_receivable' => $accountsReceivable,
            'sale' => $sale,
            'lines' => $this->lines($accountsReceivable, $sale),
            'document_number' => $accountsReceivable->sale_number_snapshot ?: (string) $accountsReceivable->getKey(),
            'status_label' => AccountsReceivableStatus::label($accountsReceivable->status),
            'branch_name' => $accountsReceivable->branch?->name ?? '—',
            'client_name' => $accountsReceivable->client_name_snapshot ?: ($sale?->client?->name ?? '—'),
            'client_document' => $accountsReceivable->client_document_snapshot ?: '—',
            'payment_method' => SalePaymentMethodLabels::label($sale?->payment_method),
            'company_name' => (string) config('fiscal.retention_agent.name'),
            'company_rif' => (string) config('fiscal.retention_agent.rif'),
            'company_address' => CompanyFiscalAddress::line(),
            'subtotal_usd' => $sale !== null ? (float) $sale->subtotal : (float) $accountsReceivable->sale_total_usd,
            'discount_usd' => $sale !== null ? (float) $sale->discount_total : 0.0,
            'tax_usd' => $sale !== null ? (float) $sale->tax_total : 0.0,
            'igtf_usd' => $sale !== null ? (float) $sale->igtf_total : 0.0,
            'total_usd' => $sale !== null ? (float) $sale->total : (float) $accountsReceivable->sale_total_usd,
            'paid_usd' => (float) $accountsReceivable->paid_equivalent_usd,
            'remaining_usd' => (float) $accountsReceivable->remaining_principal_usd,
            'total_ves' => (float) $accountsReceivable->sale_total_ves_reference,
            'balance_ves' => (float) $accountsReceivable->current_balance_ves,
            'bcv_rate' => $accountsReceivable->bcv_ves_per_usd_snapshot !== null
                ? (float) $accountsReceivable->bcv_ves_per_usd_snapshot
                : null,
            'issued_at' => $accountsReceivable->issued_at?->format('d/m/Y') ?? '—',
            'due_at' => $accountsReceivable->due_at?->format('d/m/Y') ?? '—',
            'generated_at' => $generatedAt->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'generated_by' => $actor?->name ?? 'sistema',
            'notes' => $accountsReceivable->notes,
            'pdf_logo_data_uri' => is_readable($logoPath)
                ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
                : null,
        ];
    }

    public function download(AccountsReceivable $accountsReceivable, ?User $actor = null): Response
    {
        return Pdf::loadView('pdf.accounts-receivable-invoice', $this->viewData($accountsReceivable, $actor))
            ->setPaper('a4', 'portrait')
            ->stream($this->filename($accountsReceivable));
    }

    public function filename(AccountsReceivable $accountsReceivable): string
    {
        $saleNumber = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '-',
            (string) ($accountsReceivable->sale_number_snapshot ?: $accountsReceivable->getKey()),
        ) ?: 'cxc';

        return 'factura-comercial-'.$saleNumber.'.pdf';
    }

    /**
     * @return Collection<int, array{description: string, sku: string, quantity: float, unit_price: float, line_total: float}>
     */
    private function lines(AccountsReceivable $accountsReceivable, ?Sale $sale): Collection
    {
        $items = $sale?->items ?? collect();

        if ($items->isEmpty()) {
            return collect([[
                'description' => 'Venta a crédito '.$accountsReceivable->sale_number_snapshot,
                'sku' => '—',
                'quantity' => 1.0,
                'unit_price' => (float) $accountsReceivable->sale_total_usd,
                'line_total' => (float) $accountsReceivable->sale_total_usd,
            ]]);
        }

        return $items->map(function (SaleItem $item): array {
            return [
                'description' => $item->product_name_snapshot ?: 'Producto',
                'sku' => $item->sku_snapshot ?: '—',
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ];
        })->values();
    }
}
