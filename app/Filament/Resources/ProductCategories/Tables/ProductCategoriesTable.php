<?php

namespace App\Filament\Resources\ProductCategories\Tables;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'creator',
                'updater',
            ])->withCount('products'))
            ->columns([
                ImageColumn::make('image')
                    ->label('Imagen')
                    ->disk('public')
                    ->visibility('public')
                    ->height(44)
                    ->width(44)
                    ->square()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn (ProductCategory $record): ?string => filled($record->slug)
                        ? 'Slug: '.$record->slug
                        : null)
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->lineClamp(2)
                    ->wrap()
                    ->tooltip(fn (ProductCategory $record): string => (string) $record->name)
                    ->icon(Heroicon::Tag)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->limit(48)
                    ->tooltip(fn (ProductCategory $record): ?string => $record->description)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon(Heroicon::DocumentText)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Slug copiado')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon(Heroicon::Link)
                    ->iconColor('gray')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->action(
                        Action::make('activateCategoryStatus')
                            ->modalHeading('Activar categoría')
                            ->modalDescription('¿Deseas activar esta categoría para que pueda usarse en el sistema y en cálculos de precio?')
                            ->modalSubmitActionLabel('Sí, activar categoría')
                            ->requiresConfirmation()
                            ->visible(function (ProductCategory $record): bool {
                                $authUser = request()->user();

                                return $authUser instanceof User
                                    && $authUser->isAdministrator()
                                    && ! $record->is_active;
                            })
                            ->action(function (ProductCategory $record): void {
                                $record->update([
                                    'is_active' => true,
                                ]);

                                Notification::make()
                                    ->title('Categoría activada correctamente.')
                                    ->success()
                                    ->send();
                            })
                    )
                    ->alignCenter()
                    ->tooltip(fn (ProductCategory $record): string => $record->is_active
                        ? 'Categoría activa en catálogo'
                        : (
                            request()->user() instanceof User
                            && request()->user()->isAdministrator()
                            ? 'Haz clic para activar (requiere confirmación)'
                            : 'Categoría pendiente de aprobación administrativa'
                        )),
                TextColumn::make('profit_percentage')
                    ->label('Margen %')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable()
                    ->alignEnd()
                    ->icon(Heroicon::Calculator)
                    ->iconColor('gray'),
                TextColumn::make('products_count')
                    ->label('Productos')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->icon(Heroicon::Cube)
                    ->iconColor('gray'),
                TextColumn::make('creator.name')
                    ->label('Creado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—')
                    ->icon(Heroicon::UserCircle)
                    ->iconColor('gray'),
                TextColumn::make('updater.name')
                    ->label('Actualizado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—')
                    ->icon(Heroicon::ArrowPath)
                    ->iconColor('gray'),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray'),
                TextColumn::make('updated_at')
                    ->label('Última edición')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray'),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),
            ])
            ->emptyStateHeading('No hay categorías')
            ->emptyStateDescription('Cree una categoría para agrupar productos en catálogo y reportes.')
            ->emptyStateIcon(Heroicon::Swatch)
            ->recordUrl(fn (ProductCategory $record): string => ProductCategoryResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
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
}
