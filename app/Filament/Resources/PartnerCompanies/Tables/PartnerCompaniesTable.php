<?php

namespace App\Filament\Resources\PartnerCompanies\Tables;

use App\Enums\OrderStatus;
use App\Enums\PartnerCompanyClientType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyContact;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
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
                    ->with(['branches:id,name', 'contacts'])
                    ->withCount('documents')
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
                    ->label('RIF')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Identificación copiada')
                    ->placeholder('—')
                    ->icon(Heroicon::Identification)
                    ->iconColor('gray'),
                TextColumn::make('client_type')
                    ->label('Tipo de cliente')
                    ->formatStateUsing(fn (mixed $state): string => PartnerCompanyClientType::tryLabel($state))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('branches.name')
                    ->label('Sucursales')
                    ->badge()
                    ->color('primary')
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->placeholder('Sin sucursal')
                    ->toggleable(),
                TextColumn::make('payment_modality')
                    ->label('Modalidad de pago')
                    ->formatStateUsing(fn (mixed $state): string => PartnerCompanyPaymentModality::tryLabel($state))
                    ->badge()
                    ->color(fn (mixed $state): string => $state instanceof PartnerCompanyPaymentModality ? $state->color() : 'gray')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('discount_percentage')
                    ->label('Descuento')
                    ->formatStateUsing(fn (mixed $state): string => filled($state) && (float) $state > 0
                        ? rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',').'%'
                        : '—')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('credit_limit_usd')
                    ->label('Límite crédito')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('No aplica')
                    ->tooltip('Límite de crédito aprobado en el convenio'),
                TextColumn::make('assigned_credit_limit')
                    ->label('Saldo disponible')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->color(fn (PartnerCompany $record): ?string => $record->credit_limit_usd !== null
                        && (float) $record->assigned_credit_limit <= 0 ? 'danger' : null)
                    ->tooltip('Saldo disponible en USD (se reduce con consumos registrados en histórico)')
                    ->toggleable(),
                TextColumn::make('documents_count')
                    ->label('Documentos')
                    ->numeric()
                    ->alignCenter()
                    ->badge()
                    ->icon(Heroicon::PaperClip)
                    ->color(fn (PartnerCompany $record): string => ((int) $record->documents_count) > 0 ? 'info' : 'gray')
                    ->sortable(),
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
                    ->label('Estado')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country')
                    ->label('País')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('responsible_contact')
                    ->label('Responsable')
                    ->state(fn (PartnerCompany $record): ?string => self::responsibleContact($record)?->name)
                    ->description(fn (PartnerCompany $record): ?string => self::responsibleContact($record)?->phone)
                    ->placeholder('—')
                    ->icon(Heroicon::UserCircle)
                    ->iconColor('gray')
                    ->toggleable(),
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
            ->filtersFormColumns(3)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Sin compañías aliadas')
            ->emptyStateDescription('Registra empresas aliadas para centralizar convenios, referencias y datos de contacto operativos.')
            ->emptyStateIcon(Heroicon::BuildingOffice2)
            ->recordUrl(fn (PartnerCompany $record): string => PartnerCompanyResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                SelectFilter::make('branches')
                    ->label('Sucursal')
                    ->relationship('branches', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                SelectFilter::make('client_type')
                    ->label('Tipo de cliente')
                    ->options(PartnerCompanyClientType::options())
                    ->multiple(),
                SelectFilter::make('payment_modality')
                    ->label('Acuerdo de negociación')
                    ->options(PartnerCompanyPaymentModality::options())
                    ->multiple(),
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
                Action::make('documents')
                    ->label('Documentos')
                    ->icon(Heroicon::PaperClip)
                    ->color('gray')
                    ->modalHeading(fn (PartnerCompany $record): string => 'Documentos de '.$record->legal_name)
                    ->modalDescription('Carta de convenio y documentación legal del aliado.')
                    ->modalIcon(Heroicon::PaperClip)
                    ->modalContent(fn (PartnerCompany $record) => view('filament.partner-companies.documents-modal', [
                        'documents' => $record->documents()->get(),
                        'editUrl' => PartnerCompanyResource::getUrl('edit', ['record' => $record]),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->slideOver(),
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

    private static function responsibleContact(PartnerCompany $record): ?PartnerCompanyContact
    {
        $contacts = $record->relationLoaded('contacts') ? $record->contacts : $record->contacts()->get();

        return $contacts->firstWhere('is_responsible', true) ?? $contacts->first();
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
