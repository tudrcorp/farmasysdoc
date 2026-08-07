<?php

namespace App\Filament\Resources\ClientDiscounts\Individual\Tables;

use App\Filament\Resources\ClientDiscounts\Individual\IndividualClientDiscountResource;
use App\Models\Client;
use App\Services\Sales\ClientCommercialDiscountAssigner;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class IndividualClientDiscountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Cliente')
                    ->description(fn (Client $record): ?string => self::contactSubtitle($record))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Client $record): string => $record->name)
                    ->icon(Heroicon::User)
                    ->iconColor('primary')
                    ->placeholder('—'),
                TextColumn::make('document_number')
                    ->label('Documento')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Documento copiado')
                    ->description(fn (Client $record): ?string => filled($record->document_type)
                        ? (string) $record->document_type
                        : null)
                    ->icon(Heroicon::Identification)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('customer_discount')
                    ->label('Descuento')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->alignCenter()
                    ->icon(Heroicon::ReceiptPercent)
                    ->formatStateUsing(fn ($state): string => self::formatPercent($state).'%')
                    ->tooltip('Porcentaje aplicado sobre el subtotal de toda la venta en caja'),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->icon(Heroicon::Phone)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->limit(28)
                    ->icon(Heroicon::Envelope)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                        'blocked' => 'Bloqueado',
                        default => filled($state) ? (string) $state : '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'blocked' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable()
                    ->description(fn (Client $record): string => $record->updated_at?->format('d/m/Y H:i') ?? '—')
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado del cliente')
                    ->options([
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                        'blocked' => 'Bloqueado',
                    ])
                    ->native(false),
                SelectFilter::make('discount_band')
                    ->label('Rango de descuento')
                    ->options([
                        'low' => 'Hasta 5%',
                        'mid' => '5% – 15%',
                        'high' => 'Más de 15%',
                    ])
                    ->native(false)
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'low' => $query->where('customer_discount', '<=', 5),
                            'mid' => $query->whereBetween('customer_discount', [5.01, 15]),
                            'high' => $query->where('customer_discount', '>', 15),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon(Heroicon::Eye)
                    ->color('gray'),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare)
                    ->color('primary'),
                Action::make('clearDiscount')
                    ->label('Quitar')
                    ->icon(Heroicon::Trash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar descuento individual')
                    ->modalDescription('El cliente dejará de tener descuento en caja. No se elimina el cliente.')
                    ->modalSubmitActionLabel('Sí, quitar descuento')
                    ->action(function (Client $record): void {
                        app(ClientCommercialDiscountAssigner::class)->clearIndividual($record);

                        Notification::make()
                            ->title('Descuento individual eliminado')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->recordUrl(fn (Client $record): string => IndividualClientDiscountResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('clearDiscounts')
                        ->label('Quitar descuentos')
                        ->icon(Heroicon::Trash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Quitar descuentos individuales')
                        ->modalDescription('Los clientes seleccionados dejarán de tener descuento. No se eliminan los clientes.')
                        ->modalSubmitActionLabel('Sí, quitar')
                        ->action(function (Collection $records): void {
                            $assigner = app(ClientCommercialDiscountAssigner::class);
                            foreach ($records as $record) {
                                if ($record instanceof Client) {
                                    $assigner->clearIndividual($record);
                                }
                            }

                            Notification::make()
                                ->title('Descuentos individuales eliminados')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->emptyStateHeading('Sin descuentos individuales')
            ->emptyStateDescription('Asocia un cliente y define el porcentaje. En caja se aplicará sobre toda la venta.')
            ->emptyStateIcon(Heroicon::ReceiptPercent);
    }

    private static function contactSubtitle(Client $client): ?string
    {
        $phone = trim((string) ($client->phone ?? ''));
        if ($phone !== '') {
            return $phone;
        }

        $email = trim((string) ($client->email ?? ''));

        return $email !== '' ? $email : null;
    }

    private static function formatPercent(mixed $state): string
    {
        if ($state === null || $state === '' || ! is_numeric($state)) {
            return '0';
        }

        return rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',');
    }
}
