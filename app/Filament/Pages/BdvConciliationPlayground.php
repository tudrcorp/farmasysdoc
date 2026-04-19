<?php

namespace App\Filament\Pages;

use App\Http\Requests\BdvConciliation\GetMovementRequest;
use App\Models\User;
use App\Services\BdvConciliation\BdvBankApiClient;
use App\Services\BdvConciliation\BdvConciliationClient;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use UnitEnum;

/**
 * Playground de integración BDV para administradores: prueba manual de los servicios REST documentados
 * (conciliación, saldo, movimientos, C2P, etc.) contra calidad o producción según configuración.
 *
 * No sustituye tests automatizados; sirve para validar credenciales, conectividad y respuestas antes de go-live.
 */
class BdvConciliationPlayground extends Page
{
    protected static ?string $title = 'Integración Banco de Venezuela (pruebas)';

    protected static ?string $navigationLabel = 'BDV — APIs (pruebas)';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 88;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingLibrary;

    protected static ?string $slug = 'bdv-conciliation-playground';

    protected string $view = 'filament.pages.bdv-conciliation-playground';

    /** Pestaña activa en la UI (wire:click cambia este valor). */
    public string $activeTab = 'conciliation';

    public string $environment = 'qa';

    /* --- Conciliación Pagomóvil (getMovement/v2) --- */
    public string $cedulaPagador = '';

    public string $telefonoPagador = '';

    public string $telefonoDestino = '';

    public string $referencia = '';

    public string $fechaPago = '';

    public string $importe = '';

    public string $bancoOrigen = '';

    public bool $reqCed = false;

    /* --- Conciliación múltiple --- */
    public string $cmFechaPago = '';

    public string $cmBancoOrigen = '';

    public string $cmTelefonoCliente = '';

    /* --- Consulta de saldo --- */
    public string $saldoCurrency = 'VES';

    public string $saldoAccount = '';

    /* --- Consulta de movimientos --- */
    public string $movCuenta = '';

    public string $movFechaIni = '';

    public string $movFechaFin = '';

    public string $movTipoMoneda = 'VES';

    public string $movNroMovimiento = '';

    /* --- Operaciones salientes --- */
    public string $outCedulaPagador = '';

    public string $outTelefonoPagador = '';

    public string $outTelefonoDestino = '';

    public string $outReferencia = '';

    public string $outFechaPago = '';

    public string $outImporte = '';

    public string $outBancoOrigen = '';

    public string $outBancoDestino = '';

    /* --- Vuelto --- */
    public string $vueltoNumeroReferencia = '';

    public string $vueltoMonto = '';

    public string $vueltoNacionalidadDestino = 'V';

    public string $vueltoCedulaDestino = '';

    public string $vueltoTelefonoDestino = '';

    public string $vueltoBancoDestino = '';

    public string $vueltoMoneda = 'VES';

    public string $vueltoConcepto = '';

    /* --- C2P: solicitud de OTP --- */
    public string $c2pCustomerDocumentId = '';

    /* --- C2P: cobro --- */
    public string $c2pCustomerNumberInstrument = '';

    public string $c2pAmount = '';

    public string $c2pCustomerBankCode = '';

    public string $c2pConcept = '';

    public string $c2pOtp = '';

    public string $c2pCoinType = 'VES';

    public string $c2pOperationType = 'CELE';

    public string $c2pCommerceNumberInstrument = '';

    /* --- C2P: anulación --- */
    public string $c2pEndToEndId = '';

    public string $c2pReferenceOrigin = '';

    /* --- Pago móvil por lote (ventana 15 min) --- */
    public string $loteDate = '';

    public string $loteTimeStart = '';

    public string $loteTimeEnd = '';

