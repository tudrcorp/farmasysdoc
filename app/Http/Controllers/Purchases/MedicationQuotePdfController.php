<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\MedicationQuote;
use App\Models\User;
use App\Support\Quotes\MedicationQuotePdfFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MedicationQuotePdfController extends Controller
{
    public function __invoke(
        Request $request,
        MedicationQuote $medicationQuote,
        MedicationQuotePdfFactory $pdfFactory,
    ): Response {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->isAdministrator() && ! $user->canAccessFarmaadminMenuKey('medication_quotes')) {
            abort(403, 'No tienes permiso para el cotizador.');
        }

        return $pdfFactory->make($medicationQuote)->download($medicationQuote->fileName());
    }
}
