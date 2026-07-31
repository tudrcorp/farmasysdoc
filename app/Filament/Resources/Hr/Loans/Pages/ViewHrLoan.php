<?php

namespace App\Filament\Resources\Hr\Loans\Pages;

use App\Enums\HrLoanStatus;
use App\Filament\Resources\Hr\Loans\HrLoanResource;
use App\Models\HrLoan;
use App\Models\User;
use App\Services\Hr\HrLoanApprover;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ViewHrLoan extends ViewRecord
{
    protected static string $resource = HrLoanResource::class;

    protected static ?string $title = 'Detalle del préstamo';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobar')
                ->icon(Heroicon::CheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->canDecide())
                ->action(function (): void {
                    try {
                        app(HrLoanApprover::class)->approve($this->getRecord(), $this->adminUser());
                        Notification::make()->title('Préstamo aprobado')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('No se pudo aprobar')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('reject')
                ->label('Rechazar')
                ->icon(Heroicon::XCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->canDecide())
                ->action(function (): void {
                    try {
                        app(HrLoanApprover::class)->reject($this->getRecord(), $this->adminUser());
                        Notification::make()->title('Préstamo rechazado')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('No se pudo rechazar')->body($e->getMessage())->danger()->send();
                    }
                }),
            EditAction::make()
                ->visible(fn (): bool => HrLoanResource::currentUser()?->isAdministrator() ?? false),
        ];
    }

    private function canDecide(): bool
    {
        $user = HrLoanResource::currentUser();
        $record = $this->getRecord();

        return $user instanceof User
            && $user->isAdministrator()
            && $record instanceof HrLoan
            && $record->status === HrLoanStatus::PendingApproval;
    }

    private function adminUser(): User
    {
        $user = HrLoanResource::currentUser();
        if (! $user instanceof User) {
            throw new \RuntimeException('Usuario no autenticado.');
        }

        return $user;
    }
}
