<?php

namespace App\Http\Controllers\Purchases;

use App\Enums\PurchaseStatus;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Purchases\PurchaseAnnulmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseAnnulmentApprovalController extends Controller
{
    public function show(Request $request, Purchase $purchase, PurchaseAnnulmentService $annulmentService): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdministrator(), 403);
        abort_unless($annulmentService->mayOpenApprovalPage($purchase), 404);

        $purchase->loadMissing([
            'supplier',
            'branch',
            'items' => fn ($q) => $q->orderBy('line_number')->orderBy('id'),
        ]);

        $confirmUrl = URL::temporarySignedRoute(
            'purchases.annulment.confirm',
            now()->addMinutes(90),
            ['purchase' => $purchase->getKey()],
            absolute: true,
        );

        return view('purchases.annulment-approve', [
            'purchase' => $purchase,
            'confirmUrl' => $confirmUrl,
            'annulledStatusValue' => PurchaseStatus::Annulled->value,
            'annulledStatusLabel' => PurchaseStatus::Annulled->label(),
        ]);
    }

    public function confirm(Request $request, Purchase $purchase, PurchaseAnnulmentService $annulmentService): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdministrator(), 403);

        $request->validate([
            'purchase_status' => ['required', Rule::in([PurchaseStatus::Annulled->value])],
        ], [
            'purchase_status.required' => 'Seleccione el estado «Anulada» para confirmar.',
            'purchase_status.in' => 'Solo puede confirmar con el estado «Anulada».',
        ]);

        $annulmentService->executeAnnulment($purchase, $user);

        return redirect()
            ->route('purchases.annulment.complete', ['purchase' => $purchase->getKey()])
            ->with('purchase_annulled', true);
    }

    public function complete(Request $request, Purchase $purchase): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdministrator(), 403);
        abort_unless($purchase->status === PurchaseStatus::Annulled, 404);
        abort_unless(session()->pull('purchase_annulled') === true, 403);

        $purchase->loadMissing(['supplier', 'branch']);

        $waText = rawurlencode(
            'Compra '.$purchase->purchase_number.' anulada en Farmadoc el '.now()->format('d/m/Y H:i').'.',
        );
        $whatsappReturnUrl = 'https://wa.me/?text='.$waText;

        return view('purchases.annulment-complete', [
            'purchase' => $purchase,
            'whatsappReturnUrl' => $whatsappReturnUrl,
            'purchasesPanelUrl' => PurchaseResource::getUrl(panel: 'farmaadmin'),
        ]);
    }
}
