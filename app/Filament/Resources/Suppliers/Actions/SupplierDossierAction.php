<?php

namespace App\Filament\Resources\Suppliers\Actions;

use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchases\SupplierDossierBuilder;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Slide-over "Ficha del proveedor": resumen + compras, pagos, comprobantes y retenciones (alcance por sucursal).
 */
final class SupplierDossierAction
{
    public const NAME = 'supplierDossier';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Ficha del proveedor')
            ->icon(Heroicon::Identification)
            ->color('primary')
            ->slideOver()
            ->modalWidth(Width::SevenExtraLarge)
            ->modalHeading(fn (Supplier $record): string => 'Ficha · '.$record->displayName())
            ->modalDescription('Todo el historial del proveedor en las sucursales a las que tienes acceso.')
            ->modalIcon(Heroicon::Identification)
            ->modalContent(function (Supplier $record): View {
                $viewer = Auth::user();
                $viewer = $viewer instanceof User ? $viewer : null;

                return view('filament.suppliers.supplier-dossier', [
                    ...app(SupplierDossierBuilder::class)->build($record, $viewer),
                    'canOpenAccountsPayables' => self::canOpenMenu($viewer, 'accounts_payable'),
                    'canOpenPurchaseBooks' => self::canOpenMenu($viewer, 'purchase_books'),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->extraModalFooterActions(fn (Supplier $record): array => [
                DownloadSupplierDossierPdfAction::make($record),
            ]);
    }

    private static function canOpenMenu(?User $viewer, string $menuKey): bool
    {
        return $viewer instanceof User
            && ($viewer->isAdministrator() || $viewer->canAccessFarmaadminMenuKey($menuKey));
    }
}
