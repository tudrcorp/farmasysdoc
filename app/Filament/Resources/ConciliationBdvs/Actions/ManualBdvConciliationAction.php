<?php

namespace App\Filament\Resources\ConciliationBdvs\Actions;

use App\Enums\VenezuelanPagoMovilBank;
use App\Models\Branch;
use App\Models\User;
use App\Services\BdvConciliation\BdvPagomovilConciliationService;
use App\Services\BdvConciliation\ManualBdvConciliationOtpService;
use App\Services\BdvConciliation\ManualBdvConciliationService;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

final class ManualBdvConciliationAction
{
    public const NAME = 'manualBdvConciliation';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Conciliación Manual')
            ->icon(Heroicon::Key)
            ->color('warning')
            ->modalHeading('Conciliación Manual')
            ->modalDescription('Registra un Pago Móvil conciliado a mano, sin consultar la API del Banco de Venezuela. Requiere una clave OTP de 6 dígitos enviada por email y WhatsApp (válida 10 minutos).')
            ->modalSubmitActionLabel('Registrar conciliación')
            ->modalWidth(Width::Large)
            ->visible(fn (): bool => self::actorCanRegister())
            ->fillForm(self::defaultFormState(...))
            ->schema(self::formSchema())
            ->successNotificationTitle('Conciliación manual registrada')
            ->action(self::submitAction(...));
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultFormState(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        $service = app(BdvPagomovilConciliationService::class);
        $branchId = $service->defaultBranchIdForUser($user);

        return [
            'branch_id' => $branchId,
            'payment_date' => now()->toDateString(),
            'destination_phone' => $service->resolveCommercePhone($branchId),
            'origin_bank' => VenezuelanPagoMovilBank::BancoDeVenezuela->value,
            'otp_code' => null,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function formSchema(): array
    {
        return [
            Select::make('branch_id')
                ->label('Sucursal')
                ->options(fn (): array => BranchAuthScope::applyToBranchFormSelect(
                    Branch::query()->orderBy('name'),
                )->pluck('name', 'id')->all())
                ->required()
                ->searchable()
                ->preload()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state): void {
                    $phone = app(BdvPagomovilConciliationService::class)->resolveCommercePhone(
                        filled($state) ? (int) $state : null,
                    );
                    $set('destination_phone', $phone);
                })
                ->prefixIcon(Heroicon::BuildingStorefront)
                ->columnSpanFull(),

            Grid::make(2)
                ->schema([
                    TextInput::make('reference')
                        ->label('Referencia')
                        ->required()
                        ->maxLength(64)
                        ->placeholder('12345678'),
                    TextInput::make('amount')
                        ->label('Monto (VES)')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->inputMode('decimal')
                        ->helperText('Use punto (.) como separador decimal.')
                        ->prefix('VES'),
                    TextInput::make('payer_document')
                        ->label('Doc. pagador')
                        ->required()
                        ->maxLength(32)
                        ->placeholder('V12345678'),
                    TextInput::make('payer_phone')
                        ->label('Tel. pagador')
                        ->required()
                        ->maxLength(32)
                        ->placeholder('04141234567'),
                    TextInput::make('destination_phone')
                        ->label('Tel. destino')
                        ->required()
                        ->maxLength(32)
                        ->placeholder('04141234567'),
                    DatePicker::make('payment_date')
                        ->label('Fecha de pago')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->prefixIcon(Heroicon::CalendarDays)
                        ->default(now()->toDateString()),
                ])
                ->columnSpanFull(),

            Select::make('origin_bank')
                ->label('Banco origen')
                ->options(VenezuelanPagoMovilBank::optionsForSelect())
                ->required()
                ->searchable()
                ->native(false)
                ->columnSpanFull(),

            Placeholder::make('otp_help')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<div class="rounded-xl border border-warning-500/40 bg-warning-500/10 p-3 text-sm text-warning-700 dark:text-warning-200">'
                    .'<p class="font-medium">Autorización OTP</p>'
                    .'<p class="mt-1">Complete los datos y solicite la clave. Se enviará por email y WhatsApp. Si un gerente ejecuta la acción, la reciben ese gerente y los administradores. Si un administrador la dispara, solo llega a administradores. Caduca en 10 minutos.</p>'
                    .'</div>'
                ))
                ->columnSpanFull(),

            SchemaActions::make([
                Action::make('requestManualConciliationOtp')
                    ->label('Solicitar código OTP')
                    ->icon(Heroicon::Key)
                    ->color('warning')
                    ->action(function (Get $get): void {
                        $user = Auth::user();
                        if (! $user instanceof User) {
                            return;
                        }

                        $missing = self::missingFieldsForOtp($get);
                        if ($missing !== []) {
                            Notification::make()
                                ->title('Complete los datos')
                                ->body('Antes de solicitar el OTP complete: '.implode(', ', $missing).'.')
                                ->warning()
                                ->send();

                            return;
                        }

                        try {
                            $context = app(ManualBdvConciliationService::class)->otpContextFromForm([
                                'branch_id' => $get('branch_id'),
                                'reference' => $get('reference'),
                                'amount' => $get('amount'),
                                'payer_document' => $get('payer_document'),
                                'payer_phone' => $get('payer_phone'),
                                'destination_phone' => $get('destination_phone'),
                                'payment_date' => $get('payment_date'),
                                'origin_bank' => $get('origin_bank'),
                            ]);

                            app(ManualBdvConciliationOtpService::class)->issue($user, $context);

                            $body = $user->isAdministrator()
                                ? 'Se envió un código de 6 dígitos por email y WhatsApp a los administradores, con el detalle de la conciliación. Caduca en 10 minutos.'
                                : 'Se envió un código de 6 dígitos por email y WhatsApp a usted y a los administradores, con el detalle de la conciliación. Caduca en 10 minutos.';

                            Notification::make()
                                ->title('Código OTP enviado')
                                ->body($body)
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('No se pudo solicitar el OTP')
                                ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
                ->columnSpanFull(),

            OneTimeCodeInput::make('otp_code')
                ->label('Código OTP')
                ->length(6)
                ->required()
                ->helperText('Ingrese el código de 6 dígitos. Es de un solo uso y caduca a los 10 minutos.')
                ->columnSpanFull(),
        ];
    }

    public static function submitAction(array $data, Action $action): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            Notification::make()
                ->title('Debe iniciar sesión.')
                ->danger()
                ->send();
            $action->halt();

            return;
        }

        try {
            app(ManualBdvConciliationService::class)->register($user, $data);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo registrar')
                ->body(collect($e->errors())->flatten()->first() ?: 'Error de validación.')
                ->danger()
                ->send();

            $action->halt();
        }
    }

    public static function actorCanRegister(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && app(ManualBdvConciliationService::class)->canRegister($user);
    }

    /**
     * @return list<string>
     */
    private static function missingFieldsForOtp(Get $get): array
    {
        $required = [
            'branch_id' => 'sucursal',
            'reference' => 'referencia',
            'amount' => 'monto',
            'payer_document' => 'documento del pagador',
            'payer_phone' => 'teléfono del pagador',
            'destination_phone' => 'teléfono destino',
            'payment_date' => 'fecha de pago',
            'origin_bank' => 'banco origen',
        ];

        $missing = [];
        foreach ($required as $field => $label) {
            if (blank($get($field))) {
                $missing[] = $label;
            }
        }

        return $missing;
    }
}
