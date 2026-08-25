<?php

namespace App\Services\Branches;

use App\Models\Branch;
use App\Models\BranchDailyOperation;
use App\Models\PhysicalCashBox;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Branches\BranchDailyOperationException;
use App\Support\Branches\NotifyOnBranchDailyClose;
use App\Support\Branches\NotifyOnBranchDailyOpen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BranchDailyOperationService
{
    public function __construct(
        private readonly NotifyOnBranchDailyOpen $notifyOnOpen,
        private readonly NotifyOnBranchDailyClose $notifyOnClose,
        private readonly BranchDailyOperationReconciliationBuilder $reconciliationBuilder,
    ) {}

    public function isOpen(Branch $branch): bool
    {
        if ($branch->relationLoaded('currentOpenDailyOperation')) {
            return $branch->currentOpenDailyOperation !== null;
        }

        return BranchDailyOperation::query()
            ->where('branch_id', $branch->getKey())
            ->whereNull('closed_at')
            ->exists();
    }

    public function isOpenForUser(?User $user): bool
    {
        if (! $user instanceof User || blank($user->branch_id)) {
            return false;
        }

        return BranchDailyOperation::query()
            ->where('branch_id', (int) $user->branch_id)
            ->whereNull('closed_at')
            ->exists();
    }

    public function currentOpen(Branch $branch): ?BranchDailyOperation
    {
        if ($branch->relationLoaded('currentOpenDailyOperation')) {
            return $branch->currentOpenDailyOperation;
        }

        return BranchDailyOperation::query()
            ->where('branch_id', $branch->getKey())
            ->whereNull('closed_at')
            ->latest('id')
            ->first();
    }

    public function actorCanManage(User $user, Branch $branch): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        if (! $user->isManager()) {
            return false;
        }

        return in_array((int) $branch->getKey(), $user->restrictedBranchIdsForQueries(), true);
    }

    public function canOpen(User $user, Branch $branch): bool
    {
        if (! $this->actorCanManage($user, $branch)) {
            return false;
        }

        if ($this->isOpen($branch)) {
            return false;
        }

        return $user->isAdministrator() || ! $this->wasClosedToday($branch);
    }

    public function canClose(User $user, Branch $branch): bool
    {
        return $this->actorCanManage($user, $branch) && $this->isOpen($branch);
    }

    public function open(User $actor, Branch $branch): BranchDailyOperation
    {
        $this->assertActorCanManage($actor, $branch);

        $operation = DB::transaction(function () use ($actor, $branch): BranchDailyOperation {
            $existing = BranchDailyOperation::query()
                ->where('branch_id', $branch->getKey())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if ($existing instanceof BranchDailyOperation) {
                throw new BranchDailyOperationException('La sucursal ya está aperturada.');
            }

            if (! $actor->isAdministrator() && $this->wasClosedToday($branch)) {
                throw new BranchDailyOperationException(
                    'Solo un administrador puede reaperturar la sucursal el mismo día.'
                );
            }

            return BranchDailyOperation::query()->create([
                'branch_id' => $branch->getKey(),
                'opened_by_user_id' => $actor->getKey(),
                'opened_at' => now(),
            ]);
        });

        AuditLogger::record(
            'branch_daily_operation_opened',
            'Sucursal · Apertura de gestión del día · '.$branch->name,
            Branch::class,
            $branch->getKey(),
            $branch->name,
            [
                'module' => 'branch_daily_operation',
                'branch_id' => $branch->getKey(),
                'branch_code' => $branch->code,
                'operation_id' => $operation->getKey(),
                'opened_by_user_id' => $actor->getKey(),
                'opened_by_email' => $actor->email,
                'opened_at' => $operation->opened_at?->toIso8601String(),
            ],
        );

        try {
            $this->notifyOnOpen->notify($actor, $branch, $operation);
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar WhatsApp de apertura de sucursal', [
                'branch_id' => $branch->getKey(),
                'operation_id' => $operation->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        return $operation;
    }

    public function close(User $actor, Branch $branch): BranchDailyOperation
    {
        $this->assertActorCanManage($actor, $branch);

        $operation = DB::transaction(function () use ($actor, $branch): BranchDailyOperation {
            $open = BranchDailyOperation::query()
                ->where('branch_id', $branch->getKey())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if (! $open instanceof BranchDailyOperation) {
                throw new BranchDailyOperationException('La sucursal no está aperturada.');
            }

            $openBoxes = $this->openPhysicalCashBoxes($branch);
            if ($openBoxes->isNotEmpty()) {
                $labels = $this->openCashBoxLabels($openBoxes);

                throw new BranchDailyOperationException(
                    'Hay cajas abiertas. Debe cerrarlas antes de cerrar la sucursal: '.implode('; ', $labels).'.',
                    $labels,
                );
            }

            $open->forceFill([
                'closed_by_user_id' => $actor->getKey(),
                'closed_at' => now(),
            ])->save();

            return $open->fresh() ?? $open;
        });

        $report = $this->reconciliationBuilder->build($branch, $operation);

        AuditLogger::record(
            'branch_daily_operation_closed',
            'Sucursal · Cierre de gestión del día · '.$branch->name,
            Branch::class,
            $branch->getKey(),
            $branch->name,
            [
                'module' => 'branch_daily_operation',
                'branch_id' => $branch->getKey(),
                'branch_code' => $branch->code,
                'operation_id' => $operation->getKey(),
                'closed_by_user_id' => $actor->getKey(),
                'closed_by_email' => $actor->email,
                'opened_at' => $operation->opened_at?->toIso8601String(),
                'closed_at' => $operation->closed_at?->toIso8601String(),
                'total_usd' => $report['total_usd'],
                'total_ves' => $report['total_ves'],
            ],
        );

        try {
            $this->notifyOnClose->notify($actor, $branch, $operation, $report);
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar WhatsApp de cierre de sucursal', [
                'branch_id' => $branch->getKey(),
                'operation_id' => $operation->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        return $operation;
    }

    /**
     * @return Collection<int, PhysicalCashBox>
     */
    public function openPhysicalCashBoxes(Branch $branch): Collection
    {
        return PhysicalCashBox::query()
            ->with('user:id,name,email,branch_id')
            ->where('is_open', true)
            ->whereHas(
                'user',
                fn (Builder $query): Builder => $query->where('branch_id', $branch->getKey()),
            )
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, PhysicalCashBox>  $boxes
     * @return list<string>
     */
    public function openCashBoxLabels(Collection $boxes): array
    {
        return $boxes
            ->map(function (PhysicalCashBox $box): string {
                $cashier = $box->user;
                $name = filled($cashier?->name) ? (string) $cashier->name : (string) ($cashier?->email ?? 'Cajero #'.$box->user_id);

                return 'Caja de '.$name;
            })
            ->values()
            ->all();
    }

    public function wasClosedToday(Branch $branch): bool
    {
        $timezone = (string) config('app.timezone');
        $today = now()->timezone($timezone)->toDateString();

        return BranchDailyOperation::query()
            ->where('branch_id', $branch->getKey())
            ->whereNotNull('closed_at')
            ->whereDate('closed_at', $today)
            ->exists();
    }

    private function assertActorCanManage(User $actor, Branch $branch): void
    {
        if ($this->actorCanManage($actor, $branch)) {
            return;
        }

        throw new BranchDailyOperationException(
            'Solo gerencia o administración de esta sucursal pueden gestionar la apertura y el cierre.'
        );
    }
}
