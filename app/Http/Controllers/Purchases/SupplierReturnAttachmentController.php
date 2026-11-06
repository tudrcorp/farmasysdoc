<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnAttachment;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve fotos y soportes privados de una devolución a proveedor.
 */
final class SupplierReturnAttachmentController extends Controller
{
    public function __invoke(Request $request, SupplierReturnAttachment $attachment): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessFarmaadminMenuKey('supplier_returns'), 403);

        $isVisible = BranchAuthScope::apply(SupplierReturn::query())->whereKey($attachment->supplier_return_id)->exists();
        abort_unless($isVisible, 403);

        $path = (string) $attachment->file_path;
        $disk = Storage::disk(SupplierReturnAttachment::disk());
        abort_unless($path !== '' && ! str_contains($path, '..') && $disk->exists($path), 404);

        return $disk->response($path, $attachment->displayName());
    }
}
