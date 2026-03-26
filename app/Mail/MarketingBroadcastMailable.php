<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\MarketingBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MarketingBroadcastMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public MarketingBroadcast $broadcast,
        public string $htmlBody,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->broadcast->subject ?? 'Mensaje de Farmadoc';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return (new Content)->htmlString($this->renderHtml());
    }

    protected function renderHtml(): string
    {
        $html = $this->htmlBody;
        $replacements = [
            '{{nombre}}' => e($this->client->name),
            '{{email}}' => e((string) $this->client->email),
        ];
        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        if (! Str::contains($html, '<html', ignoreCase: true)) {
            $html = '<!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;line-height:1.5;">'.$html.'</body></html>';
        }

        return $html;
    }
}
