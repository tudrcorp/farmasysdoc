<?php

namespace App\Filament\BusinessPartners\Resources\AgreementConsumptions\Pages;

use App\Filament\BusinessPartners\Resources\AgreementConsumptions\AgreementConsumptionResource;
use App\Filament\BusinessPartners\Widgets\PartnerFundSummaryWidget;
use App\Models\PartnerAgreementSale;
use App\Models\PartnerAgreementSaleItem;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListAgreementConsumptions extends ListRecords
{
    protected static string $resource = AgreementConsumptionResource::class;

    public function getTitle(): string
    {
        return 'Consumos del convenio';
    }

    public function getSubheading(): ?string
    {
        return 'Compras de sus afiliados con cargo al fondo de consumo (plan Flexible).';
    }

    protected function getHeaderWidgets(): array
    {
        return [PartnerFundSummaryWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Descargar Excel')
                ->icon(Heroicon::ArrowDownTray)
                ->color('gray')
                ->tooltip('Detalle por producto de los consumos filtrados')
                ->action(fn (): StreamedResponse => $this->exportExcel()),
        ];
    }

    private function exportExcel(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery()->with(['items', 'branch:id,name', 'plan:id,name']);

        return response()->streamDownload(function () use ($query): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $bold = (new Style)->setFontBold();

            $writer->addRow(Row::fromValues(['Fecha', 'Hora', 'Nota de entrega', 'Estado', 'Afiliado', 'Cédula', 'Teléfono', 'Sucursal', 'Plan', 'Producto', 'Código', 'Cantidad', 'Precio unit. USD', 'IVA USD', 'Importe USD', 'Total nota USD'], $bold));

            $query->chunk(200, function ($sales) use ($writer): void {
                /** @var PartnerAgreementSale $sale */
                foreach ($sales as $sale) {
                    foreach ($sale->items as $item) {
                        /** @var PartnerAgreementSaleItem $item */
                        $writer->addRow(Row::fromValues([
                            $sale->sold_at?->format('d/m/Y'),
                            $sale->sold_at?->format('h:i a'),
                            $sale->number,
                            $sale->isCancelled() ? 'Anulada (reintegrada)' : 'Aplicada',
                            $sale->client_name,
                            $sale->client_document,
                            $sale->client_phone,
                            $sale->branch?->name,
                            $sale->plan?->name,
                            $item->product_name_snapshot,
                            $item->sku_snapshot,
                            (float) $item->quantity,
                            (float) $item->unit_price,
                            (float) $item->tax_amount,
                            (float) $item->line_total,
                            (float) $sale->total,
                        ]));
                    }
                }
            });

            $writer->close();
        }, 'consumos-convenio-'.now()->format('Y-m-d').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
