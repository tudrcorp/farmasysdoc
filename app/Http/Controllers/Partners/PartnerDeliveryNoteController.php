<?php

namespace App\Http\Controllers\Partners;

use App\Filament\Resources\PartnerAgreementSales\PartnerAgreementSaleResource;
use App\Http\Controllers\Controller;
use App\Models\PartnerAgreementSale;
use App\Services\Partners\PartnerDeliveryNotePdfFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF de la nota de entrega de una venta por convenio para el personal de Farmadoc. Respeta el
 * mismo alcance que el recurso (sucursales del usuario; el cajero solo las suyas).
 */
final class PartnerDeliveryNoteController extends Controller
{
    public function __invoke(PartnerAgreementSale $partnerAgreementSale, PartnerDeliveryNotePdfFactory $pdf): Response
    {
        abort_unless(PartnerAgreementSaleResource::canViewAny(), 403);
        abort_unless(PartnerAgreementSaleResource::getEloquentQuery()->whereKey($partnerAgreementSale->getKey())->exists(), 404);

        return $pdf->download($partnerAgreementSale);
    }
}
