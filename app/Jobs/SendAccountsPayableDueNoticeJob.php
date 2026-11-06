<?php

namespace App\Jobs;

use App\Mail\AccountsPayableDueNoticeMail;
use App\Models\User;
use App\Services\Finance\AccountsPayableDueNoticeService;
use App\Support\Notifications\UltramsgWhatsAppClient;
use App\Support\Notifications\WhatsAppLink;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Envía el aviso de facturas por vencer (con el PDF) a un administrador por un canal.
 * Un job por canal para que el reintento de uno no repita el otro.
 */
class SendAccountsPayableDueNoticeJob implements ShouldQueue
{
    use Queueable;

    public const string CHANNEL_MAIL = 'mail';

    public const string CHANNEL_WHATSAPP = 'whatsapp';

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public int $userId,
        public string $channel,
        public string $pdfPath,
        public string $headline,
        public array $lines,
    ) {
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function handle(AccountsPayableDueNoticeService $service, UltramsgWhatsAppClient $whatsApp): void
    {
        $user = User::query()->find($this->userId);
        $disk = Storage::disk($service->disk());

        if (! $user instanceof User || ! $disk->exists($this->pdfPath)) {
            return;
        }

        $filename = basename($this->pdfPath);

        if ($this->channel === self::CHANNEL_MAIL) {
            if (filled($user->email)) {
                Mail::to((string) $user->email)->send(new AccountsPayableDueNoticeMail(
                    recipientName: (string) $user->name,
                    headline: $this->headline,
                    lines: $this->lines,
                    pdfPath: $this->pdfPath,
                    pdfDisk: $service->disk(),
                    actionUrl: route('filament.farmaadmin.resources.accounts-payables.index'),
                ));
            }

            return;
        }

        $digits = WhatsAppLink::normalizePhoneDigits($user->whatsapp_phone);

        if ($digits === null || ! $whatsApp->isEnabled()) {
            return;
        }

        $caption = implode("\n", [
            '*Farmadoc · Cuentas por pagar*',
            '*'.$this->headline.'*',
            ...array_map(fn (string $line): string => '• '.$line, $this->lines),
        ]);

        if (! $whatsApp->sendDocumentMessage($digits, base64_encode((string) $disk->get($this->pdfPath)), $filename, $caption)) {
            throw new RuntimeException('No se pudo enviar por WhatsApp el aviso de facturas por vencer al usuario #'.$user->getKey().'.');
        }
    }
}
