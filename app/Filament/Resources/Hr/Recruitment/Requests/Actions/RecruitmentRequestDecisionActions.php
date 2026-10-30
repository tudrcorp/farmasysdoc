<?php

namespace App\Filament\Resources\Hr\Recruitment\Requests\Actions;

use App\Enums\RecruitmentRequestStatus;
use App\Filament\Resources\Hr\Recruitment\Requests\RecruitmentRequestResource;
use App\Models\RecruitmentRequest;
use App\Models\User;
use App\Services\Recruitment\RecruitmentRequestReviewer;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

final class RecruitmentRequestDecisionActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Aprobar')
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->modalHeading('Aprobar solicitud de personal')
            ->modalSubmitActionLabel('Aprobar')
            ->schema([
                Textarea::make('note')->label('Nota (opcional)')->rows(3)->maxLength(1000),
            ])
            ->visible(fn (RecruitmentRequest $record): bool => self::isAdministrator() && $record->isPending())
            ->action(self::handler('Solicitud aprobada', 'No se pudo aprobar', function (RecruitmentRequestReviewer $reviewer, RecruitmentRequest $record, User $user, ?string $note): void {
                $reviewer->approve($record, $user, $note);
            }));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Rechazar')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->modalHeading('Rechazar solicitud de personal')
            ->modalSubmitActionLabel('Rechazar')
            ->schema([
                Textarea::make('note')->label('Motivo del rechazo')->required()->rows(3)->maxLength(1000),
            ])
            ->visible(fn (RecruitmentRequest $record): bool => self::isAdministrator() && $record->isPending())
            ->action(self::handler('Solicitud rechazada', 'No se pudo rechazar', function (RecruitmentRequestReviewer $reviewer, RecruitmentRequest $record, User $user, ?string $note): void {
                $reviewer->reject($record, $user, (string) $note);
            }));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancelar solicitud')
            ->icon(Heroicon::NoSymbol)
            ->color('gray')
            ->modalHeading('Cancelar solicitud de personal')
            ->modalSubmitActionLabel('Cancelar solicitud')
            ->schema([
                Textarea::make('note')->label('Motivo')->required()->rows(3)->maxLength(1000),
            ])
            ->visible(fn (RecruitmentRequest $record): bool => self::isAdministrator()
                && $record->status === RecruitmentRequestStatus::Approved)
            ->action(self::handler('Solicitud cancelada', 'No se pudo cancelar', function (RecruitmentRequestReviewer $reviewer, RecruitmentRequest $record, User $user, ?string $note): void {
                $reviewer->cancel($record, $user, (string) $note);
            }));
    }

    private static function handler(string $successTitle, string $failureTitle, Closure $decision): Closure
    {
        return function (RecruitmentRequest $record, array $data) use ($successTitle, $failureTitle, $decision): void {
            try {
                $decision(app(RecruitmentRequestReviewer::class), $record, RecruitmentRequestResource::currentUser(), $data['note'] ?? null);
                Notification::make()->title($successTitle)->success()->send();
            } catch (Throwable $exception) {
                Notification::make()->title($failureTitle)->body($exception->getMessage())->danger()->send();
            }
        };
    }

    private static function isAdministrator(): bool
    {
        return RecruitmentRequestResource::currentUser()?->isAdministrator() ?? false;
    }
}
