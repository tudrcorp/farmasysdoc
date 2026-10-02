<?php

namespace App\Filament\Resources\FiscalPrinters\Tables;

use App\Enums\FiscalDocumentType;
use App\Enums\FiscalPrinterMode;
use App\Enums\FiscalPrinterModel;
use App\Filament\Resources\FiscalPrinters\Actions\ChangeFiscalPrinterModeAction;
use App\Filament\Resources\FiscalPrinters\Actions\ReassignPendingDocumentsAction;
use App\Filament\Resources\FiscalPrinters\Actions\RequestFiscalReportAction;
use App\Filament\Resources\FiscalPrinters\FiscalPrinterResource;
use App\Models\Branch;
use App\Models\FiscalPrinter;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FiscalPrintersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon(Heroicon::Printer)
                    ->iconColor('gray'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('physicalCashBox.user.name')
                    ->label('Caja (cajero)')
                    ->placeholder('Sin caja'),
                TextColumn::make('model')
                    ->label('Modelo')
                    ->formatStateUsing(fn (mixed $state): string => FiscalPrinterModel::tryLabel($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fiscal_registry')
                    ->label('Registro SENIAT')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('mode')
                    ->label('Modo')
                    ->formatStateUsing(fn (mixed $state): string => FiscalPrinterMode::tryLabel($state))
                    ->badge()
                    ->color(fn (FiscalPrinter $record): string => $record->currentMode()->color()),
                TextColumn::make('connection_state')
                    ->label('Agente')
                    ->state(fn (FiscalPrinter $record): string => $record->isOnline() ? 'En línea' : 'Sin conexión')
                    ->badge()
                    ->color(fn (FiscalPrinter $record): string => $record->isOnline() ? 'success' : 'danger')
                    ->description(fn (FiscalPrinter $record): ?string => $record->last_heartbeat_at?->diffForHumans()),
                TextColumn::make('last_fiscal_number')
                    ->label('Última factura')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('name')
            ->striped()
            ->poll('30s')
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(fn (): array => Branch::query()
                        ->where('is_active', true)
                        ->tap(fn ($query) => BranchAuthScope::applyToBranchFormSelect($query))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('mode')
                    ->label('Modo')
                    ->options(FiscalPrinterMode::options()),
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),
            ])
            ->emptyStateHeading('No hay máquinas fiscales')
            ->emptyStateDescription('Registre la máquina fiscal de cada caja con su serial y registro SENIAT.')
            ->emptyStateIcon(Heroicon::Printer)
            ->recordUrl(fn (FiscalPrinter $record): string => FiscalPrinterResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver')
                        ->icon(Heroicon::Eye),
                    EditAction::make()
                        ->label('Editar')
                        ->icon(Heroicon::PencilSquare),
                    ChangeFiscalPrinterModeAction::make(),
                    ReassignPendingDocumentsAction::make(),
                    RequestFiscalReportAction::make(FiscalDocumentType::XReport),
                    RequestFiscalReportAction::make(FiscalDocumentType::ZReport),
                ]),
            ])
            ->recordActionsColumnLabel('Acciones');
    }
}
