<?php

namespace App\Filament\Resources\PhysicalCashBoxCloseReports\Tables;

use App\Filament\Resources\PhysicalCashBoxCloseReports\PhysicalCashBoxCloseReportResource;
use App\Models\PhysicalCashBoxCloseReport;
use App\Support\Cash\PhysicalCashBoxCloseVariance;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PhysicalCashBoxCloseReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('closed_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->placeholder('Sin sucursal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Cajero')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('has_mismatch')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => (bool) $state ? 'Descuadre' : 'Cuadrado')
                    ->color(fn (mixed $state): string => (bool) $state ? 'danger' : 'success'),
                TextColumn::make('difference_usd')
                    ->label('Dif. USD')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->color(fn (mixed $state): string => PhysicalCashBoxCloseVariance::isMismatch((float) $state) ? 'danger' : 'success'),
                TextColumn::make('difference_ves')
                    ->label('Dif. VES')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('Bs. ')
                    ->color(fn (mixed $state): string => PhysicalCashBoxCloseVariance::isMismatch((float) $state) ? 'danger' : 'success'),
                TextColumn::make('pos_difference_ves')
                    ->label('Dif. POS')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('Bs. ')
                    ->color(fn (mixed $state): string => PhysicalCashBoxCloseVariance::isMismatch((float) $state) ? 'danger' : 'success'),
                TextColumn::make('whatsapp_status')
                    ->label('WhatsApp')
                    ->badge()
                    ->state(fn (PhysicalCashBoxCloseReport $record): string => $record->whatsapp_sent_at !== null
                        ? 'Enviado'
                        : (filled($record->whatsapp_error) ? 'Falló' : 'Pendiente'))
                    ->color(fn (PhysicalCashBoxCloseReport $record): string => $record->whatsapp_sent_at !== null
                        ? 'success'
                        : (filled($record->whatsapp_error) ? 'danger' : 'gray')),
                TextColumn::make('email_status')
                    ->label('Correo')
                    ->badge()
                    ->state(fn (PhysicalCashBoxCloseReport $record): string => $record->email_sent_at !== null
                        ? 'Enviado'
                        : (filled($record->email_error) ? 'Falló' : 'Pendiente'))
                    ->color(fn (PhysicalCashBoxCloseReport $record): string => $record->email_sent_at !== null
                        ? 'success'
                        : (filled($record->email_error) ? 'danger' : 'gray')),
            ])
            ->defaultSort('closed_at', 'desc')
            ->filters([
                TernaryFilter::make('has_mismatch')
                    ->label('Descuadre')
                    ->trueLabel('Con faltante o sobrante')
                    ->falseLabel('Cuadrados'),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('Sin reportes de cierre')
            ->emptyStateDescription('Cuando un cajero cierre su caja física, el reporte comparativo quedará aquí aunque falle el envío.')
            ->recordUrl(fn (PhysicalCashBoxCloseReport $record): string => PhysicalCashBoxCloseReportResource::getUrl('view', ['record' => $record]));
    }
}
