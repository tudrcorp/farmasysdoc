<?php

namespace App\Filament\Concerns;

use App\Enums\VenezuelanPagoMovilBank;
use App\Http\Requests\BdvConciliation\GetMovementRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\BdvConciliation\BdvPagomovilConciliationService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

trait InteractsWithBdvPagomovilConciliationForm
{
    public ?int $branchId = null;

    public string $cedulaPagador = '';

    public string $telefonoPagador = '';

    public string $telefonoDestino = '';

    public string $referencia = '';

    public string $fechaPago = '';

    public string $importe = '';

    public string $bancoOrigen = VenezuelanPagoMovilBank::BancoDeVenezuela->value;

    public bool $reqCed = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastResult = null;

    public bool $lastSuccess = false;

    public ?string $lastUserMessage = null;

    /**
     * @var list<array{id: int, name: string}>
     */
    public array $branchOptions = [];

    public bool $showBranchSelect = false;

    public string $environmentLabel = '';

    protected function bootBdvPagomovilConciliationForm(BdvPagomovilConciliationService $service): void
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        $this->branchOptions = $service->branchOptionsForUser($user);
        $this->showBranchSelect = count($this->branchOptions) > 1;
        $this->branchId = $service->defaultBranchIdForUser($user);
        $this->fechaPago = now()->toDateString();
        $this->telefonoDestino = $service->resolveCommercePhone($this->branchId);
        $this->environmentLabel = $service->resolveEnvironment() === 'production' ? 'Producción' : 'Calidad (QA)';
    }

    public function updatedBranchId(BdvPagomovilConciliationService $service): void
    {
        $this->telefonoDestino = $service->resolveCommercePhone($this->branchId);
    }

    public function submitConciliation(BdvPagomovilConciliationService $service): void
    {
        $this->lastResult = null;
        $this->lastSuccess = false;
        $this->lastUserMessage = null;

        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        $allowedBranchIds = array_column($this->branchOptions, 'id');

        $branchIdForPhone = $this->branchId ?? $service->defaultBranchIdForUser($user);
        $this->telefonoDestino = $service->resolveCommercePhone(
            $branchIdForPhone !== null ? (int) $branchIdForPhone : null,
        );

        $rules = array_merge(
            (new GetMovementRequest)->rules(),
            [
                'branchId' => [
                    Rule::requiredIf($this->showBranchSelect || $this->branchId === null),
                    'nullable',
                    'integer',
                    Rule::in($allowedBranchIds),
                ],
            ],
        );

        $validated = $this->validate($rules, (new GetMovementRequest)->messages());

        $branchId = $this->showBranchSelect
            ? (int) ($validated['branchId'] ?? 0)
            : (int) ($this->branchId ?? $service->defaultBranchIdForUser($user) ?? 0);

        if ($branchId <= 0) {
            Notification::make()
                ->title('Sucursal requerida')
                ->body('No se pudo determinar la sucursal para registrar la conciliación. Verifique su usuario o seleccione una sucursal.')
                ->danger()
                ->send();

            return;
        }

        $this->telefonoDestino = $service->resolveCommercePhone($branchId);
        $validated['telefonoDestino'] = $this->telefonoDestino;

        unset($validated['branchId']);
        $validated['branch_id'] = $branchId;

        $outcome = $service->conciliate($validated, $user);

        $this->lastResult = $outcome['panel'];
        $this->lastSuccess = $outcome['success'];
        $this->lastUserMessage = $outcome['user_message'];

        AuditLogger::record(
            event: $outcome['success'] ? 'bdv_pagomovil_conciliation_ok' : 'bdv_pagomovil_conciliation_failed',
            description: $outcome['success']
                ? 'Conciliación PM BDV exitosa.'
                : 'Conciliación PM BDV rechazada o con error.',
            properties: [
                'branch_id' => $branchId,
                'reference' => $validated['referencia'] ?? null,
                'amount' => $validated['importe'] ?? null,
                'bdv_code' => collect($outcome['panel']['highlight_codes'] ?? [])
                    ->firstWhere('key', 'code')['value'] ?? null,
                'record_id' => $outcome['record']?->id,
            ],
        );

        $notification = Notification::make()
            ->title($outcome['success'] ? 'Pago conciliado' : 'No se concilió el pago')
            ->body($outcome['user_message']);

        if ($outcome['success']) {
            $notification->success();
            $this->dispatch('bdv-pm-conciliation-success');
        } else {
            $notification->danger()->persistent();
        }

        $notification->send();
    }

    public function resetBdvPagomovilConciliationForm(BdvPagomovilConciliationService $service): void
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        $this->reset([
            'cedulaPagador',
            'telefonoPagador',
            'referencia',
            'importe',
            'reqCed',
            'lastResult',
            'lastSuccess',
            'lastUserMessage',
        ]);

        $this->fechaPago = now()->toDateString();
        $this->bancoOrigen = VenezuelanPagoMovilBank::BancoDeVenezuela->value;
        $this->branchId = $service->defaultBranchIdForUser($user);
        $this->telefonoDestino = $service->resolveCommercePhone($this->branchId);
    }
}
