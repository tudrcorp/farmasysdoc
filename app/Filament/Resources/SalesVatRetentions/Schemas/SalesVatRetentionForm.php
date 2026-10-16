<?php

namespace App\Filament\Resources\SalesVatRetentions\Schemas;

use App\Models\Branch;
use App\Models\FiscalPrinter;
use App\Support\Filament\BranchAuthScope;
use App\Support\Fiscal\VenezuelanRifFormatter;
use App\Support\Fiscal\VenezuelanTaxpayerClassifier;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Unique;

class SalesVatRetentionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Comprobante de retención')
                    ->description('Datos del comprobante que entregó el cliente (agente de retención). Se declara en la quincena de la fecha de recepción.')
                    ->icon(Heroicon::ReceiptRefund)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 3])->schema([
                            Select::make('branch_id')
                                ->label('Sucursal')
                                ->options(fn (): array => Branch::query()
                                    ->where('is_active', true)
                                    ->tap(fn ($query) => BranchAuthScope::applyToBranchFormSelect($query))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->default(fn (): ?int => BranchAuthScope::suggestedBranchIdForOperationalForm())
                                ->required()
                                ->live()
                                ->native(false),
                            TextInput::make('customer_rif')
                                ->label('RIF del cliente')
                                ->required()
                                ->maxLength(20)
                                ->placeholder('J-12345678-9')
                                ->dehydrateStateUsing(fn (?string $state): string => VenezuelanRifFormatter::format($state))
                                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! VenezuelanTaxpayerClassifier::isTaxpayer((string) $value)) {
                                        $fail('Indique un RIF válido de contribuyente (J, G, C, o V/E con 9 dígitos).');
                                    }
                                }),
                            TextInput::make('customer_name')
                                ->label('Razón social del cliente')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('voucher_number')
                                ->label('Nº de comprobante')
                                ->helperText('14 dígitos: AAAAMM + secuencia de 8 dígitos.')
                                ->required()
                                ->length(14)
                                ->regex('/^\d{14}$/')
                                ->unique(
                                    table: 'sales_vat_retentions',
                                    column: 'voucher_number',
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('customer_rif', VenezuelanRifFormatter::format((string) $get('customer_rif'))),
                                )
                                ->validationMessages([
                                    'regex' => 'El comprobante debe tener 14 dígitos numéricos.',
                                    'unique' => 'Este comprobante ya está registrado para este cliente.',
                                ]),
                            DatePicker::make('voucher_issued_on')
                                ->label('Fecha de emisión del comprobante')
                                ->required()
                                ->native(false)
                                ->maxDate(now()),
                            DatePicker::make('received_on')
                                ->label('Fecha de recepción')
                                ->helperText('Define la quincena del Libro de Ventas.')
                                ->required()
                                ->native(false)
                                ->default(now())
                                ->maxDate(now())
                                ->afterOrEqual('voucher_issued_on'),
                        ]),
                    ]),
                Section::make('Factura retenida')
                    ->icon(Heroicon::DocumentText)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 3])->schema([
                            Select::make('fiscal_printer_id')
                                ->label('Máquina fiscal que emitió la factura')
                                ->options(fn (Get $get): array => FiscalPrinter::query()
                                    ->where('branch_id', (int) $get('branch_id'))
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (FiscalPrinter $printer): array => [$printer->id => $printer->name.' · '.$printer->fiscal_registry])
                                    ->all())
                                ->native(false),
                            TextInput::make('invoice_number')
                                ->label('Nº de factura')
                                ->required()
                                ->maxLength(20)
                                ->regex('/^\d+$/')
                                ->validationMessages(['regex' => 'Use solo los dígitos del número de factura.']),
                            DatePicker::make('invoice_date')
                                ->label('Fecha de la factura')
                                ->required()
                                ->native(false)
                                ->beforeOrEqual('voucher_issued_on'),
                            TextInput::make('invoice_total_ves')
                                ->label('Total de la factura (Bs)')
                                ->numeric()
                                ->minValue(0),
                            TextInput::make('invoice_tax_ves')
                                ->label('IVA de la factura (Bs)')
                                ->numeric()
                                ->minValue(0.01)
                                ->required()
                                ->live(onBlur: true),
                            Select::make('retention_percent')
                                ->label('Porcentaje retenido')
                                ->options(['75' => '75%', '100' => '100%'])
                                ->required()
                                ->live()
                                ->native(false),
                            TextInput::make('retained_amount_ves')
                                ->label('Monto retenido (Bs)')
                                ->helperText(fn (Get $get): string => is_numeric($get('invoice_tax_ves')) && is_numeric($get('retention_percent'))
                                    ? 'Según IVA × porcentaje: Bs '.number_format(round((float) $get('invoice_tax_ves') * (float) $get('retention_percent') / 100, 2), 2, ',', '.')
                                    : 'Debe coincidir con IVA × porcentaje.')
                                ->numeric()
                                ->minValue(0.01)
                                ->required()
                                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $expected = round((float) $get('invoice_tax_ves') * (float) $get('retention_percent') / 100, 2);

                                    if (abs((float) $value - $expected) > 0.01) {
                                        $fail('El monto retenido debe ser el IVA × porcentaje (Bs '.number_format($expected, 2, ',', '.').'). Verifique el comprobante.');
                                    }
                                }),
                            Textarea::make('notes')
                                ->label('Observaciones')
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
