<?php

namespace App\Filament\Resources\Suppliers\Tables;

use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
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

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->withCount(['products', 'purchases']),
            )
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->weight('medium')
                    ->placeholder('—')
                    ->icon(Heroicon::Hashtag)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('legal_name')
                    ->label('Razón social')
                    ->description(fn (Supplier $record): ?string => self::formatLegalNameDescription($record))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Supplier $record): string => $record->legal_name)
                    ->icon(Heroicon::BuildingOffice2)
                    ->iconColor('gray'),
                TextColumn::make('products_count')
                    ->label('Productos')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Supplier $record): string => ((int) $record->products_count) > 0 ? 'success' : 'gray')
                    ->tooltip(fn (Supplier $record): string => ((int) $record->products_count) === 1
                        ? '1 producto enlazado en catálogo'
                        : (string) (int) $record->products_count.' productos enlazados en catálogo'),
                TextColumn::make('purchases_count')
                    ->label('Compras')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn (Supplier $record): string => ((int) $record->purchases_count) > 0 ? 'info' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter()
                    ->sortable()
                    ->tooltip(fn (Supplier $record): string => $record->is_active
                        ? 'Activo: disponible para nuevos vínculos y compras'
                        : 'Inactivo: revisar antes de asignar a productos'),
                TextColumn::make('tax_id')
                    ->label('NIT / ID fiscal')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Identificación copiada')
                    ->placeholder('—')
                    ->icon(Heroicon::Identification)
                    ->iconColor('gray')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->placeholder('—')
                    ->icon(Heroicon::Envelope)
                    ->iconColor('gray')
                    ->limit(28)
                    ->tooltip(fn (Supplier $record): ?string => filled($record->email) ? $record->email : null),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->placeholder('—')
                    ->icon(Heroicon::Phone)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('trade_name')
                    ->label('Nombre comercial')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->limit(32)
                    ->tooltip(fn (Supplier $record): ?string => filled($record->trade_name) ? $record->trade_name : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::MapPin)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contact_name')
                    ->label('Contacto')
                    ->searchable()
                    ->placeholder('—')
                    ->limit(28)
                    ->icon(Heroicon::User)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('website')
                    ->label('Web')
                    ->url(fn (Supplier $record): ?string => filled($record->website) ? $record->website : null)
                    ->openUrlInNewTab()
                    ->limit(24)
                    ->placeholder('—')
                    ->icon(Heroicon::GlobeAlt)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable()
                    ->limit(36)
                    ->tooltip(fn (Supplier $record): ?string => filled($record->address) ? $record->address : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state')
                    ->label('Departamento')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country')
                    ->label('País')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mobile_phone')
                    ->label('Celular')
                    ->copyable()
                    ->copyMessage('Celular copiado')
                    ->placeholder('—')
                    ->icon(Heroicon::DevicePhoneMobile)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contact_email')
                    ->label('Email contacto')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contact_phone')
                    ->label('Tel. contacto')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_terms')
                    ->label('Condiciones de pago')
                    ->limit(40)
                    ->tooltip(fn (Supplier $record): ?string => filled($record->payment_terms) ? $record->payment_terms : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Modificado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Última edición')
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
            ->emptyStateHeading('Sin proveedores registrados')
            ->emptyStateDescription('Registra laboratorios, distribuidores y otros proveedores para vincularlos a productos y compras. Usa «Crear proveedor» en el encabezado.')
            ->emptyStateIcon(Heroicon::BuildingOffice2)
            ->recordUrl(fn (Supplier $record): string => SupplierResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado del proveedor')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
                TernaryFilter::make('has_linked_products')
                    ->label('Vínculo con catálogo')
                    ->placeholder('Todos')
                    ->trueLabel('Con productos enlazados')
                    ->falseLabel('Sin productos enlazados')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->has('products'),
                        false: fn (Builder $query): Builder => $query->doesntHave('products'),
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
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }

    private static function formatLegalNameDescription(Supplier $record): ?string
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
