<?php

namespace App\Services\Partners;

use App\Models\PartnerAgreementSale;
use App\Support\Fiscal\CompanyFiscalAddress;
use App\Support\Fiscal\VenezuelanRifFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF de la nota de entrega de una venta por convenio (a nombre del afiliado, forma de pago
 * «Por convenio»).
 */
final class PartnerDeliveryNotePdfFactory
{
    /**
     * @return array<string, mixed>
     */
    public function viewData(PartnerAgreementSale $sale): array
    {
        $sale->loadMissing(['items', 'branch', 'partnerCompany', 'plan', 'seller', 'canceller']);

        return [
            'sale' => $sale,
            'company_name' => (string) config('fiscal.retention_agent.name'),
            'company_rif' => VenezuelanRifFormatter::format((string) config('fiscal.retention_agent.rif')),
            'company_address' => CompanyFiscalAddress::line(),
            'payment_label' => PartnerAgreementSale::PAYMENT_LABEL,
        ];
    }

    public function output(PartnerAgreementSale $sale): string
    {
        return Pdf::loadView('pdf.partner-delivery-note', $this->viewData($sale))
            ->setPaper('letter', 'portrait')
            ->output();
    }

    public function download(PartnerAgreementSale $sale): Response
    {
        return response($this->output($sale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$sale->fileName().'"',
        ]);
    }
}
