<?php

namespace App\Filament\Resources\Inventories\Actions;

use App\Models\Inventory;
use App\Models\User;
use App\Services\Pricing\InventoryBranchSpecialPriceService;
use App\Support\Finance\DefaultVatRate;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Asignar / quitar el precio especial de un producto en la sucursal de la fila de inventario.
 */
final class BranchSpecialPriceActions
{
    public static function assign(): Action
    {
        return Action::make('assignBranchSpecialPrice')
            ->label(fn (Inventory $record): string => $record->branchSpecialPriceAmount() !== null
                ? 'Cambiar precio especial'
                : 'Precio especial')
            ->icon(Heroicon::Tag)
            ->color('warning')
            ->visible(fn (): bool => self::currentUser()?->canEditBranchSpecialPrice() ?? false)
            ->modalHeading(fn (Inventory $record): string => 'Precio especial · '.($record->product?->name ?? 'Producto'))
            ->modalDescription(fn (Inventory $record): string => 'Solo aplica en la sucursal '.($record->branch?->name ?? '—')
                .'. Manda sobre el precio calculado y el precio directo del producto; las demás sucursales no cambian.')
            ->fillForm(fn (Inventory $record): array => [
                'branch_special_price' => $record->branchSpecialPriceAmount(),
            ])
            ->schema(fn (Inventory $record): array => [
                TextInput::make('branch_special_price')
                    ->label('Precio especial sin IVA (USD)')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->prefix('$')
                    ->required()
                    ->live(debounce: 400)
                    ->helperText(fn (Get $get): string => self::helperText($record, $get('branch_special_price'))),
            ])
            ->action(function (Inventory $record, array $data): void {
                $actor = self::currentUser();

                if (! $actor instanceof User) {
                    return;
                }

                app(InventoryBranchSpecialPriceService::class)->assign($record, (float) $data['branch_special_price'], $actor);

                Notification::make()
                    ->title('Precio especial asignado')
                    ->body(($record->product?->name ?? 'Producto').' · '.($record->branch?->name ?? 'Sucursal')
                        .': $ '.number_format((float) $data['branch_special_price'], 2, ',', '.').' sin IVA.')
                    ->success()
                    ->send();
            });
    }

    public static function clear(): Action
    {
        return Action::make('clearBranchSpecialPrice')
            ->label('Quitar precio especial')
            ->icon(Heroicon::XMark)
            ->color('gray')
            ->visible(fn (Inventory $record): bool => $record->branchSpecialPriceAmount() !== null
                && (self::currentUser()?->canEditBranchSpecialPrice() ?? false))
            ->requiresConfirmation()
            ->modalHeading('Quitar precio especial')
            ->modalDescription(fn (Inventory $record): string => 'La sucursal '.($record->branch?->name ?? '—')
                .' volverá a vender '.($record->product?->name ?? 'el producto')
                .' al precio calculado ($ '.number_format((float) ($record->final_price_without_vat ?? 0), 2, ',', '.').' sin IVA) o al precio directo si el producto lo tiene.')
            ->action(function (Inventory $record): void {
                $actor = self::currentUser();

                if (! $actor instanceof User) {
                    return;
                }

                app(InventoryBranchSpecialPriceService::class)->clear($record, $actor);

                Notification::make()
                    ->title('Precio especial quitado')
                    ->success()
                    ->send();
            });
    }

    private static function helperText(Inventory $record, mixed $state): string
    {
        $calculated = 'Precio calculado actual: $ '.number_format((float) ($record->final_price_without_vat ?? 0), 2, ',', '.').' sin IVA.';

        if (! is_numeric($state) || (float) $state <= 0) {
            return $calculated;
        }

        $net = round((float) $state, 2);
        $appliesVat = (bool) ($record->product?->applies_vat ?? false);
        $vatRate = max(0.0, DefaultVatRate::percent());

        if (! $appliesVat || $vatRate <= 0) {
            return $calculated.' Producto exento: el cliente paga $ '.number_format($net, 2, ',', '.').'.';
        }

        $final = round($net + round($net * $vatRate / 100, 2), 2);

        return $calculated.' Con IVA ('.number_format($vatRate, 2, ',', '.').' %) el cliente paga $ '.number_format($final, 2, ',', '.').'.';
    }

    private static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
