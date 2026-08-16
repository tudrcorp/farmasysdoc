<?php

namespace App\Services\Hr;

use App\Mail\PayrollReceiptMail;
use App\Models\HrPayrollReceipt;
use App\Support\Notifications\UltramsgWhatsAppClient;
use App\Support\Notifications\WhatsAppLink;
use Illuminate\Support\Facades\Mail;

final class PayrollReceiptSender
{
    public function __construct(
        private PayrollReceiptPdfFactory $pdfFactory,
        private UltramsgWhatsAppClient $whatsApp,
    ) {}

    /**
     * @return array{email: bool, whatsapp: bool, email_error: ?string, whatsapp_error: ?string}
     */
    public function send(HrPayrollReceipt $receipt): array
    {
        $email = $this->sendEmail($receipt);
        $whatsapp = $this->sendWhatsApp($receipt);

        return [
            'email' => $email['ok'],
            'whatsapp' => $whatsapp['ok'],
            'email_error' => $email['error'],
            'whatsapp_error' => $whatsapp['error'],
        ];
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    public function sendEmail(HrPayrollReceipt $receipt): array
    {
        $receipt->loadMissing('employee');
        $email = $receipt->employee?->email;

        if (! filled($email)) {
            return ['ok' => false, 'error' => 'El empleado no tiene correo.'];
        }

        try {
            Mail::to($email)->send(new PayrollReceiptMail(
                $receipt,
                $this->pdfFactory->output($receipt),
            ));
            $receipt->forceFill(['emailed_at' => now()])->save();

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $exception) {
            report($exception);

            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    public function sendWhatsApp(HrPayrollReceipt $receipt): array
    {
        $receipt->loadMissing('employee');
        $digits = WhatsAppLink::normalizePhoneDigits($receipt->employee?->phone);

        if ($digits === null) {
            return ['ok' => false, 'error' => 'El empleado no tiene un teléfono válido.'];
        }

        if (! $this->whatsApp->isEnabled()) {
            return ['ok' => false, 'error' => 'WhatsApp no está configurado.'];
        }

        $caption = sprintf(
            'Recibo de nómina · %s · %s',
            $receipt->worker_name,
            $receipt->periodLabel(),
        );

        $sent = $this->whatsApp->sendDocumentMessage(
            $digits,
            base64_encode($this->pdfFactory->output($receipt)),
            $receipt->fileName(),
            $caption,
        );

        if (! $sent) {
            return ['ok' => false, 'error' => 'No se pudo enviar el PDF por WhatsApp.'];
        }

        $receipt->forceFill(['whatsapped_at' => now()])->save();

        return ['ok' => true, 'error' => null];
    }
}
