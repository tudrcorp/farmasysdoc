<?php

namespace App\Filament\Resources\Suppliers\Actions;

use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;

final class DownloadSupplierDossierPdfAction
{
    public const NAME = 'downloadSupplierDossierPdf';

    public static function make(?Supplier $supplier = null): Action
    {
        return Action::make(self::NAME)
            ->label('Descargar PDF')
            ->icon(Heroicon::DocumentArrowDown)
            ->color('gray')
            ->url(function (Action $action) use ($supplier): ?string {
                $record = $supplier ?? $action->getRecord();

                if (! $record instanceof Supplier) {
                    return null;
                }

                return URL::temporarySignedRoute(
                    'suppliers.dossier-pdf',
                    now()->addMinutes(30),
                    ['supplier' => $record->getKey()],
                );
            })
            ->openUrlInNewTab();
    }
}
