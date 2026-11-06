<?php

namespace App\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve el contrato firmado (privado) de un convenio de compañía aliada.
 */
final class PartnerAgreementDocumentController extends Controller
{
    public function __invoke(Request $request, PartnerCompanyAgreement $agreement): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessFarmaadminMenuKey('partner_agreements'), 403);

        $isVisible = BranchAuthScope::applyToPartnerCompaniesQuery(PartnerCompany::query())
            ->whereKey($agreement->partner_company_id)
            ->exists();
        abort_unless($isVisible, 403);

        $path = (string) $agreement->signed_document_path;
        $disk = Storage::disk(PartnerCompanyAgreement::documentDisk());
        abort_unless($path !== '' && ! str_contains($path, '..') && $disk->exists($path), 404);

        return $disk->response($path, $agreement->signed_document_name ?: basename($path));
    }
}
