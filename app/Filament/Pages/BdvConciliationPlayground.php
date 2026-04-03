<?php

namespace App\Filament\Pages;

use App\Http\Requests\BdvConciliation\GetMovementRequest;
use App\Models\User;
use App\Services\BdvConciliation\BdvConciliationClient;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use UnitEnum;

class BdvConciliationPlayground extends Page
{
    protected static ?string $title = 'Prueba API Conciliación BDV';

    protected static ?string $navigationLabel = 'Conciliación BDV (pruebas)';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 88;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingLibrary;

    protected static ?string $slug = 'bdv-conciliation-playground';

    protected string $view = 'filament.pages.bdv-conciliation-playground';

    public string $cedulaPagador = '';

    public string $telefonoPagador = '';

    public string $telefonoDestino = '';

    public string $referencia = '';

    public string $fechaPago = '';

    public string $importe = '';

    public string $bancoOrigen = '';

    public string $environment = 'qa';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastResult = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdministrator();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->fillSampleFieldsQuietly();
    }

    /**
     * Rellena los campos con el ejemplo del manual sin notificación (primera carga).
     */
    private function fillSampleFieldsQuietly(): void
    {
        $this->cedulaPagador = 'V16007868';
        $this->telefonoPagador = '04127018390';
        $this->telefonoDestino = '04245718777';
        $this->referencia = '123112313';
        $this->fechaPago = '2023-02-12';
        $this->importe = '120.00';
        $this->bancoOrigen = '0102';
        $this->environment = 'qa';
        $this->lastResult = null;
    }

    public function loadSampleFromManual(): void
    {
        $this->fillSampleFieldsQuietly();

        Notification::make()
            ->title('Datos de ejemplo cargados')
            ->body('Son los mismos que figuran en el manual MDU-006 (ambiente de calidad). Ajusta y pulsa «Consultar en BDV».')
            ->success()
            ->send();
    }

    public function conciliar(BdvConciliationClient $client): void
    {
        $this->lastResult = null;

        $rules = array_merge(
            (new GetMovementRequest)->rules(),
            [
                'environment' => ['required', 'string', Rule::in(['qa', 'production'])],
            ],
        );

        $messages = array_merge(
            (new GetMovementRequest)->messages(),
            [
                'environment.in' => 'El entorno debe ser calidad (qa) o producción (production).',
            ],
        );

        $validated = $this->validate($rules, $messages);
        $environment = $validated['environment'];
        unset($validated['environment']);

        /** @var array<string, string> $movement */
        $movement = $validated;
        $payload = GetMovementRequest::movementPayloadFromValidated($movement);

        try {
            $response = $client->postGetMovement($payload, $environment);
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Solicitud no válida')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (RuntimeException $e) {
            Notification::make()
                ->title('No se puede llamar al banco')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (ConnectionException $e) {
            Notification::make()
                ->title('Error de conexión')
                ->body('No se alcanzó el servicio BDV: '.$e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (Throwable $e) {
            Notification::make()
                ->title('Error inesperado')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $decoded = $response->json();

        $this->lastResult = [
            'upstream_http_status' => $response->status(),
            'upstream_successful' => $response->successful(),
            'body' => is_array($decoded) ? $decoded : $response->body(),
            'body_is_json' => is_array($decoded),
        ];

        Notification::make()
            ->title('Respuesta recibida')
            ->body('Revisa el panel inferior: código HTTP del banco y cuerpo de la respuesta.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function getManualSamplePayload(): array
    {
        return [
            'cedulaPagador' => 'V27037606',
            'telefonoPagador' => '04127141363',
            'telefonoDestino' => '04127141363',
            'referencia' => '123112313',
            'fechaPago' => '2023-02-12',
            'importe' => '120.00',
            'bancoOrigen' => '0102',
        ];
    }
}