    public string $loteNumeroComercio = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $lastResult = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessFarmaadminMenuKey('bdv_playground');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->fillAllSamplesQuietly();
    }

    public function setTab(string $tab): void
    {
        $allowed = [
            'conciliation', 'multiple', 'balance', 'movements', 'outgoing',
            'vuelto', 'c2p', 'lote', 'webhook',
        ];
        if (in_array($tab, $allowed, true)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Rellena formularios con los ejemplos de los manuales dummy (calidad), sin notificación.
     */
    private function fillAllSamplesQuietly(): void
    {
        $this->environment = 'qa';
        $this->lastResult = null;

        $this->cedulaPagador = 'V27037606';
        $this->telefonoPagador = '04127141363';
        $this->telefonoDestino = '04127141363';
        $this->referencia = '12345678';
        $this->fechaPago = '2023-02-12';
        $this->importe = '120.00';
        $this->bancoOrigen = '0102';
        $this->reqCed = false;

        $this->cmFechaPago = '2025-11-06';
        $this->cmBancoOrigen = '0102';
        $this->cmTelefonoCliente = '04141234567';

        $this->saldoAccount = '01020467440007909094';

        $this->movCuenta = '01020501830003283374';
        $this->movFechaIni = '01/01/2025';
        $this->movFechaFin = '28/01/2025';
        $this->movNroMovimiento = '';

        $this->outCedulaPagador = 'J1330321';
        $this->outTelefonoPagador = '04123963208';
        $this->outTelefonoDestino = '04123963208';
        $this->outReferencia = '000000685519';
        $this->outFechaPago = '2024-11-13';
        $this->outImporte = '0.20';
        $this->outBancoOrigen = '0102';
        $this->outBancoDestino = '0102';

        $this->vueltoNumeroReferencia = '6111121716';
        $this->vueltoMonto = '1.21';
        $this->vueltoCedulaDestino = '15404774';
        $this->vueltoTelefonoDestino = '04123963208';
        $this->vueltoBancoDestino = '0102';
        $this->vueltoConcepto = 'Prueba Api Vuelto';

        $this->c2pCustomerDocumentId = 'V12345678';
        $this->c2pCustomerNumberInstrument = '04125692243';
        $this->c2pAmount = '1000.6';
        $this->c2pCustomerBankCode = '0102';
        $this->c2pConcept = 'Pago';
        $this->c2pOtp = '5551111';
        $this->c2pCommerceNumberInstrument = '04140282647';
        $this->c2pEndToEndId = '';
        $this->c2pReferenceOrigin = '';

        $this->loteDate = '2025-12-04';
        $this->loteTimeStart = '09:10:00';
        $this->loteTimeEnd = '09:25:00';
        $this->loteNumeroComercio = '04123963208';
    }

    public function loadSamplesFromManuals(): void
    {
        $this->fillAllSamplesQuietly();

        Notification::make()
            ->title('Datos de ejemplo cargados')
            ->body('Valores tomados de los PDF dummy de calidad BDV. Revisa cada pestaña y ejecuta la prueba que necesites.')
            ->success()
            ->send();
    }

    public function runConciliation(BdvConciliationClient $client): void
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

        /** @var array<string, mixed> $movement */
        $movement = $validated;
        $payload = GetMovementRequest::movementPayloadFromValidated($movement);

        $this->dispatchBdvCall(
            fn () => $client->postGetMovement($payload, $environment),
            'Conciliación Pagomóvil (getMovement)',
        );
    }

    public function runConsultaMultiple(BdvBankApiClient $bank): void
    {
        $this->lastResult = null;

        $validated = $this->validate([
            'environment' => ['required', 'string', Rule::in(['qa', 'production'])],
            'cmFechaPago' => ['required', 'string', 'date_format:Y-m-d'],
            'cmBancoOrigen' => ['required', 'string', 'max:16'],
            'cmTelefonoCliente' => ['required', 'string', 'max:32'],
        ], [
            'cmFechaPago.date_format' => 'Use formato AAAA-MM-DD.',
        ]);

        $payload = [
            'fechaPago' => $validated['cmFechaPago'],
            'bancoOrigen' => $validated['cmBancoOrigen'],
            'telefonoCliente' => $validated['cmTelefonoCliente'],
        ];

        $path = $bank->path('consulta_multiple');

        $this->dispatchBdvCall(
            fn () => $bank->postJson($path, $payload, $validated['environment'], 'suite'),
            'Conciliación múltiple',
        );
    }

    public function runConsultaSaldo(BdvBankApiClient $bank): void
    {
        $this->lastResult = null;

        $validated = $this->validate([
            'environment' => ['required', 'string', Rule::in(['qa', 'production'])],
            'saldoCurrency' => ['required', 'string', 'max:8'],
            'saldoAccount' => ['required', 'string', 'max:64'],
        ]);

        // dd($validated);

        $payload = [
            'currency' => $validated['saldoCurrency'],
            'account' => $validated['saldoAccount'],
        ];

        // dd($payload);

        $path = $bank->path('account_balances');

        $this->dispatchBdvCall(
            fn () => $bank->postJson($path, $payload, $validated['environment'], 'suite'),
            'Consulta de saldo',
        );
    }

    public function runConsultaMovimientos(BdvBankApiClient $bank): void
    {
        $this->lastResult = null;

        $validated = $this->validate([
            'environment' => ['required', 'string', Rule::in(['qa', 'production'])],
            'movCuenta' => ['required', 'string', 'max:64'],
            'movFechaIni' => ['required', 'string', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'movFechaFin' => ['required', 'string', 'regex:/^\d{2}\/\d{2}\/\d{4}$/'],
            'movTipoMoneda' => ['required', 'string', 'in:VES'],
            'movNroMovimiento' => ['nullable', 'string', 'max:32'],
        ], [
            'movFechaIni.regex' => 'fechaIni debe ser DD/MM/AAAA (según manual).',
            'movFechaFin.regex' => 'fechaFin debe ser DD/MM/AAAA (según manual).',
        ]);

        $payload = [
            'cuenta' => $validated['movCuenta'],
            'fechaIni' => $validated['movFechaIni'],
            'fechaFin' => $validated['movFechaFin'],
            'tipoMoneda' => $validated['movTipoMoneda'],
            'nroMovimiento' => $validated['movNroMovimiento'] ?? '',
        ];

        $path = $bank->path('consulta_movimientos');

        $this->dispatchBdvCall(
            fn () => $bank->postJson($path, $payload, $validated['environment'], 'suite'),
            'Consulta de movimientos',
        );
    }

    public function runOutMovement(BdvBankApiClient $bank): void
    {
        $this->lastResult = null;

        $validated = $this->validate([
            'environment' => ['required', 'string', Rule::in(['qa', 'production'])],
            'outCedulaPagador' => ['required', 'string', 'max:32'],
            'outTelefonoPagador' => ['required', 'string', 'max:32'],
            'outTelefonoDestino' => ['required', 'string', 'max:32'],
            'outReferencia' => ['required', 'string', 'max:64'],
            'outFechaPago' => ['required', 'string', 'date_format:Y-m-d'],
            'outImporte' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'outBancoOrigen' => ['required', 'string', 'max:16'],
            'outBancoDestino' => ['required', 'string', 'max:16'],
        ]);

        $payload = [
            'cedulaPagador' => $validated['outCedulaPagador'],
            'telefonoPagador' => $validated['outTelefonoPagador'],
            'telefonoDestino' => $validated['outTelefonoDestino'],
            'referencia' => $validated['outReferencia'],
            'fechaPago' => $validated['outFechaPago'],
            'importe' => $validated['outImporte'],
            'bancoOrigen' => $validated['outBancoOrigen'],
            'bancoDestino' => $validated['outBancoDestino'],
        ];

        $path = $bank->path('get_out_movement');

        $this->dispatchBdvCall(
            fn () => $bank->postJson($path, $payload, $validated['environment'], 'suite'),
            'Consulta operaciones salientes',
        );
    }

    public function runVuelto(BdvBankApiClient $bank): void
    {
        $this->lastResult = null;

        $validated = $this->validate([
            'environment' => ['required', 'string', Rule::in(['qa', 'production'])],
            'vueltoNumeroReferencia' => ['required', 'string', 'max:32'],
            'vueltoMonto' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'vueltoNacionalidadDestino' => ['required', 'string', 'max:4'],
            'vueltoCedulaDestino' => ['required', 'string', 'max:32'],
            'vueltoTelefonoDestino' => ['required', 'string', 'max:32'],
            'vueltoBancoDestino' => ['required', 'string', 'max:16'],
            'vueltoMoneda' => ['required', 'string', 'max:8'],
            'vueltoConcepto' => ['required', 'string', 'max:255'],
        ]);

        $payload = [
            'numeroReferencia' => $validated['vueltoNumeroReferencia'],
            'montoOperacion' => $validated['vueltoMonto'],
            'nacionalidadDestino' => $validated['vueltoNacionalidadDestino'],
            'cedulaDestino' => $validated['vueltoCedulaDestino'],
            'telefonoDestino' => $validated['vueltoTelefonoDestino'],
            'bancoDestino' => $validated['vueltoBancoDestino'],
            'moneda' => $validated['vueltoMoneda'],
            'conceptoPago' => $validated['vueltoConcepto'],
        ];

        $path = $bank->path('vuelto');

        $this->dispatchBdvCall(
            fn () => $bank->postJson($path, $payload, $validated['environment'], 'suite'),
            'Vuelto (Pago Móvil)',
        );
    }

    public function runC2pPaymentKey(BdvBankApiClient $bank): void
    {
        $this->lastResult = null;

        $validated = $this->validate([
            'environment' => ['required', 'string', Rule::in(['qa', 'production'])],
            'c2pCustomerDocumentId' => ['required', 'string', 'max:32'],
        ]);

        $payload = ['customerDocumentId' => $validated['c2pCustomerDocumentId']];
        $path = $bank->path('c2p_payment_key');

        $this->dispatchBdvCall(
            fn () => $bank->postJson($path, $payload, $validated['environment'], 'suite'),
            'C2P — Generar clave (OTP)',
        );
    }

    public function runC2pProcess(BdvBankApiClient $bank): void
    {
        $this->lastResult = null;

        $validated = $this->validate([
            'environment' => ['required', 'string', Rule::in(['qa', 'production'])],
            'c2pCustomerDocumentId' => ['required', 'string', 'max:32'],
            'c2pCustomerNumberInstrument' => ['required', 'string', 'max:32'],
            'c2pAmount' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'c2pCustomerBankCode' => ['required', 'string', 'max:16'],
            'c2pConcept' => ['required', 'string', 'max:128'],
            'c2pOtp' => ['required', 'string', 'max:32'],
            'c2pCoinType' => ['required', 'string', 'max:8'],
            'c2pOperationType' => ['required', 'string', 'max:16'],
            'c2pCommerceNumberInstrument' => ['required', 'string', 'max:32'],
        ]);

        $payload = [
            'customerDocumentId' => $validated['c2pCustomerDocumentId'],
            'customerNumberInstrument' => $validated['c2pCustomerNumberInstrument'],
            'amount' => $validated['c2pAmount'],
            'customerBankCode' => $validated['c2pCustomerBankCode'],
            'concept' => $validated['c2pConcept'],
            'otp' => $validated['c2pOtp'],
            'coinType' => $validated['c2pCoinType'],
            'operationType' => $validated['c2pOperationType'],
            'commerceNumberInstrument' => $validated['c2pCommerceNumberInstrument'],
        ];

        $path = $bank->path('c2p_process');

        $this->dispatchBdvCall(
            fn () => $bank->postJson($path, $payload, $validated['environment'], 'suite'),
            'C2P — Procesar cobro',
        );
    }

    public function runC2pAnnulment(BdvBankApiClient $bank): void
    {
        $this->lastResult = null;

        $validated = $this->validate([
            'environment' => ['required', 'string', Rule::in(['qa', 'production'])],
            'c2pEndToEndId' => ['required', 'string', 'max:128'],
            'c2pReferenceOrigin' => ['nullable', 'string', 'max:128'],
        ]);

        $ref = $validated['c2pReferenceOrigin'] ?? '';
        $payload = [
            'endToEndId' => $validated['c2pEndToEndId'],
            'referenceOrigin' => $ref === '' ? null : $ref,
        ];

        $path = $bank->path('c2p_annulment');

        $this->dispatchBdvCall(
            fn () => $bank->postJson($path, $payload, $validated['environment'], 'suite'),
            'C2P — Anulación',
        );
    }

    public function runLotePagomovil(BdvBankApiClient $bank): void
    {
        $this->lastResult = null;

        $validated = $this->validate([
            'environment' => ['required', 'string', Rule::in(['qa', 'production'])],
            'loteDate' => ['required', 'string', 'date_format:Y-m-d'],
            'loteTimeStart' => ['required', 'string', 'date_format:H:i:s'],
            'loteTimeEnd' => ['required', 'string', 'date_format:H:i:s'],
            'loteNumeroComercio' => ['nullable', 'string', 'max:32'],
        ], [
            'loteTimeStart.date_format' => 'Hora inicio: formato HH:mm:ss.',
            'loteTimeEnd.date_format' => 'Hora fin: formato HH:mm:ss.',
        ]);

        $payload = [
            'timeStart' => $validated['loteTimeStart'],
            'timeEnd' => $validated['loteTimeEnd'],
            'date' => $validated['loteDate'],
        ];
        if (filled($validated['loteNumeroComercio'] ?? null)) {
            $payload['numeroComercio'] = $validated['loteNumeroComercio'];
        }

        $path = $bank->path('pagomovil_lote');

        $this->dispatchBdvCall(
            fn () => $bank->postJson($path, $payload, $validated['environment'], 'lote'),
            'Consulta Pagomóvil por lote (≤ 15 min)',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getManualSamplePayload(): array
    {
        return GetMovementRequest::movementPayloadFromValidated([
            'cedulaPagador' => 'V27037606',
            'telefonoPagador' => '04127141363',
            'telefonoDestino' => '04127141363',
            'referencia' => '12345678',
            'fechaPago' => '2023-02-12',
            'importe' => '120.00',
            'bancoOrigen' => '0102',
            'reqCed' => false,
        ]);
    }

    /**
     * Ejecuta el closure HTTP, captura respuesta o muestra notificación de error coherente.
     *
     * @param  callable(): Response  $request
     */
    private function dispatchBdvCall(callable $request, string $operationLabel): void
    {
        try {
            $response = $request();
        } catch (InvalidArgumentException $e) {
            $this->recordExceptionPanelResult($operationLabel, 'Solicitud no válida', $e->getMessage());
            Notification::make()
                ->title('Solicitud no válida')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (RuntimeException $e) {
            $this->recordExceptionPanelResult($operationLabel, 'No se puede llamar al banco', $e->getMessage());
            Notification::make()
                ->title('No se puede llamar al banco')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (ConnectionException $e) {
            $this->recordExceptionPanelResult($operationLabel, 'Error de conexión', 'No se alcanzó el servicio BDV: '.$e->getMessage());
            Notification::make()
                ->title('Error de conexión')
                ->body('No se alcanzó el servicio BDV: '.$e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (Throwable $e) {
            $this->recordExceptionPanelResult($operationLabel, 'Error inesperado', $e->getMessage());
            Notification::make()
                ->title('Error inesperado')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->captureResult($response, $operationLabel);

        $ok = ($this->lastResult['outcome'] ?? 'error') === 'success';

        $notification = Notification::make()
            ->title($ok ? 'Respuesta satisfactoria' : 'Respuesta con incidencia')
            ->body($ok
                ? 'Operación «'.$operationLabel.'»: revisa los códigos resaltados abajo.'
                : 'Operación «'.$operationLabel.'»: revisa el cuerpo y los códigos en rojo.');

        if ($ok) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->send();
    }

    private function captureResult(Response $response, string $operationLabel): void
    {
        $decoded = $response->json();
        $decodedArray = is_array($decoded) ? $decoded : null;
        $analysis = self::analyzeBdvResponse($response, $decodedArray);

        $this->lastResult = [
            'operation' => $operationLabel,
            'upstream_http_status' => $response->status(),
            'upstream_successful' => $response->successful(),
            'outcome' => $analysis['outcome'],
            'highlight_codes' => $analysis['codes'],
            'body' => $decodedArray !== null ? $decodedArray : $response->body(),
            'body_is_json' => $decodedArray !== null,
            'error_kind' => null,
            'error_message' => null,
        ];
    }

    /**
     * Guarda en el panel un resultado de error local (validación HTTP cliente, conexión, etc.) para pintarlo en rojo.
     */
    private function recordExceptionPanelResult(string $operationLabel, string $errorKind, string $errorMessage): void
    {
        $this->lastResult = [
            'operation' => $operationLabel,
            'upstream_http_status' => null,
            'upstream_successful' => false,
            'outcome' => 'error',
            'highlight_codes' => [
                ['key' => 'excepción', 'value' => $errorKind],
            ],
            'body' => $errorMessage,
            'body_is_json' => false,
            'error_kind' => $errorKind,
            'error_message' => $errorMessage,
        ];
    }

    /**
     * Decide si la respuesta BDV es «satisfactoria» para el semáforo verde/rojo del playground.
     * Cubre varias formas de JSON según manuales (code numérico o string, codigo, errores tipo Spring).
     *
     * @param  array<string, mixed>|null  $decoded
     * @return array{outcome: 'success'|'error', codes: list<array{key: string, value: string}>}
     */
    private static function analyzeBdvResponse(Response $response, ?array $decoded): array
    {
        $httpOk = $response->successful();
        $codes = self::extractBdvHighlightCodes($decoded);

        if (! $httpOk) {
            return ['outcome' => 'error', 'codes' => $codes];
        }

        if (! is_array($decoded)) {
            return ['outcome' => 'success', 'codes' => $codes];
        }

        if (self::isSpringStyleHttpError($decoded)) {
            return ['outcome' => 'error', 'codes' => $codes];
        }

        $businessOk = self::isBdvBusinessSuccessBody($decoded);

        return [
            'outcome' => $businessOk ? 'success' : 'error',
            'codes' => $codes,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $decoded
     * @return list<array{key: string, value: string}>
     */
    private static function extractBdvHighlightCodes(?array $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach (['code', 'codigo', 'status', 'error', 'message', 'total', 'totalRegistros'] as $key) {
            if (! array_key_exists($key, $decoded)) {
                continue;
            }
            $val = $decoded[$key];
            if ($val === null || $val === '') {
                continue;
            }
            if (is_scalar($val)) {
                $out[] = ['key' => $key, 'value' => (string) $val];

                continue;
            }
            if (is_array($val)) {
                $out[] = ['key' => $key, 'value' => json_encode($val, JSON_UNESCAPED_UNICODE)];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private static function isSpringStyleHttpError(array $decoded): bool
    {
        if (isset($decoded['status']) && is_numeric($decoded['status']) && (int) $decoded['status'] >= 400) {
            return true;
        }

        return isset($decoded['error'], $decoded['path'])
            && is_string($decoded['error'])
            && $decoded['error'] !== '';
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private static function isBdvBusinessSuccessBody(array $decoded): bool
    {
        if (array_key_exists('codigo', $decoded)) {
            $co = (string) $decoded['codigo'];

            return in_array($co, ['00', '01'], true);
        }

        if (! array_key_exists('code', $decoded)) {
            return true;
        }

        $c = $decoded['code'];

        $asInt = is_int($c) ? $c : (is_numeric($c) ? (int) $c : null);

        $successValues = [1000, 200];
        if (in_array($c, ['1000', '200', '00'], true)) {
            return true;
        }
        if ($asInt !== null && in_array($asInt, $successValues, true)) {
            return true;
        }

        $failValues = [1010, 1001, 401, 99];
        if (in_array($c, ['1010', '1001', '401', '99'], true)) {
            return false;
        }
        if ($asInt !== null && in_array($asInt, $failValues, true)) {
            return false;
        }

        if ($asInt !== null && $asInt >= 400) {
            return false;
        }

        return true;
    }
}
