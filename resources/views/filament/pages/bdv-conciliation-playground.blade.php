<x-filament-panels::page>
    <div class="fi-bdv-playground space-y-8">
        <div class="grid gap-8 lg:grid-cols-2">
            <x-filament::section
                heading="¿Qué es esto?"
                description="Herramienta solo para administradores: prueba la conciliación de Pagomóvil del Banco de Venezuela sin salir del panel."
            >
                <div class="prose prose-sm dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                    <p>
                        El <strong>API de Conciliación BDV</strong> (manual MDU-006) permite verificar si un pago móvil quedó registrado:
                        envías datos del pagador, referencia, monto, fecha y banco de origen; el banco responde si el movimiento existe o el motivo del rechazo.
                    </p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li><strong>Código 1000</strong> en la respuesta del banco = movimiento validado (éxito de negocio).</li>
                        <li>Cualquier otro <code class="text-xs">code</code> = error de negocio (ej. registro no existe, datos nulos).</li>
                        <li><strong>Calidad (qa):</strong> servidor de pruebas del manual; puedes usar los datos de ejemplo.</li>
                        <li><strong>Producción:</strong> requiere <code class="text-xs">BDV_CONCILIATION_API_KEY</code> en el archivo <code class="text-xs">.env</code> (clave de BDVenLínea Empresa).</li>
                    </ul>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Formato fecha: <code>AAAA-MM-DD</code> (sin barras). Importe con punto decimal (ej. <code>120.00</code>), sin comas.
                    </p>
                </div>
            </x-filament::section>

            <x-filament::section
                heading="Formulario de consulta"
                description="Los nombres de campo coinciden con el JSON del manual del banco."
            >
                <form wire:submit.prevent="conciliar" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1 sm:col-span-2">
                            <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">Entorno</span>
                            </label>
                            <select
                                wire:model="environment"
                                class="fi-select-input block w-full rounded-lg border border-gray-950/10 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/5 focus:ring-2 focus:ring-primary-600 dark:border-white/10 dark:bg-white/5 dark:text-white dark:ring-white/10"
                            >
                                <option value="qa">Calidad (pruebas — manual)</option>
                                <option value="production">Producción (requiere API key en .env)</option>
                            </select>
                            @error('environment')
                                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-950 dark:text-white">Cédula pagador</label>
                            <input type="text" wire:model="cedulaPagador" class="fi-input block w-full rounded-lg border border-gray-950/10 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                            @error('cedulaPagador') <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-950 dark:text-white">Teléfono pagador</label>
                            <input type="text" wire:model="telefonoPagador" class="fi-input block w-full rounded-lg border border-gray-950/10 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                            @error('telefonoPagador') <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-950 dark:text-white">Teléfono destino (comercio)</label>
                            <input type="text" wire:model="telefonoDestino" class="fi-input block w-full rounded-lg border border-gray-950/10 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                            @error('telefonoDestino') <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-950 dark:text-white">Referencia</label>
                            <input type="text" wire:model="referencia" class="fi-input block w-full rounded-lg border border-gray-950/10 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                            @error('referencia') <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-950 dark:text-white">Fecha de pago</label>
                            <input type="date" wire:model="fechaPago" class="fi-input block w-full rounded-lg border border-gray-950/10 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                            @error('fechaPago') <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-950 dark:text-white">Importe</label>
                            <input type="text" wire:model="importe" placeholder="120.00" class="fi-input block w-full rounded-lg border border-gray-950/10 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                            @error('importe') <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-950 dark:text-white">Banco origen</label>
                            <input type="text" wire:model="bancoOrigen" placeholder="0102" class="fi-input block w-full rounded-lg border border-gray-950/10 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                            @error('bancoOrigen') <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2">
                        <x-filament::button type="submit" color="primary" wire:loading.attr="disabled" wire:target="conciliar">
                            <span wire:loading.remove wire:target="conciliar">Consultar en BDV</span>
                            <span wire:loading wire:target="conciliar">Consultando…</span>
                        </x-filament::button>

                        <x-filament::button type="button" color="gray" outlined wire:click="loadSampleFromManual" wire:loading.attr="disabled" wire:target="loadSampleFromManual,conciliar">
                            Cargar datos de prueba del manual
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        </div>

        @if ($environment === 'production')
            <x-filament::section>
                <x-slot name="heading">
                    <span class="text-amber-700 dark:text-amber-300">Producción</span>
                </x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Asegúrate de tener configurada la variable <code class="rounded bg-gray-100 px-1 font-mono text-xs dark:bg-white/10">BDV_CONCILIATION_API_KEY</code> en el servidor.
                    Las consultas reales afectan la operación con el banco.
                </p>
            </x-filament::section>
        @endif

        <x-filament::section
            heading="Respuesta del banco"
            description="HTTP devuelto por el servicio BDV y cuerpo (JSON o texto)."
            :collapsed="($lastResult === null)"
            :collapsible="true"
        >
            @if ($lastResult === null)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Aún no hay respuesta. Completa el formulario y pulsa «Consultar en BDV».
                </p>
            @else
                <dl class="grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">HTTP upstream</dt>
                        <dd class="font-mono text-gray-950 dark:text-white">{{ $lastResult['upstream_http_status'] }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">¿2xx?</dt>
                        <dd class="font-mono text-gray-950 dark:text-white">{{ $lastResult['upstream_successful'] ? 'sí' : 'no' }}</dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Cuerpo</p>
                    <pre class="max-h-[28rem] overflow-auto rounded-xl bg-gray-950 p-4 text-xs text-gray-100 dark:bg-black/40">{{ is_string($lastResult['body']) ? $lastResult['body'] : json_encode($lastResult['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Referencia rápida (manual)" collapsed collapsible>
            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p class="text-sm text-gray-600 dark:text-gray-300">Ejemplo de cuerpo JSON que envía la aplicación al banco (mismos campos que el formulario):</p>
                <pre class="rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{{ json_encode($this->getManualSamplePayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
