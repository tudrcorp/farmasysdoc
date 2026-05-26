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
                ->description('Principal pendiente en USD × tasa BCV del día.')
                ->icon(Heroicon::DocumentText)
                ->compact()
                ->visible(fn (Get $get): bool => blank($get('_bulk_error')))
                ->schema([
                    Repeater::make('selected_lines')
                        ->label('')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->defaultItems(0)
                        ->table([
                            TableColumn::make('Proveedor · factura')->width('38%'),
                            TableColumn::make('OC')->width('8rem'),
                            TableColumn::make('Vence')->width('5.5rem'),
                            TableColumn::make('USD')->width('7rem')->alignment(Alignment::End),
                            TableColumn::make('Bs')->width('8.5rem')->alignment(Alignment::End),
                        ])
                        ->schema([
                            Hidden::make('accounts_payable_id'),
                            TextInput::make('supplier_invoice_line')
                                ->hiddenLabel()
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('purchase_number')
                                ->hiddenLabel()
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('due_at_label')
                                ->hiddenLabel()
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('amount_usd_label')
                                ->hiddenLabel()
                                ->disabled()
                                ->dehydrated(false)
                                ->extraInputAttributes(['class' => 'text-end font-medium tabular-nums']),
                            TextInput::make('amount_ves_label')
                                ->hiddenLabel()
                                ->disabled()
                                ->dehydrated(false)
                                ->extraInputAttributes(['class' => 'text-end font-medium tabular-nums']),
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
                            .' <span class="font-normal text-gray-500 dark:text-gray-400">(BCV '
                            .e(self::formatBcvRateLabel((float) ($get('_bcv_rate') ?? 0)))
                            .')</span></p>'
                        ))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
            Section::make('Datos del pago')
                ->description('Se aplican a todas las cuentas seleccionadas.')
                ->icon(Heroicon::Banknotes)
                ->compact()
                ->visible(fn (Get $get): bool => blank($get('_bulk_error')))
                ->schema(AccountsPayablePaymentFormSchema::paymentFields(false))
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
        ];
    }

    private static function formatUsd(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' USD';
    }

    private static function formatBs(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }

    private static function formatBcvRateLabel(float $rate): string
    {
        return $rate > 0
            ? number_format($rate, 2, ',', '.').' Bs/USD'
            : '—';
    }
}
