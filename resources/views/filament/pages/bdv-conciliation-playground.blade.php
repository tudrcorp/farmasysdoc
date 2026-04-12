<x-filament-panels::page>
    {{--
        Playground BDV: pestañas por servicio. Cada formulario envía JSON al banco según los manuales dummy QA.
        La pestaña «Notificación» solo documenta el webhook entrante (el banco llama a tu URL, no hay botón de prueba aquí).
    --}}
    <div class="fi-bdv-playground space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Uso del laboratorio
            </x-slot>
            <x-slot name="description">
                Solo administradores. Elige el entorno (calidad suele tener datos de ejemplo fijos). Cada API puede usar una API Key distinta: revisa <code class="text-xs">config/bdv_conciliation.php</code> y tu <code class="text-xs">.env</code>.
            </x-slot>
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-[12rem] flex-1 space-y-1">
                    <label class="text-sm font-medium text-gray-950 dark:text-white">Entorno global</label>
                    <select
                        wire:model.live="environment"
                        class="fi-select-input block w-full rounded-lg border border-gray-950/10 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-white/10"
                    >
                        <option value="qa">Calidad (QA)</option>
                        <option value="production">Producción</option>
                    </select>
                    @error('environment')
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>
                <x-filament::button type="button" color="gray" outlined wire:click="loadSamplesFromManuals">
                    Cargar ejemplos de los manuales
                </x-filament::button>
            </div>
            @if ($environment === 'production')
                <p class="mt-3 text-sm text-amber-800 dark:text-amber-200">
                    Producción: configura <code class="rounded bg-amber-100 px-1 text-xs dark:bg-amber-900/40">BDV_PRODUCTION_KEY_*</code> / <code class="text-xs">BDV_CONCILIATION_API_KEY</code> según el servicio. Las llamadas son reales.
                </p>
            @endif
        </x-filament::section>

        <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-2 dark:border-white/10">
            @foreach ([
                'conciliation' => 'Conciliación PM',
                'multiple' => 'Conciliación múltiple',
                'balance' => 'Saldo',
                'movements' => 'Movimientos',
                'outgoing' => 'Salientes',
                'vuelto' => 'Vuelto',
                'c2p' => 'C2P',
                'lote' => 'PM por lote',
                'webhook' => 'Notificación',
            ] as $key => $label)
                <button
                    type="button"
                    wire:click="setTab('{{ $key }}')"
                    @class([
                        'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                        'bg-primary-600 text-white shadow' => $activeTab === $key,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/15' => $activeTab !== $key,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($activeTab === 'conciliation')
            <x-filament::section
                heading="Conciliación Pagomóvil"
                description="POST getMovement/v2 — valida un pago recibido. Código 1000 = conciliado. reqCed: solo true en BDV→BDV según manual."
            >
                <form wire:submit.prevent="runConciliation" class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1 sm:col-span-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="reqCed" class="rounded border-gray-300 text-primary-600" />
                            <span>Validar cédula (reqCed) — solo pagos BDV a BDV</span>
                        </label>
                    </div>
                    @include('filament.pages.partials.bdv-input', ['name' => 'cedulaPagador', 'label' => 'Cédula pagador'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'telefonoPagador', 'label' => 'Teléfono pagador'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'telefonoDestino', 'label' => 'Teléfono destino (comercio)'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'referencia', 'label' => 'Referencia'])
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-950 dark:text-white">Fecha pago</label>
                        <input type="date" wire:model="fechaPago" class="fi-input block w-full rounded-lg border border-gray-950/10 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                        @error('fechaPago') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    @include('filament.pages.partials.bdv-input', ['name' => 'importe', 'label' => 'Importe', 'placeholder' => '120.00'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'bancoOrigen', 'label' => 'Banco origen', 'placeholder' => '0102'])
                    <div class="sm:col-span-2">
                        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runConciliation">
                            <span wire:loading.remove wire:target="runConciliation">Enviar conciliación</span>
                            <span wire:loading wire:target="runConciliation">Enviando…</span>
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif

        @if ($activeTab === 'multiple')
            <x-filament::section
                heading="Conciliación múltiple"
                description="Lista pagos del día por teléfono cliente y banco. Usa ranura de credencial «suite»."
            >
                <form wire:submit.prevent="runConsultaMultiple" class="grid gap-4 sm:grid-cols-2">
                    @include('filament.pages.partials.bdv-input', ['name' => 'cmFechaPago', 'label' => 'Fecha pago (AAAA-MM-DD)'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'cmBancoOrigen', 'label' => 'Banco origen'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'cmTelefonoCliente', 'label' => 'Teléfono cliente', 'class' => 'sm:col-span-2'])
                    <div class="sm:col-span-2">
                        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runConsultaMultiple">Consultar</x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif

        @if ($activeTab === 'balance')
            <x-filament::section heading="Consulta de saldo" description="Moneda + número de cuenta asociada al RIF en el banco.">
                <form wire:submit.prevent="runConsultaSaldo" class="grid gap-4 sm:grid-cols-2">
                    @include('filament.pages.partials.bdv-input', ['name' => 'saldoCurrency', 'label' => 'Moneda (ej. VES)'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'saldoAccount', 'label' => 'Número de cuenta'])
                    <div class="sm:col-span-2">
                        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runConsultaSaldo">Consultar saldo</x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif

        @if ($activeTab === 'movements')
            <x-filament::section
                heading="Consulta de movimientos"
                description="Fechas en DD/MM/AAAA. Paginación: si hay más de 100 movimientos el mismo día, reenvía nroMovimiento con el último nroMov recibido."
            >
                <form wire:submit.prevent="runConsultaMovimientos" class="grid gap-4 sm:grid-cols-2">
                    @include('filament.pages.partials.bdv-input', ['name' => 'movCuenta', 'label' => 'Cuenta', 'class' => 'sm:col-span-2'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'movFechaIni', 'label' => 'Fecha inicio (DD/MM/AAAA)'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'movFechaFin', 'label' => 'Fecha fin (DD/MM/AAAA)'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'movTipoMoneda', 'label' => 'Tipo moneda'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'movNroMovimiento', 'label' => 'Nº movimiento (paginación, opcional)'])
                    <div class="sm:col-span-2">
                        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runConsultaMovimientos">Consultar movimientos</x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif

        @if ($activeTab === 'outgoing')
            <x-filament::section heading="Operaciones salientes" description="Valida un Pagomóvil enviado por el comercio (getOutMovement/v2).">
                <form wire:submit.prevent="runOutMovement" class="grid gap-4 sm:grid-cols-2">
                    @include('filament.pages.partials.bdv-input', ['name' => 'outCedulaPagador', 'label' => 'Cédula/RIF pagador'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'outTelefonoPagador', 'label' => 'Teléfono pagador'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'outTelefonoDestino', 'label' => 'Teléfono destino'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'outReferencia', 'label' => 'Referencia'])
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-950 dark:text-white">Fecha pago</label>
                        <input type="date" wire:model="outFechaPago" class="fi-input block w-full rounded-lg border border-gray-950/10 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                        @error('outFechaPago') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    @include('filament.pages.partials.bdv-input', ['name' => 'outImporte', 'label' => 'Importe'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'outBancoOrigen', 'label' => 'Banco origen'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'outBancoDestino', 'label' => 'Banco destino'])
                    <div class="sm:col-span-2">
                        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runOutMovement">Consultar</x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif

        @if ($activeTab === 'vuelto')
            <x-filament::section heading="Vuelto" description="Devolución vía Pago Móvil. El comercio genera numeroReferencia único por operación.">
                <form wire:submit.prevent="runVuelto" class="grid gap-4 sm:grid-cols-2">
                    @include('filament.pages.partials.bdv-input', ['name' => 'vueltoNumeroReferencia', 'label' => 'Número referencia (generado por ti)'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'vueltoMonto', 'label' => 'Monto'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'vueltoNacionalidadDestino', 'label' => 'Nacionalidad destino'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'vueltoCedulaDestino', 'label' => 'Cédula destino'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'vueltoTelefonoDestino', 'label' => 'Teléfono destino'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'vueltoBancoDestino', 'label' => 'Banco destino'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'vueltoMoneda', 'label' => 'Moneda'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'vueltoConcepto', 'label' => 'Concepto', 'class' => 'sm:col-span-2'])
                    <div class="sm:col-span-2">
                        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runVuelto">Ejecutar vuelto</x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif

        @if ($activeTab === 'c2p')
            <div class="space-y-6">
                <x-filament::section heading="C2P — Solicitar OTP (paymentkey)" description="Paso 1: el pagador recibe la clave en su banco.">
                    <form wire:submit.prevent="runC2pPaymentKey" class="flex flex-wrap items-end gap-4">
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pCustomerDocumentId', 'label' => 'Documento cliente', 'class' => 'min-w-[14rem] flex-1'])
                        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runC2pPaymentKey">Solicitar OTP</x-filament::button>
                    </form>
                </x-filament::section>
                <x-filament::section heading="C2P — Procesar cobro" description="Paso 2: incluye OTP y teléfono comercio (cuenta múltiple).">
                    <form wire:submit.prevent="runC2pProcess" class="grid gap-4 sm:grid-cols-2">
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pCustomerDocumentId', 'label' => 'Documento cliente'])
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pCustomerNumberInstrument', 'label' => 'Teléfono cliente'])
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pAmount', 'label' => 'Monto'])
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pCustomerBankCode', 'label' => 'Código banco cliente'])
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pConcept', 'label' => 'Concepto'])
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pOtp', 'label' => 'OTP'])
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pCoinType', 'label' => 'Moneda'])
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pOperationType', 'label' => 'Tipo operación'])
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pCommerceNumberInstrument', 'label' => 'Teléfono comercio', 'class' => 'sm:col-span-2'])
                        <div class="sm:col-span-2">
                            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runC2pProcess">Procesar cobro</x-filament::button>
                        </div>
                    </form>
                </x-filament::section>
                <x-filament::section heading="C2P — Anulación" description="Usa endToEndId devuelto en el cobro. referenceOrigin puede ir vacío.">
                    <form wire:submit.prevent="runC2pAnnulment" class="grid gap-4 sm:grid-cols-2">
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pEndToEndId', 'label' => 'endToEndId', 'class' => 'sm:col-span-2'])
                        @include('filament.pages.partials.bdv-input', ['name' => 'c2pReferenceOrigin', 'label' => 'referenceOrigin (opcional)', 'class' => 'sm:col-span-2'])
                        <div class="sm:col-span-2">
                            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runC2pAnnulment">Anular</x-filament::button>
                        </div>
                    </form>
                </x-filament::section>
            </div>
        @endif

        @if ($activeTab === 'lote')
            <x-filament::section
                heading="Consulta Pagomóvil por lote"
                description="Ventana máxima 15 minutos. Usa ranura «lote» (API Key distinta en QA). Fecha AAAA-MM-DD; horas HH:mm:ss. numeroComercio opcional."
            >
                <form wire:submit.prevent="runLotePagomovil" class="grid gap-4 sm:grid-cols-2">
                    @include('filament.pages.partials.bdv-input', ['name' => 'loteDate', 'label' => 'Fecha'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'loteTimeStart', 'label' => 'Hora inicio'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'loteTimeEnd', 'label' => 'Hora fin'])
                    @include('filament.pages.partials.bdv-input', ['name' => 'loteNumeroComercio', 'label' => 'Número comercio (opcional)'])
                    <div class="sm:col-span-2">
                        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="runLotePagomovil">Consultar lote</x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        @endif

        @if ($activeTab === 'webhook')
            <x-filament::section
                heading="Notificación de pago (webhook entrante)"
                description="No es una llamada que hagas tú al banco: BDV envía un POST a la URL HTTPS que registres, con el pago recibido."
            >
                <div class="prose prose-sm dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                    <p>Tu servidor debe responder siempre con JSON y uno de los códigos aceptados (<code>codigo</code> 00, 01 o 99) según el manual.</p>
                    <p class="text-xs text-gray-500">Ejemplo de cuerpo que <strong>recibirías</strong> (simplificado): bancoOrdenante, referenciaBancoOrdenante, idCliente, numeroCliente, idComercio, numeroComercio, fecha, hora, monto.</p>
                    <p class="text-xs text-gray-500">Para probar en QA suele hacer falta que el banco apunte el webhook a una URL pública (túnel ngrok, staging, etc.) y valides firma/seguridad según te indiquen en contrato.</p>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section
            heading="Respuesta del banco"
            description="Última operación ejecutada desde esta página."
            :collapsed="($lastResult === null)"
            :collapsible="true"
        >
            @if ($lastResult === null)
                <p class="text-sm text-gray-500 dark:text-gray-400">Ejecuta una pestaña para ver HTTP y JSON aquí.</p>
            @else
                @php
                    $bdvOutcomeOk = ($lastResult['outcome'] ?? 'error') === 'success';
                @endphp
                <div
                    @class([
                        'mb-4 rounded-xl border-2 p-4',
                        'border-emerald-500/70 bg-emerald-50 dark:border-emerald-500/50 dark:bg-emerald-950/30' => $bdvOutcomeOk,
                        'border-red-500/70 bg-red-50 dark:border-red-500/50 dark:bg-red-950/30' => ! $bdvOutcomeOk,
                    ])
                >
                    <p class="text-sm font-semibold {{ $bdvOutcomeOk ? 'text-emerald-900 dark:text-emerald-100' : 'text-red-900 dark:text-red-100' }}">
                        {{ $bdvOutcomeOk ? 'Resultado: satisfactorio' : 'Resultado: error o incidencia' }}
                    </p>
                    @if (! empty($lastResult['highlight_codes']))
                        <p class="mt-2 text-xs font-medium uppercase tracking-wide opacity-80 {{ $bdvOutcomeOk ? 'text-emerald-800 dark:text-emerald-200' : 'text-red-800 dark:text-red-200' }}">
                            Códigos / campos clave
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($lastResult['highlight_codes'] as $row)
                                <span
                                    @class([
                                        'inline-flex max-w-full items-center rounded-lg border px-2.5 py-1 font-mono text-xs font-bold tabular-nums shadow-sm',
                                        'border-emerald-600/40 bg-emerald-100 text-emerald-950 dark:border-emerald-400/30 dark:bg-emerald-900/40 dark:text-emerald-50' => $bdvOutcomeOk,
                                        'border-red-600/40 bg-red-100 text-red-950 dark:border-red-400/30 dark:bg-red-900/40 dark:text-red-50' => ! $bdvOutcomeOk,
                                    ])
                                >
                                    <span class="mr-1 font-semibold opacity-70">{{ $row['key'] }}</span>
                                    <span class="break-all">{{ $row['value'] }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Operación</dt>
                        <dd class="text-gray-950 dark:text-white">{{ $lastResult['operation'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">HTTP upstream</dt>
                        <dd>
                            @if ($lastResult['upstream_http_status'] !== null)
                                <span
                                    @class([
                                        'inline-block rounded-md px-2 py-0.5 font-mono text-sm font-bold tabular-nums',
                                        ($lastResult['upstream_successful'] ?? false)
                                            ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/50 dark:text-emerald-100'
                                            : 'bg-red-100 text-red-900 dark:bg-red-900/50 dark:text-red-100',
                                    ])
                                >
                                    {{ $lastResult['upstream_http_status'] }}
                                </span>
                            @else
                                <span class="font-mono text-sm text-gray-500 dark:text-gray-400">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">¿Respuesta HTTP 2xx?</dt>
                        <dd
                            @class([
                                'font-semibold',
                                ($lastResult['upstream_successful'] ?? false) ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300',
                            ])
                        >
                            {{ ($lastResult['upstream_successful'] ?? false) ? 'Sí' : 'No' }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Cuerpo</p>
                    <pre
                        @class([
                            'max-h-[28rem] overflow-auto rounded-xl p-4 text-xs',
                            $bdvOutcomeOk
                                ? 'border border-emerald-800/20 bg-gray-950 text-gray-100 dark:border-emerald-500/20'
                                : 'border border-red-800/30 bg-gray-950 text-gray-100 dark:border-red-500/25',
                        ])
                    >{{ is_string($lastResult['body']) ? $lastResult['body'] : json_encode($lastResult['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Referencia JSON — conciliación simple" collapsed collapsible>
            <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">Ejemplo de payload (incluye reqCed):</p>
            <pre class="rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($this->getManualSamplePayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </x-filament::section>
    </div>
</x-filament-panels::page>
