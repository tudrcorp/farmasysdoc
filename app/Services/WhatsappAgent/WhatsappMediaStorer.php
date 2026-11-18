<?php

namespace App\Services\WhatsappAgent;

use App\Models\WhatsappMessage;
use App\Support\WhatsappAgent\WhatsappGateway;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copia en disco privado la foto o nota de voz del cliente (las URLs del proveedor caducan).
 * Son datos de salud: nunca se guardan en el disco público.
 */
final class WhatsappMediaStorer
{
    private const DIRECTORY = 'whatsapp-agent/media';

    /**
     * @var array<string, string>
     */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'application/pdf' => 'pdf',
    ];

    public function __construct(private readonly WhatsappGateway $gateway) {}

    public function store(WhatsappMessage $message): bool
    {
        if ($message->hasStoredMedia()) {
            return true;
        }

        if (blank($message->media_url)) {
            return false;
        }

        $download = $this->gateway->downloadMedia((string) $message->media_url);

        if ($download === null) {
            return false;
        }

        $mime = $download['mime'] ?? null;
        $extension = self::EXTENSIONS[(string) $mime] ?? (pathinfo((string) parse_url((string) $message->media_url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin');
        $path = self::DIRECTORY.'/'.now()->format('Y/m').'/'.$message->getKey().'-'.Str::random(12).'.'.Str::lower($extension);

        Storage::disk(WhatsappMessage::MEDIA_DISK)->put($path, $download['contents']);

        $message->forceFill([
            'media_path' => $path,
            'media_mime' => $mime,
        ])->save();

        return true;
    }
}
