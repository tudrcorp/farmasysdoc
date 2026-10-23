<?php

namespace App\Http\Controllers\PettyCash;

use App\Enums\PettyCashFundStatus;
use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\PettyCash\ApprovePettyCashFundRequest;
use App\Http\Requests\PettyCash\RejectPettyCashFundRequest;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Services\Finance\PettyCashFundApprover;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class PettyCashApprovalController extends Controller
{
    public function show(Request $request, PettyCashFund $fund): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdministrator(), 403);

        $fund->loadMissing(['branch', 'requestedBy']);

        $approveUrl = URL::temporarySignedRoute(
            'petty-cash.approval.approve',
            now()->addHours(12),
            ['fund' => $fund->getKey()],
            absolute: true,
        );

        $rejectUrl = URL::temporarySignedRoute(
            'petty-cash.approval.reject',
            now()->addHours(12),
            ['fund' => $fund->getKey()],
            absolute: true,
        );

        return view('petty-cash.approval', [
            'fund' => $fund,
            'approveUrl' => $approveUrl,
            'rejectUrl' => $rejectUrl,
            'isPending' => $fund->status === PettyCashFundStatus::PendingApproval,
            'panelUrl' => PettyCashFundResource::getUrl('view', ['record' => $fund], panel: 'farmaadmin'),
        ]);
    }

    public function approve(ApprovePettyCashFundRequest $request, PettyCashFund $fund, PettyCashFundApprover $approver): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdministrator(), 403);

        $file = $request->file('payment_proof');
        if ($file === null) {
            return back()->withErrors(['payment_proof' => 'Adjunte la captura del pago.'])->withInput();
        }

        $path = $file->store('petty-cash/payment-proofs', 'public');
        if (! is_string($path) || $path === '') {
            return back()->withErrors(['payment_proof' => 'No se pudo guardar el comprobante.'])->withInput();
        }

        try {
            $approver->approve(
                $fund,
                $user,
                $path,
                (string) $request->validated('payment_reference'),
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['payment_proof' => $e->getMessage()])->withInput();
        }

        return redirect()->route('petty-cash.approval.complete', [
            'fund' => $fund->getKey(),
            'outcome' => 'approved',
        ]);
    }

    public function reject(RejectPettyCashFundRequest $request, PettyCashFund $fund, PettyCashFundApprover $approver): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdministrator(), 403);

        try {
            $approver->reject(
                $fund,
                $user,
                $request->validated('rejection_reason') !== null
                    ? (string) $request->validated('rejection_reason')
                    : null,
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['rejection_reason' => $e->getMessage()])->withInput();
        }

        return redirect()->route('petty-cash.approval.complete', [
            'fund' => $fund->getKey(),
            'outcome' => 'rejected',
        ]);
    }

    public function complete(Request $request, PettyCashFund $fund): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdministrator(), 403);

        $fund->loadMissing(['branch', 'requestedBy']);
        $fund->refresh();

        $approved = $this->resolveApprovalCompleteOutcome($request, $fund);

        abort_unless($approved !== null, 403);

        $waText = rawurlencode(
            $approved
                ? 'Caja chica #'.$fund->getKey().' aprobada en Farmadoc el '.now()->format('d/m/Y H:i').'.'
                : 'Caja chica #'.$fund->getKey().' rechazada en Farmadoc el '.now()->format('d/m/Y H:i').'.',
        );

        return view('petty-cash.approval-complete', [
            'fund' => $fund,
            'approved' => $approved,
            'whatsappReturnUrl' => 'https://wa.me/?text='.$waText,
            'panelUrl' => PettyCashFundResource::getUrl('view', ['record' => $fund], panel: 'farmaadmin'),
        ]);
    }

    private function resolveApprovalCompleteOutcome(Request $request, PettyCashFund $fund): ?bool
    {
        $fromStatus = match ($fund->status) {
            PettyCashFundStatus::Approved => true,
            PettyCashFundStatus::Rejected => false,
            default => null,
        };

        if ($fromStatus !== null) {
            return $fromStatus;
        }

        $outcome = $request->query('outcome');
        if (! is_string($outcome) || $outcome === '') {
            $outcome = session()->pull('petty_cash_approval_outcome');
        }

        return match ($outcome) {
            'approved' => true,
            'rejected' => false,
            default => null,
        };
    }
}
