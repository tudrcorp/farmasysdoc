<?php

namespace App\Filament\Resources\PurchaseBooks\Pages;

use App\Filament\Resources\PurchaseBooks\PurchaseBookResource;
use App\Models\PurchaseBook;
use App\Services\Audit\AuditLogger;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewPurchaseBook extends ViewRecord
{
    protected static string $resource = PurchaseBookResource::class;

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();
        if ($record instanceof PurchaseBook) {
            return 'Comprobante '.$record->voucher_number;
        }

        return 'Detalle de retención';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $record = $this->getRecord();
        if (! $record instanceof PurchaseBook) {
            return null;
        }

        $date = $record->invoice_date?->format('d/m/Y') ?? 'sin fecha';
        $retained = number_format((float) $record->tax_retained_ves, 2, ',', '.').' Bs retenidos';

        return $record->supplier_name.' · factura '.$date.' · '.$retained;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $model = $this->getRecord();
        if ($model instanceof PurchaseBook) {
            AuditLogger::forModel(
                $model,
                'purchase_book_viewed',
                [
                    'voucher_number' => $model->voucher_number,
                    'tax_period' => $model->tax_period,
                ],
            );
        }
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToList')
                ->label('Volver al libro')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->url(PurchaseBookResource::getUrl('index')),
        ];
    }
}
