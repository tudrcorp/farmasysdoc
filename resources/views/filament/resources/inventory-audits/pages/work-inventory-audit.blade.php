<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>

@script
<script>
    try {
        if (typeof $wire.$interceptRequest !== 'function') {
            console.warn('[auditoria] interceptRequest no está disponible en este Livewire')
        } else {
            $wire.$interceptRequest(({ request, onResponse, onError, onFailure, onSuccess }) => {
                const startedAt = performance.now()
                const requestId = String(request.id ?? Date.now())
                console.info('[auditoria] livewire start', { requestId })

                const notify = (title, body) => {
                    try {
                        if (typeof FilamentNotification === 'function') {
                            new FilamentNotification().title(title).body(body).danger().seconds(12).send()
                        }
                    } catch (e) {
                        console.warn('[auditoria] no se pudo mostrar notificación', e)
                    }
                }

                const timer = window.setTimeout(() => {
                    const ms = Math.round(performance.now() - startedAt)
                    console.warn('[auditoria] sin respuesta a los 12s', { requestId, ms })
                    notify(
                        'La acción no responde',
                        'Lleva más de 12s. Revisa storage/logs/inventory-audit.log y esta consola (F12).',
                    )
                }, 12000)

                const finish = (kind, extra = {}) => {
                    window.clearTimeout(timer)
                    console.info('[auditoria] livewire ' + kind, {
                        requestId,
                        ms: Math.round(performance.now() - startedAt),
                        ...extra,
                    })
                }

                onResponse(({ response }) => finish('response', { status: response?.status }))
                onSuccess(() => finish('ok'))
                onError(({ response, body }) => {
                    finish('error', { status: response?.status, body: String(body ?? '').slice(0, 300) })
                    notify(
                        'Error de Livewire',
                        'HTTP ' + String(response?.status ?? '?') + '. Revisa storage/logs/inventory-audit.log',
                    )
                })
                onFailure(({ error }) => {
                    finish('failure', { message: String(error ?? '') })
                    notify(
                        'La petición de Livewire falló',
                        String(error ?? 'Sin detalle. Revisa F12 y storage/logs/inventory-audit.log'),
                    )
                })
            })
        }
    } catch (e) {
        console.error('[auditoria] no se pudo instalar el interceptor', e)
    }
</script>
@endscript
