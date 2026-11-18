<?php

namespace App\Http\Controllers\WhatsappAgent;

use App\Filament\Resources\WhatsappConversations\WhatsappConversationResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Muestra en el panel la foto del récipe o la nota de voz del cliente (disco privado).
 */
class WhatsappMessageMediaController extends Controller
{
    public function __invoke(Request $request, WhatsappMessage $message): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->canAccessFarmaadminMenuKey(WhatsappConversationResource::MENU_KEY), 403);

        $disk = Storage::disk(WhatsappMessage::MEDIA_DISK);
        $path = (string) $message->media_path;

        abort_if($path === '' || ! $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => $message->media_mime ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
