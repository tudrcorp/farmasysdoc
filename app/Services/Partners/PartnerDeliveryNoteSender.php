<?php

namespace App\Services\Partners;

use App\Mail\PartnerDeliveryNoteMail;
use App\Models\PartnerAgreementSale;
use App\Support\Notifications\UltramsgWhatsAppClient;
use App\Support\Notifications\WhatsAppLink;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía la nota de entrega (PDF) al afiliado por correo o WhatsApp, a pedido del cajero.
 * Se puede indicar otro destino (p. ej. un correo o teléfono que el afiliado dicta en caja).
 */
final class PartnerDeliveryNoteSender
{
    public function __construct(
        private PartnerDeliveryNotePdfFactory $pdf,
        private UltramsgWhatsAppClient $whatsApp,
    ) {}

    /**
     * @return array{ok: bool, error: ?string, to: ?string}
     */
    public function sendEmail(PartnerAgreementSale $sale, ?string $email = null): array
    {
        $to = PartnerAgreementSaleService::deliverableEmail($email ?: $sale->client_email);

        if ($to === null) {
            return ['ok' => false, 'error' => 'El afiliado no tiene un correo válido.', 'to' => null];
        }

        try {
            Mail::to($to)->send(new PartnerDeliveryNoteMail($sale, $this->pdf->output($sale)));
            $sale->forceFill(['emailed_at' => now(), 'client_email' => $sale->client_email ?: $to])->save();

            return ['ok' => true, 'error' => null, 'to' => $to];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'error' => 'No se pudo enviar el correo.', 'to' => $to];
        }
    }

    /**
     * @return array{ok: bool, error: ?string, to: ?string}
     */
    public function sendWhatsApp(PartnerAgreementSale $sale, ?string $phone = null): array
    {
        $digits = WhatsAppLink::normalizePhoneDigits($phone ?: $sale->client_phone);

        if ($digits === null) {
            return ['ok' => false, 'error' => 'El afiliado no tiene un teléfono válido.', 'to' => null];
        }

        if (! $this->whatsApp->isEnabled()) {
            return ['ok' => false, 'error' => 'WhatsApp no está configurado.', 'to' => $digits];
        }

        $caption = sprintf('Nota de entrega %s · USD %s · Convenio %s', $sale->number, number_format((float) $sale->total, 2, ',', '.'), $sale->partnerCompany?->trade_name ?: $sale->partnerCompany?->legal_name);

        $sent = $this->whatsApp->sendDocumentMessage($digits, base64_encode($this->pdf->output($sale)), $sale->fileName(), $caption);

        if (! $sent) {
            return ['ok' => false, 'error' => 'No se pudo enviar el PDF por WhatsApp.', 'to' => $digits];
        }

        $sale->forceFill(['whatsapped_at' => now(), 'client_phone' => $sale->client_phone ?: $phone])->save();

        return ['ok' => true, 'error' => null, 'to' => $digits];
    }
}
