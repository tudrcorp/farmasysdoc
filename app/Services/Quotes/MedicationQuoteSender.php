<?php

namespace App\Services\Quotes;

use App\Mail\MedicationQuoteMail;
use App\Models\MedicationQuote;
use App\Support\Notifications\UltramsgWhatsAppClient;
use App\Support\Notifications\WhatsAppLink;
use App\Support\Quotes\MedicationQuotePdfFactory;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class MedicationQuoteSender
{
    public function __construct(
        private readonly MedicationQuotePdfFactory $pdfFactory,
        private readonly UltramsgWhatsAppClient $whatsApp,
    ) {}

    /**
     * @return list<string>
     */
    public function send(MedicationQuote $quote): array
    {
        $notes = [];
        $pdf = $this->pdfFactory->output($quote);

        $email = trim((string) $quote->requester_email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($email)->send(new MedicationQuoteMail($quote, $pdf));
                $quote->forceFill(['emailed_at' => now()])->save();
                $notes[] = 'Correo enviado a '.$email.'.';
            } catch (Throwable $exception) {
                $notes[] = 'No se pudo enviar el correo: '.$exception->getMessage();
            }
        } else {
            $notes[] = 'No hay un correo válido para enviar la cotización.';
        }

        $phone = WhatsAppLink::normalizePhoneDigits($quote->requester_phone);
        if ($phone === null) {
            $notes[] = 'No hay un teléfono válido para WhatsApp.';

            return $notes;
        }

        if (! $this->whatsApp->isEnabled()) {
            $notes[] = 'WhatsApp no está configurado. La cotización quedó guardada.';

            return $notes;
        }

        $sent = $this->whatsApp->sendDocumentMessage(
            $phone,
            base64_encode($pdf),
            $quote->fileName(),
            'Cotización '.$quote->number.' · '.$quote->requester_name,
        );

        if ($sent) {
            $quote->forceFill(['whatsapped_at' => now()])->save();
            $notes[] = 'WhatsApp enviado.';
        } else {
            $notes[] = 'No se pudo enviar el PDF por WhatsApp.';
        }

        return $notes;
    }
}
