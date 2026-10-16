<?php

namespace App\Filament\Resources\FiscalPrinters\RelationManagers;

use App\Enums\FiscalZReportSource;
use App\Filament\Resources\FiscalPrinters\Pages\ViewFiscalPrinter;
use App\Models\FiscalPrinter;
use App\Models\FiscalZReport;
use App\Models\User;
use App\Services\Fiscal\FiscalDocumentRegistrar;
use App\Services\Fiscal\FiscalZReportRecorder;
use App\Support\Fiscal\SalesBookAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Reportes Z leídos de la memoria fiscal de esta máquina (solo lectura): base del Libro de Ventas.
 */
class ZReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'zReports';

    protected static ?string $title = 'Reportes Z';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass === ViewFiscalPrinter::class;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('z_number', 'desc')
            ->columns([
                TextColumn::make('z_number')->label('Z Nº')->formatStateUsing(fn (int $state): string => str_pad((string) $state, 4, '0', STR_PAD_LEFT))->sortable(),
                TextColumn::make('report_datetime')->label('Fecha')->dateTime('d/m/Y h:i A')->sortable(),
                TextColumn::make('last_invoice_number')->label('Última factura')->formatStateUsing(fn (int $state): string => str_pad((string) $state, 8, '0', STR_PAD_LEFT)),
                TextColumn::make('exempt_sales_ves')->label('Exento')->numeric(2),
                TextColumn::make('general_base_sales_ves')->label('Base 16%')->numeric(2),
                TextColumn::make('general_tax_sales_ves')->label('IVA 16%')->numeric(2),
                TextColumn::make('total')->label('Total ventas')->state(fn (FiscalZReport $record): float => $record->salesTotalVes())->numeric(2),
                TextColumn::make('last_credit_note_number')->label('Última NC')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')->label('Origen')->badge()->formatStateUsing(fn (FiscalZReportSource $state): string => $state->label()),
                IconColumn::make('integrity')->label('Íntegro')->state(fn (FiscalZReport $record): bool => $record->hasValidContentHash())->boolean(),
                TextColumn::make('captured_at')->label('Capturado')->dateTime('d/m/Y h:i A')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('readZReports')
                    ->label('Leer reportes Z')
                    ->icon(Heroicon::ArrowPath)
                    ->visible(fn (): bool => SalesBookAccess::canClose(Auth::user() instanceof User ? Auth::user() : null))
                    ->modalDescription('Lee los Z de la memoria fiscal sin imprimir. Si la máquina no está en modo «Activa», hágalo cuando la caja no esté facturando.')
                    ->schema([
                        TextInput::make('from_z')
                            ->label('Desde el Z')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default(function (): ?int {
                                /** @var FiscalPrinter $printer */
                                $printer = $this->getOwnerRecord();
                                $stored = FiscalZReport::query()
                                    ->where('printer_serial', FiscalZReportRecorder::normalizeSerial($printer->fiscal_registry))
                                    ->max('z_number');

                                if ($stored !== null) {
                                    return (int) $stored + 1;
                                }

                                $last = filter_var($printer->last_z_number, FILTER_VALIDATE_INT);

                                return $last !== false ? $last : null;
                            }),
                        TextInput::make('to_z')
                            ->label('Hasta el Z (vacío = hasta el último)')
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->action(function (array $data): void {
                        /** @var FiscalPrinter $printer */
                        $printer = $this->getOwnerRecord();
                        $user = Auth::user();

                        try {
                            app(FiscalDocumentRegistrar::class)->requestZReading(
                                $printer,
                                (int) $data['from_z'],
                                filled($data['to_z'] ?? null) ? (int) $data['to_z'] : null,
                                SalesBookAccess::actorLabel($user instanceof User ? $user : null),
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()->title('No se pudo pedir la lectura')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();

                            return;
                        }

                        Notification::make()->title('Lectura de reportes Z en cola')->success()->send();
                    }),
            ]);
    }
}
