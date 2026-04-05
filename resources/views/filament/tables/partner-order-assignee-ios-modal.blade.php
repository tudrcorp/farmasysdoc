@php
    /** @var array{isCompleted: bool, deliveredAtLabel: ?string, inProgress: bool, hasLinkedUser: bool, name: ?string, email: ?string, photoUrl: ?string, assigneeLabel: ?string, identityDocument: ?string, mobilePhone: ?string} $payload */
    $payload = $payload ?? [
        'isCompleted' => false,
        'deliveredAtLabel' => null,
        'inProgress' => false,
        'hasLinkedUser' => false,
        'name' => null,
        'email' => null,
        'photoUrl' => null,
        'assigneeLabel' => null,
        'identityDocument' => null,
        'mobilePhone' => null,
    ];
@endphp

<div class="fi-ios-partner-order-assignee-inner space-y-6">
    @if ($payload['isCompleted'] ?? false)
        <div class="fi-ios-delivery-order__empty rounded-2xl border border-emerald-200/90 bg-emerald-50/90 p-4 dark:border-emerald-500/25 dark:bg-emerald-500/10">
            <p class="fi-ios-delivery-order__empty-title text-sm font-semibold text-emerald-950 dark:text-emerald-100">
                Pedido finalizado
            </p>
            <p class="fi-ios-delivery-order__empty-sub mt-1 text-sm text-emerald-900/90 dark:text-emerald-100/85">
                Este pedido ya fue entregado y su estado en Farmadoc es
                <span class="font-medium text-emerald-950 dark:text-emerald-50">«Finalizado»</span>.
                Gracias por confiar en nuestro servicio de delivery.
            </p>
            @if (filled($payload['deliveredAtLabel'] ?? null))
                <p class="mt-4 rounded-xl border border-emerald-200/70 bg-white/80 px-3 py-2 text-xs font-medium text-emerald-900 dark:border-emerald-500/20 dark:bg-zinc-900/40 dark:text-emerald-100/90">
                    Fecha de entrega registrada:
                    <span class="font-semibold text-emerald-950 dark:text-white">{{ $payload['deliveredAtLabel'] }}</span>
                </p>
            @endif
        </div>
    @elseif (! $payload['inProgress'])
        <div class="fi-ios-delivery-order__empty rounded-2xl border border-zinc-200/80 bg-zinc-50/80 p-4 dark:border-white/10 dark:bg-white/5">
            <p class="fi-ios-delivery-order__empty-title text-sm font-semibold text-zinc-900 dark:text-white">
                Pedido aún no en proceso
            </p>
            <p class="fi-ios-delivery-order__empty-sub mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                La información y la foto del repartidor estarán disponibles cuando Farmadoc marque el pedido como
                <span class="font-medium text-zinc-800 dark:text-zinc-200">«En proceso»</span>
                (repartidor en ruta).
            </p>
        </div>
    @elseif (! $payload['hasLinkedUser'])
        <div class="fi-ios-delivery-order__empty rounded-2xl border border-amber-200/90 bg-amber-50/90 p-4 dark:border-amber-500/25 dark:bg-amber-500/10">
            <p class="text-sm font-semibold text-amber-950 dark:text-amber-100">
                Repartidor aún no confirmado en sistema
            </p>
            <p class="mt-1 text-sm text-amber-900/90 dark:text-amber-100/85">
                El pedido está en proceso, pero aún no hay un usuario de entrega vinculado. Si aparece un nombre en el
                pedido, puede usarlo como referencia provisional.
            </p>
            @if (filled($payload['assigneeLabel']))
                <div class="mt-4 rounded-xl border border-amber-200/70 bg-white/80 p-3 dark:border-amber-500/20 dark:bg-zinc-900/40">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-800/80 dark:text-amber-200/80">
                        Texto en el pedido
                    </p>
                    <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">
                        {{ $payload['assigneeLabel'] }}
                    </p>
                </div>
            @endif
        </div>
    @else
        <div class="flex flex-col items-center text-center pb-1">
            @if (filled($payload['photoUrl']))
                <div class="relative overflow-hidden rounded-3xl ring-1 ring-zinc-200/90 dark:ring-white/15">
                    <img
                        src="{{ $payload['photoUrl'] }}"
                        alt="Foto del repartidor"
                        class="mx-auto max-h-52 w-auto max-w-full object-cover object-center"
                        loading="lazy"
                    />
                </div>
            @else
                <div
                    class="flex h-28 w-28 items-center justify-center rounded-full bg-zinc-200/80 text-2xl font-semibold text-zinc-600 dark:bg-white/10 dark:text-zinc-300"
                    aria-hidden="true"
                >
                    {{ $payload['name'] !== null && $payload['name'] !== '' ? mb_strtoupper(mb_substr($payload['name'], 0, 1)) : '?' }}
                </div>
                <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Sin foto registrada para este repartidor</p>
            @endif
        </div>

        <div class="fi-ios-delivery-order__block mt-10 border-t border-zinc-200/70 pt-8 dark:border-white/10">
            <p class="fi-ios-delivery-order__block-title">Datos del repartidor</p>
            <div class="fi-ios-delivery-order__card">
                <div class="fi-ios-delivery-order__item">
                    <span class="fi-ios-delivery-order__label">Nombre</span>
                    <span class="fi-ios-delivery-order__value">{{ filled($payload['name']) ? $payload['name'] : '—' }}</span>
                </div>
                <div class="fi-ios-delivery-order__hairline" aria-hidden="true"></div>
                <div class="fi-ios-delivery-order__item">
                    <span class="fi-ios-delivery-order__label">Cédula de identidad</span>
                    <span class="fi-ios-delivery-order__value">{{ filled($payload['identityDocument'] ?? null) ? $payload['identityDocument'] : '—' }}</span>
                </div>
                <div class="fi-ios-delivery-order__hairline" aria-hidden="true"></div>
                <div class="fi-ios-delivery-order__item">
                    <span class="fi-ios-delivery-order__label">Correo</span>
                    <span class="fi-ios-delivery-order__value break-all">{{ filled($payload['email']) ? $payload['email'] : '—' }}</span>
                </div>
                <div class="fi-ios-delivery-order__hairline" aria-hidden="true"></div>
                <div class="fi-ios-delivery-order__item">
                    <span class="fi-ios-delivery-order__label">Teléfono móvil</span>
                    <span class="fi-ios-delivery-order__value">{{ filled($payload['mobilePhone'] ?? null) ? $payload['mobilePhone'] : '—' }}</span>
                </div>
                @if (filled($payload['assigneeLabel']))
                    <div class="fi-ios-delivery-order__hairline" aria-hidden="true"></div>
                    <div class="fi-ios-delivery-order__item">
                        <span class="fi-ios-delivery-order__label">Referencia en pedido</span>
                        <span class="fi-ios-delivery-order__value">{{ $payload['assigneeLabel'] }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
