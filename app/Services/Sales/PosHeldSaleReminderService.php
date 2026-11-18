<?php

namespace App\Services\Sales;

use App\Enums\PosHeldSaleSource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\PosHeldSale;
use App\Models\User;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa (campana del panel) de las ventas que llevan demasiado tiempo en pausa:
 * al cajero cada N minutos mientras sigan en pausa, y una vez al gerente de la sucursal.
 * Los pedidos de WhatsApp para retiro en tienda no se recuerdan: el cliente puede pasar horas después.
 */
final class PosHeldSaleReminderService
{
    /**
     * @return array{cashiers_notified: int, managers_notified: int}
     */
    public function run(): array
    {
        $minutes = max(1, (int) config('pos_held_sales.reminder_after_minutes', 15));
        $threshold = now()->subMinutes($minutes);

        $overdue = PosHeldSale::query()
            ->paused()
            ->where('source', PosHeldSaleSource::Pos->value)
            ->where('paused_at', '<=', $threshold)
            ->with(['user', 'branch:id,name'])
            ->get();

        if ($overdue->isEmpty()) {
            return ['cashiers_notified' => 0, 'managers_notified' => 0];
        }

        return [
            'cashiers_notified' => $this->remindCashiers($overdue, $threshold),
            'managers_notified' => $this->notifyManagers($overdue),
        ];
    }

    /**
     * @param  Collection<int, PosHeldSale>  $overdue
     */
    private function remindCashiers(Collection $overdue, DateTimeInterface $threshold): int
    {
        $notified = 0;

        foreach ($overdue->groupBy('user_id') as $heldSales) {
            /** @var Collection<int, PosHeldSale> $heldSales */
            $due = $heldSales->filter(fn (PosHeldSale $heldSale): bool => $heldSale->last_reminded_at === null
                || $heldSale->last_reminded_at->lte($threshold));

            $cashier = $heldSales->first()?->user;

            if ($due->isEmpty() || ! $cashier instanceof User) {
                continue;
            }

            $count = $heldSales->count();
            $oldest = $heldSales->max(fn (PosHeldSale $heldSale): int => $heldSale->minutesPaused());

            $this->send(
                $cashier,
                $count === 1 ? 'Tienes 1 venta en pausa' : 'Tienes '.$count.' ventas en pausa',
                'La más antigua lleva '.$oldest.' min en pausa. Cóbralas o elimínalas.',
            );

            PosHeldSale::query()
                ->whereIn('id', $heldSales->modelKeys())
                ->update(['last_reminded_at' => now()]);

            $notified++;
        }

        return $notified;
    }

    /**
     * @param  Collection<int, PosHeldSale>  $overdue
     */
    private function notifyManagers(Collection $overdue): int
    {
        $pending = $overdue->filter(fn (PosHeldSale $heldSale): bool => $heldSale->manager_notified_at === null
            && $heldSale->branch_id !== null);

        if ($pending->isEmpty()) {
            return 0;
        }

        $managers = User::query()
            ->get()
            ->filter(fn (User $user): bool => $user->isManager() && ! $user->isAdministrator());

        $notified = 0;

        foreach ($pending->groupBy('branch_id') as $branchId => $heldSales) {
            /** @var Collection<int, PosHeldSale> $heldSales */
            $branchName = $heldSales->first()?->branch?->name ?? 'la sucursal';
            $cashierNames = $heldSales->map(fn (PosHeldSale $heldSale): string => (string) ($heldSale->user?->name ?? 'Cajero'))
                ->unique()
                ->implode(', ');

            $count = $heldSales->count();

            foreach ($managers as $manager) {
                if (! in_array((int) $branchId, $manager->restrictedBranchIdsForQueries(), true)) {
                    continue;
                }

                $this->send(
                    $manager,
                    ($count === 1 ? '1 venta en pausa' : $count.' ventas en pausa').' en '.$branchName,
                    'Llevan más de '.(int) config('pos_held_sales.reminder_after_minutes', 15).' min en pausa. Cajero(s): '.$cashierNames.'.',
                );

                $notified++;
            }

            PosHeldSale::query()
                ->whereIn('id', $heldSales->modelKeys())
                ->update(['manager_notified_at' => now()]);
        }

        return $notified;
    }

    private function send(User $user, string $title, string $body): void
    {
        try {
            Notification::make()
                ->title($title)
                ->body($body)
                ->warning()
                ->icon('heroicon-o-pause-circle')
                ->actions([
                    Action::make('openHeldSales')
                        ->label('Ver ventas en pausa')
                        ->url(SaleResource::getUrl('index', ['ventas_pausadas' => 1], panel: 'farmaadmin'))
                        ->markAsRead(),
                ])
                ->sendToDatabase($user);
        } catch (Throwable $exception) {
            Log::warning('Ventas en pausa: no se pudo guardar el aviso de panel', [
                'user_id' => $user->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
