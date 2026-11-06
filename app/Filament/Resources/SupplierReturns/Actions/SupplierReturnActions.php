<?php

namespace App\Filament\Resources\SupplierReturns\Actions;

use App\Enums\SupplierReturnStatus;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnAttachment;
use App\Models\User;
use App\Services\Purchases\SupplierReturnException;
use App\Services\Purchases\SupplierReturnWorkflow;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Acciones de revisión y entrega. La lógica y las validaciones viven en {@see SupplierReturnWorkflow}.
 */
final class SupplierReturnActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [self::approve(), self::reject(), self::markReturned()];
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Aprobar devolución')
            ->icon(Heroicon::CheckBadge)
            ->color('success')
            ->visible(fn (SupplierReturn $record): bool => $record->status === SupplierReturnStatus::PendingReview && self::user()?->isAdministrator() === true)
            ->requiresConfirmation()
            ->modalDescription('La sucursal recibirá el aviso para entregar la mercancía al proveedor.')
            ->schema([Textarea::make('notes')->label('Observación (opcional)')->rows(2)])
            ->action(self::run(fn (SupplierReturnWorkflow $workflow, SupplierReturn $record, User $user, array $data) => $workflow->approve($record, $user, $data['notes'] ?? null), 'Devolución aprobada'));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Rechazar')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->visible(fn (SupplierReturn $record): bool => $record->status === SupplierReturnStatus::PendingReview && self::user()?->isAdministrator() === true)
            ->schema([Textarea::make('reason')->label('Motivo del rechazo')->required()->rows(3)])
            ->action(self::run(fn (SupplierReturnWorkflow $workflow, SupplierReturn $record, User $user, array $data) => $workflow->reject($record, $user, (string) $data['reason']), 'Devolución rechazada'));
    }

    public static function markReturned(): Action
    {
        return Action::make('markReturned')
            ->label('Marcar como devuelta')
            ->icon(Heroicon::Truck)
            ->color('primary')
            ->visible(fn (SupplierReturn $record): bool => ($user = self::user()) instanceof User && app(SupplierReturnWorkflow::class)->canMarkReturned($user, $record))
            ->modalHeading('Entrega al proveedor')
            ->schema([
                DatePicker::make('returned_on')->label('Fecha de entrega')->default(now())->maxDate(now())->required()->native(false)->displayFormat('d/m/Y'),
                Textarea::make('notes')->label('Nota (quién recibió, número de nota de crédito…)')->rows(2),
                FileUpload::make('attachments')
                    ->label('Soporte de entrega (opcional)')
                    ->multiple()
                    ->disk(fn (): string => SupplierReturnAttachment::disk())
                    ->directory(fn (): string => (string) config('supplier_returns.attachment_directory'))
                    ->visibility('private')
                    ->acceptedFileTypes(fn (): array => (array) config('supplier_returns.attachment_accepted_mime_types'))
                    ->maxSize(fn (): int => (int) config('supplier_returns.attachment_max_size_kb'))
                    ->storeFileNamesIn('attachment_names'),
            ])
            ->action(self::run(function (SupplierReturnWorkflow $workflow, SupplierReturn $record, User $user, array $data) {
                $names = (array) ($data['attachment_names'] ?? []);
                $attachments = [];
                foreach ((array) ($data['attachments'] ?? []) as $path) {
                    $attachments[(string) $path] = (string) ($names[$path] ?? basename((string) $path));
                }

                return $workflow->markReturned($record, $user, CarbonImmutable::parse((string) $data['returned_on']), $data['notes'] ?? null, $attachments);
            }, 'Devolución marcada como entregada'));
    }

    /**
     * @param  Closure(SupplierReturnWorkflow, SupplierReturn, User, array<string, mixed>): mixed  $operation
     */
    private static function run(Closure $operation, string $successTitle): Closure
    {
        return function (SupplierReturn $record, array $data, Action $action) use ($operation, $successTitle): void {
            $user = self::user();

            if (! $user instanceof User) {
                $action->halt();
            }

            try {
                $operation(app(SupplierReturnWorkflow::class), $record, $user, $data);
            } catch (SupplierReturnException $exception) {
                Notification::make()->title('No se pudo completar')->body($exception->getMessage())->danger()->persistent()->send();
                $action->halt();
            }

            Notification::make()->title($successTitle)->success()->send();
            $record->refresh();
        };
    }

    private static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
