<?php

namespace App\Filament\Resources\FiscalDocuments\Tables;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Filament\Resources\FiscalDocuments\Actions\FiscalDocumentActions;
use App\Filament\Resources\FiscalDocuments\FiscalDocumentResource;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FiscalDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (mixed $state, FiscalDocument $record): string => FiscalDocumentType::tryLabel($state)
                        .($record->is_test ? ' · prueba' : '')
                        .($record->simulation ? ' · simulación' : ''))
                    ->badge()
                    ->color(fn (FiscalDocument $record): string => match (true) {
                        $record->is_test => 'warning',
                        $record->simulation => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (mixed $state): string => FiscalDocumentStatus::tryLabel($state))
                    ->badge()
                    ->color(fn (FiscalDocument $record): string => $record->status->color()),
                TextColumn::make('sale.sale_number')
                    ->label('Venta')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('fiscal_number')
                    ->label('Nº fiscal')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('fiscalPrinter.name')
                    ->label('Máquina')
                    ->description(fn (FiscalDocument $record): ?string => $record->fiscalPrinter?->branch?->name),
                TextColumn::make('expected_total_ves')
                    ->label('Total esperado (Bs)')
                    ->numeric(2, ',', '.')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('printer_total_ves')
                    ->label('Total máquina (Bs)')
                    ->numeric(2, ',', '.')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('attempts')
                    ->label('Intentos')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(60)
                    ->tooltip(fn (FiscalDocument $record): ?string => $record->error_message)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->poll('15s')
            ->filters([
                SelectFilter::make('origin')
                    ->label('Origen')
                    ->options([
                        'real' => 'Documentos reales',
                        'simulation' => 'Simulaciones de ventas',
                        'test' => 'Laboratorio fiscal (pruebas)',
                    ])
                    ->default('real')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'real' => $query->where('is_test', false)->where('simulation', false),
                        'simulation' => $query->where('is_test', false)->where('simulation', true),
                        'test' => $query->where('is_test', true),
                        default => $query,
                    }),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(FiscalDocumentStatus::options())
                    ->multiple(),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(FiscalDocumentType::options()),
                SelectFilter::make('fiscal_printer_id')
                    ->label('Máquina fiscal')
                    ->options(fn (): array => BranchAuthScope::apply(FiscalPrinter::query())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
            ])
            ->emptyStateHeading('No hay documentos fiscales')
            ->emptyStateDescription('Aquí aparecen las facturas, notas de crédito y reportes enviados a las máquinas fiscales.')
            ->emptyStateIcon(Heroicon::ReceiptPercent)
            ->recordUrl(fn (FiscalDocument $record): string => FiscalDocumentResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver')
                        ->icon(Heroicon::Eye),
                    ...FiscalDocumentActions::all(),
                ]),
            ])
            ->recordActionsColumnLabel('Acciones');
    }
}
