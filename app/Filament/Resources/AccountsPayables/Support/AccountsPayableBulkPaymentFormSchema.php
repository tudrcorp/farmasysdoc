<?php

namespace App\Filament\Resources\AccountsPayables\Support;

use App\Support\Finance\AccountsPayableBulkPaymentPayload;
use App\Support\Purchases\PurchaseHistoryPaymentForm;
use App\Support\Purchases\PurchaseHistoryPaymentMethod;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Modal de pago masivo: repeater compacto, totales y datos de pago compartidos.
 */
final class AccountsPayableBulkPaymentFormSchema
{
    /**
     * @return list<Component|\Filament\Schemas\Components\Component>
     */
    public static function modalSchema(): array
    {
        return [
            Hidden::make('_bulk_error'),
            Hidden::make('_bcv_rate'),
            Hidden::make('_total_usd'),
            Hidden::make('_total_ves'),
            Placeholder::make('_bulk_error_notice')
                ->label('No se puede continuar')
                ->content(fn (Get $get): string => (string) ($get('_bulk_error') ?? ''))
                ->visible(fn (Get $get): bool => filled($get('_bulk_error')))
                ->columnSpanFull(),
            Section::make('Cuentas seleccionadas')
                ->description('Mismo total a pagar del listado: factura en Bs a la tasa de registro, menos la retención.')
                ->icon(Heroicon::DocumentText)
                ->compact()
                ->visible(fn (Get $get): bool => blank($get('_bulk_error')))
                ->schema([
                    Repeater::make('selected_lines')
                        ->hiddenLabel()
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->compact()
                        ->defaultItems(0)
                        ->extraAttributes(['class' => 'max-w-full overflow-x-auto'])
                        ->table([
                            TableColumn::make('Proveedor')->width('11rem'),
                            TableColumn::make('Nº factura')->width('7rem'),
                            TableColumn::make('RIF')->width('8rem'),
                            TableColumn::make('Nº orden compra')->width('8rem'),
                            TableColumn::make('Sucursal')->width('9rem'),
                            TableColumn::make('Emisión')->width('6rem'),
                            TableColumn::make('Vencimiento')->width('6.5rem'),
                            TableColumn::make('Tasa BCV registro')->width('8.5rem')->alignment(Alignment::End),
                            TableColumn::make('Total (USD)')->width('7rem')->alignment(Alignment::End),
                            TableColumn::make('Total factura (Bs, tasa emisión)')->width('9rem')->alignment(Alignment::End),
                            TableColumn::make('IVA factura')->width('7.5rem')->alignment(Alignment::End),
                            TableColumn::make('% retención')->width('6rem')->alignment(Alignment::End),
                            TableColumn::make('Total retenido')->width('7.5rem')->alignment(Alignment::End),
                            TableColumn::make('Total a pagar')->width('8rem')->alignment(Alignment::End),
                        ])
                        ->schema([
                            Hidden::make('accounts_payable_id'),
                            self::readOnlyLine('supplier_name'),
                            self::readOnlyLine('invoice_number'),
                            self::readOnlyLine('rif'),
                            self::readOnlyLine('purchase_number'),
                            self::readOnlyLine('branch_name'),
                            self::readOnlyLine('issued_at_label'),
                            self::readOnlyLine('due_at_label'),
                            self::readOnlyLine('bcv_rate_label', true),
                            self::readOnlyLine('amount_usd_label', true),
                            self::readOnlyLine('invoice_total_ves_label', true),
                            self::readOnlyLine('tax_caused_label', true),
                            self::readOnlyLine('retention_percent_label', true),
                            self::readOnlyLine('tax_retained_label', true),
                            self::readOnlyLine('amount_ves_label', true),
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
            Section::make('Total del pago')
                ->icon(Heroicon::Calculator)
                ->compact()
                ->visible(fn (Get $get): bool => blank($get('_bulk_error')))
                ->schema([
                    Placeholder::make('grand_total_calculation')
                        ->hiddenLabel()
                        ->content(fn (Get $get): HtmlString => new HtmlString(
                            '<p class="text-sm font-medium text-gray-900 dark:text-gray-100">'
                            .e(self::formatUsd((float) ($get('_total_usd') ?? 0)))
                            .' · '.e(self::formatBs((float) ($get('_total_ves') ?? 0)))
                            .'</p>'
                        ))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
            Section::make('Datos del pago')
                ->description('Se aplican a todas las cuentas seleccionadas.')
                ->icon(Heroicon::Banknotes)
                ->compact()
                ->visible(fn (Get $get): bool => blank($get('_bulk_error')))
                ->schema(AccountsPayablePaymentFormSchema::paymentFields(false, true))
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fillFormStateFromPayload(AccountsPayableBulkPaymentPayload $payload): array
    {
        if (! $payload->ok) {
            return [
                '_bulk_error' => $payload->error,
                'selected_lines' => [],
                '_total_usd' => 0,
                '_total_ves' => 0,
                '_bcv_rate' => $payload->rate,
                'payment_method' => PurchaseHistoryPaymentMethod::TRANSFERENCIA,
                'payment_form' => PurchaseHistoryPaymentForm::LIQUIDACION_TOTAL,
                'paid_at' => now(),
                'payment_reference' => '',
                'notes' => null,
                'payment_proof_path' => null,
            ];
        }

        return [
            '_bulk_error' => null,
            'selected_lines' => $payload->selectedLines,
            '_total_usd' => $payload->totalUsd,
            '_total_ves' => $payload->totalVes,
            '_bcv_rate' => $payload->rate,
            'payment_method' => PurchaseHistoryPaymentMethod::TRANSFERENCIA,
            'payment_form' => PurchaseHistoryPaymentForm::LIQUIDACION_TOTAL,
            'paid_at' => now(),
            'payment_reference' => '',
            'notes' => null,
            'payment_proof_path' => null,
        ];
    }

    private static function readOnlyLine(string $name, bool $numeric = false): TextInput
    {
        $input = TextInput::make($name)
            ->hiddenLabel()
            ->disabled()
            ->dehydrated(false);

        if ($numeric) {
            $input->extraInputAttributes(['class' => 'text-end font-medium tabular-nums']);
        }

        return $input;
    }

    private static function formatUsd(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' USD';
    }

    private static function formatBs(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }
}
