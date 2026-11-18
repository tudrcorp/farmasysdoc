<?php

namespace App\Filament\Resources\PartnerPlanAffiliates\Pages;

use App\Enums\PartnerAffiliateSource;
use App\Filament\Resources\PartnerPlanAffiliates\PartnerPlanAffiliateResource;
use App\Models\Client;
use App\Models\PartnerCompanyPlan;
use App\Models\User;
use App\Services\Partners\PartnerAffiliateService;
use App\Services\Partners\PartnerFundLedger;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ManagePartnerPlanAffiliates extends ManageRecords
{
    protected static string $resource = PartnerPlanAffiliateResource::class;

    public function getSubheading(): ?string
    {
        return 'Clientes que pueden comprar en caja con cargo al fondo de su convenio (plan Flexible).';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importAffiliates')
                ->label('Cargar afiliados')
                ->icon(Heroicon::ArrowUpTray)
                ->color('primary')
                ->modalHeading('Cargar afiliados desde Excel o CSV')
                ->modalDescription(new HtmlString('Primera fila con los encabezados <strong>Cédula</strong> y <strong>Nombre</strong> (opcionales: <strong>Teléfono</strong> y <strong>Correo</strong>). Si la cédula ya existe como cliente se reutiliza; si ya estaba afiliado no se duplica.'))
                ->modalSubmitActionLabel('Cargar')
                ->schema([
                    Select::make('assignment_id')
                        ->label('Aliado y plan Flexible')
                        ->options(fn (): array => app(PartnerAffiliateService::class)->flexibleAssignmentOptions())
                        ->searchable()
                        ->required(),
                    FileUpload::make('file')
                        ->label('Archivo')
                        ->disk('local')
                        ->directory('tmp/partner-affiliates')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $assignment = PartnerCompanyPlan::query()->find((int) $data['assignment_id']);
                    $user = Auth::user();
                    $path = Storage::disk('local')->path((string) $data['file']);

                    try {
                        if (! $assignment instanceof PartnerCompanyPlan || ! $user instanceof User) {
                            throw ValidationException::withMessages(['assignment_id' => 'Elija el aliado y el plan.']);
                        }

                        $service = app(PartnerAffiliateService::class);
                        $parsed = $service->parseImportFile($path);
                        $result = $service->import($assignment, $parsed['rows'], $user);
                    } catch (ValidationException $exception) {
                        Notification::make()->title('No se pudo cargar')->body((string) collect($exception->errors())->flatten()->first())->danger()->persistent()->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete((string) $data['file']);
                    }

                    $body = "Afiliados nuevos: {$result['enrolled']} · Ya estaban: {$result['already']} · Clientes creados: {$result['created_clients']}";
                    if ($parsed['duplicates'] > 0) {
                        $body .= " · Repetidos en el archivo: {$parsed['duplicates']}";
                    }
                    if ($parsed['errors'] !== []) {
                        $body .= '<br><strong>'.count($parsed['errors']).' filas con error:</strong><br>'.implode('<br>', array_map('e', array_slice($parsed['errors'], 0, 10)));
                        if (count($parsed['errors']) > 10) {
                            $body .= '<br>…';
                        }
                    }

                    Notification::make()
                        ->title('Carga de afiliados terminada')
                        ->body(new HtmlString($body))
                        ->color($parsed['errors'] === [] ? 'success' : 'warning')
                        ->icon(Heroicon::CheckCircle)
                        ->persistent()
                        ->send();
                }),
            Action::make('enrollOne')
                ->label('Afiliar cliente')
                ->icon(Heroicon::UserPlus)
                ->color('gray')
                ->modalHeading('Afiliar un cliente')
                ->schema([
                    Select::make('assignment_id')
                        ->label('Aliado y plan Flexible')
                        ->options(fn (): array => app(PartnerAffiliateService::class)->flexibleAssignmentOptions())
                        ->searchable()
                        ->required(),
                    Select::make('client_id')
                        ->label('Cliente')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Client::query()
                            ->where(fn ($query) => $query
                                ->where('name', 'like', '%'.trim($search).'%')
                                ->when(PartnerAffiliateService::normalizeDocument($search) !== '', fn ($q) => $q->orWhere('document_number', 'like', PartnerAffiliateService::normalizeDocument($search).'%')))
                            ->limit(30)
                            ->get(['id', 'name', 'document_number'])
                            ->mapWithKeys(fn (Client $client): array => [$client->id => $client->name.' · C.I. '.$client->document_number])
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => Client::query()->find($value)?->name)
                        ->required(),
                    TextInput::make('validated_with')
                        ->label('Validado con')
                        ->helperText('Persona de la empresa que confirmó la afiliación.')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $assignment = PartnerCompanyPlan::query()->find((int) $data['assignment_id']);
                    $client = Client::query()->find((int) $data['client_id']);

                    if (! $assignment instanceof PartnerCompanyPlan || ! $client instanceof Client) {
                        Notification::make()->title('Datos incompletos')->danger()->send();

                        return;
                    }

                    app(PartnerAffiliateService::class)->enroll($assignment, $client, PartnerAffiliateSource::Counter, Auth::user(), (string) $data['validated_with']);
                    Notification::make()->title('Cliente afiliado')->success()->send();
                }),
            Action::make('rechargeFund')
                ->label('Recargar fondo')
                ->icon(Heroicon::Banknotes)
                ->color('success')
                ->visible(fn (): bool => Auth::user() instanceof User && Auth::user()->isAdministrator())
                ->modalHeading('Recargar fondo de consumo')
                ->modalDescription('Suma al disponible del convenio. Queda registrado en el estado de cuenta del aliado.')
                ->schema([
                    Select::make('assignment_id')
                        ->label('Aliado y plan Flexible')
                        ->options(fn (): array => collect(app(PartnerAffiliateService::class)->flexibleAssignmentOptions())
                            ->map(fn (string $label, int $id): string => $label.' · disponible $'.number_format(PartnerCompanyPlan::query()->find($id)?->fundAvailableUsd() ?? 0, 2, ',', '.'))
                            ->all())
                        ->required(),
                    TextInput::make('amount_usd')->label('Monto (USD)')->numeric()->minValue(0.01)->prefix('$')->required(),
                    TextInput::make('reason')->label('Motivo')->required()->maxLength(255)->placeholder('Ej.: Recarga mensual de noviembre'),
                ])
                ->action(function (array $data): void {
                    $assignment = PartnerCompanyPlan::query()->find((int) $data['assignment_id']);
                    $user = Auth::user();

                    if (! $assignment instanceof PartnerCompanyPlan || ! $user instanceof User || ! $user->isAdministrator()) {
                        Notification::make()->title('No autorizado')->danger()->send();

                        return;
                    }

                    $movement = app(PartnerFundLedger::class)->recharge($assignment, (float) $data['amount_usd'], $user, (string) $data['reason']);
                    Notification::make()->title('Fondo recargado')->body('Nuevo disponible: $'.number_format((float) $movement->balance_after_usd, 2, ',', '.'))->success()->send();
                }),
        ];
    }
}
