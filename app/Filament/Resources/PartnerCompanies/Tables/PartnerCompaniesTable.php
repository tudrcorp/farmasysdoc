<?php

namespace App\Filament\Resources\PartnerCompanies\Tables;

use App\Enums\OrderStatus;
use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\PartnerCompany;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartnerCompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->withCount('orderServices')
                    ->withCount([
                        'orders as finalized_orders_count' => function (Builder $q): void {
                            $q->where('status', OrderStatus::Completed->value);
                        },
                    ]);
            })
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->weight('medium'),
                TextColumn::make('legal_name')
                    ->label('Razón social')
                    ->description(fn (PartnerCompany $record): ?string => self::formatDescription($record))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (PartnerCompany $record): string => $record->legal_name)
                    ->icon(Heroicon::BuildingOffice2)
                    ->iconColor('gray'),
                TextColumn::make('trade_name')
                    ->label('Nombre comercial')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->limit(32)
                    ->tooltip(fn (PartnerCompany $record): ?string => $record->trade_name)
                    ->toggleable(),
                TextColumn::make('tax_id')
                    ->label('NIT / ID fiscal')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Identificación copiada')
                    ->placeholder('—')
                    ->icon(Heroicon::Identification)
                    ->iconColor('gray'),
                TextColumn::make('assigned_credit_limit')
                    ->label('Saldo crédito')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->icon(Heroicon::Banknotes)
                    ->iconColor('gray')
                    ->tooltip('Saldo disponible en USD (se reduce con consumos registrados en histórico)')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->placeholder('—')
                    ->icon(Heroicon::Envelope)
                    ->iconColor('gray')
                    ->limit(30),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->placeholder('—')
                    ->icon(Heroicon::Phone)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mobile_phone')
                    ->label('Celular')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Celular copiado')
                    ->placeholder('—')
                    ->icon(Heroicon::DevicePhoneMobile)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('website')
                    ->label('Web')
                    ->url(fn (PartnerCompany $record): ?string => filled($record->website) ? $record->website : null)
                    ->openUrlInNewTab()
                    ->searchable()
                    ->placeholder('—')
                    ->icon(Heroicon::GlobeAlt)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable()
                    ->placeholder('—')
                    ->limit(36)
                    ->tooltip(fn (PartnerCompany $record): ?string => $record->address)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')
                    ->label('Ciudad')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state')
                    ->label('Departamento')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country')
                    ->label('País')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contact_name')
                    ->label('Contacto')
                    ->searchable()
                    ->placeholder('—')
                    ->icon(Heroicon::UserCircle)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('contact_email')
                    ->label('Correo contacto')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contact_phone')
                    ->label('Tel. contacto')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agreement_reference')
                    ->label('Ref. convenio')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Referencia copiada')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('date_created')
                    ->label('Creación convenio')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray')
                    ->tooltip('Fecha de creación del convenio'),
                TextColumn::make('date_updated')
                    ->label('Actualiz. convenio')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::ArrowPath)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip('Fecha de actualización del convenio'),
                TextColumn::make('order_services_count')
                    ->label('Órdenes de servicio')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn (PartnerCompany $record): string => ((int) $record->order_services_count) > 0 ? 'info' : 'gray'),
                TextColumn::make('finalized_orders_count')
                    ->label('Pedidos finalizados')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn (PartnerCompany $record): string => ((int) ($record->finalized_orders_count ?? 0)) > 0 ? 'success' : 'gray')
                    ->tooltip('Cantidad de pedidos del aliado en estado Finalizado')
                    ->icon(Heroicon::CheckBadge)
                    ->iconColor('gray'),
                IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('legal_name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Sin compañías aliadas')
            ->emptyStateDescription('Registra empresas aliadas para centralizar convenios, referencias y datos de contacto operativos.')
            ->emptyStateIcon(Heroicon::BuildingOffice2)
            ->recordUrl(fn (PartnerCompany $record): string => PartnerCompanyResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),
                TernaryFilter::make('has_orders')
                    ->label('Órdenes de servicio')
                    ->placeholder('Todas')
                    ->trueLabel('Con órdenes registradas')
                    ->falseLabel('Sin órdenes registradas')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->has('orderServices'),
                        false: fn (Builder $query): Builder => $query->doesntHave('orderServices'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver ficha')
                    ->icon(Heroicon::Eye),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ]);
    }

    private static function formatDescription(PartnerCompany $record): ?string
    {
        $parts = [];

        if (filled($record->trade_name)) {
            $parts[] = $record->trade_name;
        }

        $geo = array_filter([$record->city, $record->state, $record->country]);

        if ($geo !== []) {
            $parts[] = implode(' · ', $geo);
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }
}
