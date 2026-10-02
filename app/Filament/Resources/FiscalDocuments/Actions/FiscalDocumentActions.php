<?php

namespace App\Filament\Resources\FiscalDocuments\Actions;

use App\Enums\FiscalDocumentStatus;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use App\Services\Fiscal\FiscalDocumentRegistrar;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FiscalDocumentActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            self::retry(),
            self::resolveManually(),
            self::cancel(),
        ];
    }

    public static function retry(): Action
    {
        return Action::make('retryFiscalDocument')
            ->label('Reintentar')
            ->icon(Heroicon::ArrowPath)
            ->color('primary')
            ->visible(fn (FiscalDocument $record): bool => ! $record->simulation && $record->status === FiscalDocumentStatus::Failed)
            ->modalHeading('Reintentar impresión fiscal')
            ->modalDescription('Solo use esta opción si el documento NO salió impreso en la máquina fiscal.')
            ->schema([
                Select::make('fiscal_printer_id')
                    ->label('Máquina fiscal')
                    ->options(fn (FiscalDocument $record): array => FiscalPrinter::query()
                        ->where('is_active', true)
                        ->where('branch_id', $record->fiscalPrinter?->branch_id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->default(fn (FiscalDocument $record): int => (int) $record->fiscal_printer_id)
                    ->required()
                    ->native(false)
                    ->helperText('Puede enviarlo a otra máquina de la misma sucursal si la original está dañada.'),
            ])
            ->action(function (FiscalDocument $record, array $data): void {
                self::run(fn () => app(FiscalDocumentRegistrar::class)->retry(
                    $record,
                    self::actor(),
                    FiscalPrinter::query()->find($data['fiscal_printer_id'] ?? null),
                ), 'Documento reenviado a la cola');
            });
    }

    public static function resolveManually(): Action
    {
        return Action::make('resolveFiscalDocument')
            ->label('Resolver a mano')
            ->icon(Heroicon::WrenchScrewdriver)
            ->color('warning')
            ->visible(fn (FiscalDocument $record): bool => ! $record->simulation && in_array($record->status, [
                FiscalDocumentStatus::NeedsReview,
                FiscalDocumentStatus::Claimed,
                FiscalDocumentStatus::Printing,
            ], true))
            ->modalHeading('Resolver documento fiscal')
            ->modalDescription('Revise la memoria fiscal (o el último ticket impreso) antes de decidir. Si salió impreso, indique su número; si no salió, deje el número vacío y luego podrá reintentar.')
            ->schema([
                TextInput::make('fiscal_number')
                    ->label('Nº fiscal impreso')
                    ->maxLength(20)
                    ->default(fn (FiscalDocument $record): ?string => $record->fiscal_number)
                    ->helperText('Vacío = el documento no salió impreso.'),
                Textarea::make('notes')
                    ->label('Observación')
                    ->required()
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (FiscalDocument $record, array $data): void {
                self::run(fn () => app(FiscalDocumentRegistrar::class)->resolveManually(
                    $record,
                    $data['fiscal_number'] ?? null,
                    self::actor(),
                    $data['notes'] ?? null,
                ), 'Documento resuelto');
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancelFiscalDocument')
            ->label('Anular en cola')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->visible(fn (FiscalDocument $record): bool => in_array($record->status, [
                FiscalDocumentStatus::Pending,
                FiscalDocumentStatus::Failed,
            ], true))
            ->requiresConfirmation()
            ->modalHeading('¿Anular este documento en cola?')
            ->modalDescription('No se imprimirá en la máquina fiscal. Úselo solo si la venta se facturará por otra vía (p. ej. forma libre).')
            ->action(function (FiscalDocument $record): void {
                self::run(fn () => app(FiscalDocumentRegistrar::class)->cancel($record, self::actor()), 'Documento anulado');
            });
    }

    private static function actor(): string
    {
        $user = Auth::user();

        return (string) ($user?->email ?? $user?->name ?? 'sistema');
    }

    private static function run(callable $callback, string $successTitle): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo completar la acción')
                ->body(collect($e->errors())->flatten()->implode(' '))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($successTitle)
            ->success()
            ->send();
    }
}
