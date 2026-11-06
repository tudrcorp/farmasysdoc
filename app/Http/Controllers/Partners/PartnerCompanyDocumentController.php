<?php

namespace App\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyDocument;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve los adjuntos privados del expediente de una compañía aliada.
 */
final class PartnerCompanyDocumentController extends Controller
{
    public function __invoke(Request $request, PartnerCompanyDocument $document): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessFarmaadminMenuKey('partner_companies'), 403);

        $isVisible = BranchAuthScope::applyToPartnerCompaniesQuery(PartnerCompany::query())
            ->whereKey($document->partner_company_id)
            ->exists();
        abort_unless($isVisible, 403);

        $path = (string) $document->file_path;
        $disk = Storage::disk(PartnerCompanyDocument::DISK);
        abort_unless($path !== '' && ! str_contains($path, '..') && $disk->exists($path), 404);

        return $disk->response($path, $document->displayName());
    }
}
