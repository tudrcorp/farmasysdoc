<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\PhysicalCashBox;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PhysicalCashBoxClosePhotoController extends Controller
{
    public function __invoke(Request $request, PhysicalCashBox $box, string $kind): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdministrator(), 403);
        abort_unless(in_array($kind, ['usd', 'pos'], true), 404);

        $path = $kind === 'usd'
            ? (string) ($box->close_usd_cash_photo_path ?? '')
            : (string) ($box->close_pos_receipt_photo_path ?? '');

        abort_unless($path !== '' && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
