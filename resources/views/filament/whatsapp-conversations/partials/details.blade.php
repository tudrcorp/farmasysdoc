@php
    use App\Enums\PosHeldSaleStatus;

    /** @var \App\Models\WhatsappConversation $conversation */
    $contact = $conversation->contact;
    $card = 'rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10';
    $cardTitle = 'mb-3 flex items-center justify-between gap-2';
    $titleText = 'flex items-center gap-1.5 text-[13px] font-semibold text-gray-900 dark:text-white';
    $label = 'text-[11.5px] text-gray-500 dark:text-gray-400';
    $value = 'text-[13.5px] text-gray-900 dark:text-gray-100';
@endphp

<div class="flex min-h-full flex-col gap-3">
    {{-- Encabezado del cliente --}}
    <div class="flex flex-col items-center gap-1 px-2 pt-2 pb-1 text-center">
        <div class="text-base font-semibold text-gray-900 dark:text-white">{{ $contact?->displayName() }}</div>
        <a href="https://wa.me/{{ $contact?->phone }}" target="_blank" rel="noopener" class="text-sm text-gray-500 hover:underline dark:text-gray-400">{{ $contact?->localPhone() }}</a>
        <div class="mt-1 flex flex-wrap justify-center gap-1">
            <x-filament::badge :color="$contact?->client_id ? 'success' : 'warning'" size="sm" :icon="$contact?->client_id ? 'heroicon-m-check-badge' : 'heroicon-m-exclamation-triangle'">
                {{ $contact?->client_id ? 'Registrado en caja' : 'No registrado en caja' }}
            </x-filament::badge>
        </div>
    </div>

    @if ($conversation->order_id || $conversation->pos_held_sale_id)
        <div class="flex items-start gap-2 rounded-xl bg-success-50 p-3 text-sm text-success-800 ring-1 ring-success-600/20 dark:bg-success-500/10 dark:text-success-200">
            <x-filament::icon icon="heroicon-m-check-circle" class="mt-0.5 h-5 w-5 shrink-0" />
            <div>
                @if ($conversation->order)
                    Pedido con delivery <strong>{{ $conversation->order->order_number }}</strong> · {{ $conversation->branch?->name }}
                @elseif ($conversation->posHeldSale)
                    En «Ventas en pausa» de la caja de <strong>{{ $conversation->branch?->name }}</strong>
                    ({{ mb_strtolower((string) $conversation->fulfillment_type?->label()) }}) ·
                    {{ $conversation->posHeldSale->status === PosHeldSaleStatus::Completed ? 'Cobrada' : $conversation->posHeldSale->status?->label() }}
                @endif
            </div>
        </div>
    @endif

    {{-- Resumen del asistente --}}
    <div class="{{ $card }}">
        <div class="{{ $cardTitle }}">
            <span class="{{ $titleText }}"><x-filament::icon icon="heroicon-m-sparkles" class="h-4 w-4 text-violet-500" /> Resumen de {{ $agentName }}</span>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $conversation->stageLabel() }}</span>
        </div>
        @if (filled($conversation->escalation_reason))
            <div class="mb-2 rounded-lg bg-danger-50 px-2.5 py-2 text-[13px] text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">
                {{ $conversation->escalation_reason }}
            </div>
        @endif
        <p class="whitespace-pre-line text-[13.5px] leading-relaxed text-gray-700 dark:text-gray-300">{{ $conversation->summary ?: 'Aún sin resumen.' }}</p>
        @if (filled($conversation->state['pending_question'] ?? null))
            <div class="mt-3 rounded-lg bg-gray-50 px-2.5 py-2 dark:bg-white/5">
                <div class="{{ $label }}">Última pregunta al cliente</div>
                <div class="{{ $value }}">{{ $conversation->state['pending_question'] }}</div>
            </div>
        @endif
        @if (filled($conversation->state['notes'] ?? null))
            <div class="mt-2"><div class="{{ $label }}">Notas</div><div class="{{ $value }}">{{ $conversation->state['notes'] }}</div></div>
        @endif
    </div>

    {{-- Cliente --}}
    <div class="{{ $card }}">
        <div class="{{ $cardTitle }}">
            <span class="{{ $titleText }}"><x-filament::icon icon="heroicon-m-user" class="h-4 w-4 text-gray-400" /> Cliente</span>
            {{ $this->registerCustomerAction }}
        </div>
        <dl class="grid grid-cols-2 gap-x-3 gap-y-3">
            <div class="col-span-2"><dt class="{{ $label }}">Nombre y apellido</dt><dd class="{{ $value }}">{{ $contact?->full_name ?: 'Sin confirmar' }}</dd></div>
            <div><dt class="{{ $label }}">Cédula</dt><dd class="{{ $value }}">{{ $contact?->formattedDocument() ?? '—' }}</dd></div>
            <div><dt class="{{ $label }}">Nombre en WhatsApp</dt><dd class="{{ $value }} truncate">{{ $contact?->profile_name ?: '—' }}</dd></div>
        </dl>
        @if ($contact?->client_data_mismatch)
            <div class="mt-3 rounded-lg bg-warning-50 px-2.5 py-2 text-xs text-warning-800 dark:bg-warning-500/10 dark:text-warning-200">{{ $contact->client_data_mismatch_note }}</div>
        @endif
    </div>

    {{-- Productos --}}
    <div class="{{ $card }}">
        <div class="{{ $cardTitle }}">
            <span class="{{ $titleText }}"><x-filament::icon icon="heroicon-m-shopping-bag" class="h-4 w-4 text-gray-400" /> Productos</span>
            {{ $this->editItemsAction }}
        </div>
        @forelse ($conversation->items as $item)
            <div class="flex items-start justify-between gap-3 border-b border-gray-100 py-2 first:pt-0 last:border-0 last:pb-0 dark:border-white/5">
                <div class="min-w-0">
                    <div @class(['text-[13.5px] text-gray-900 dark:text-gray-100', 'text-warning-700 dark:text-warning-300' => $item->product_id === null])>
                        {{ $item->product?->name ?? $item->requested_name }}
                    </div>
                    @if ($item->product_id === null)
                        <div class="text-[11.5px] text-warning-600 dark:text-warning-400">No está en inventario: el equipo lo consigue (o elija el producto del catálogo)</div>
                    @elseif (filled($item->notes))
                        <div class="{{ $label }}">{{ $item->notes }}</div>
                    @endif
                </div>
                <span class="shrink-0 rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                    × {{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}
                </span>
            </div>
        @empty
            <p class="text-[13px] text-gray-500 dark:text-gray-400">Aún no hay productos.</p>
        @endforelse
    </div>

    {{-- Entrega y pago --}}
    <div class="{{ $card }}">
        <div class="{{ $cardTitle }}">
            <span class="{{ $titleText }}"><x-filament::icon icon="heroicon-m-truck" class="h-4 w-4 text-gray-400" /> Entrega y pago</span>
            {{ $this->editDeliveryAction }}
        </div>
        <dl class="flex flex-col gap-3">
            <div>
                <dt class="{{ $label }}">Entrega</dt>
                <dd class="{{ $value }}">{{ $conversation->fulfillment_type?->label() ?? 'Sin definir' }}{{ filled($conversation->state['preferred_branch'] ?? null) ? ' · prefiere '.$conversation->state['preferred_branch'] : '' }}</dd>
            </div>
            @if (filled($conversation->delivery_address) || $conversation->delivery_latitude !== null)
                <div>
                    <dt class="{{ $label }}">Dirección</dt>
                    <dd class="{{ $value }}">
                        {{ $conversation->delivery_address ?: 'Ubicación enviada por WhatsApp' }}
                        @if (filled($conversation->delivery_reference))
                            <span class="text-gray-500"> · {{ $conversation->delivery_reference }}</span>
                        @endif
                        @if ($conversation->delivery_latitude !== null)
                            <a href="https://www.google.com/maps?q={{ $conversation->delivery_latitude }},{{ $conversation->delivery_longitude }}" target="_blank" rel="noopener" class="ml-1 font-medium text-primary-600 hover:underline dark:text-primary-400">Ver mapa</a>
                        @endif
                    </dd>
                </div>
            @endif
            @if (filled($conversation->delivery_recipient_name))
                <div><dt class="{{ $label }}">Recibe</dt><dd class="{{ $value }}">{{ $conversation->delivery_recipient_name }}{{ filled($conversation->delivery_recipient_phone) ? ' · '.$conversation->delivery_recipient_phone : '' }}</dd></div>
            @endif
            <div><dt class="{{ $label }}">Forma de pago</dt><dd class="{{ $value }}">{{ $conversation->payment_method_note ?: '—' }}</dd></div>
        </dl>
    </div>

    {{-- Récipes --}}
    @if ($conversation->prescriptionReadings->isNotEmpty())
        <div class="{{ $card }}">
            <div class="{{ $cardTitle }}">
                <span class="{{ $titleText }}"><x-filament::icon icon="heroicon-m-photo" class="h-4 w-4 text-gray-400" /> Fotos leídas</span>
                {{ $this->validatePrescriptionsAction }}
            </div>
            @foreach ($conversation->prescriptionReadings as $reading)
                <div class="border-b border-gray-100 py-2 first:pt-0 last:border-0 last:pb-0 dark:border-white/5">
                    <div class="mb-1.5 flex flex-wrap items-center gap-1.5">
                        <x-filament::badge color="gray" size="sm" :icon="$reading->image_kind?->icon()">{{ $reading->image_kind?->label() ?? 'Récipe médico' }}</x-filament::badge>
                        @if ($reading->isRecipe())
                            <x-filament::badge :color="$reading->legibility?->filamentColor() ?? 'gray'" size="sm">{{ $reading->legibility?->label() }}</x-filament::badge>
                        @endif
                        @if ($reading->has_controlled_substance)
                            <x-filament::badge color="danger" size="sm">Controlado</x-filament::badge>
                        @endif
                        <span class="{{ $label }}">{{ $reading->validated_at ? 'Validado por '.($reading->validatedBy?->name ?? 'analista') : 'Sin validar' }}</span>
                    </div>
                    @if ($reading->image_kind === \App\Enums\WhatsappImageKind::PaymentProof)
                        @php $payment = $reading->payment_data ?? []; @endphp
                        <dl class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-[13px]">
                            @foreach (['amount' => 'Monto', 'reference' => 'Referencia', 'bank' => 'Banco emisor', 'destination_bank' => 'Banco receptor', 'date' => 'Fecha', 'destination' => 'Destino'] as $paymentKey => $paymentLabel)
                                @if (filled($payment[$paymentKey] ?? null))
                                    <div @class(['col-span-2' => in_array($paymentKey, ['bank', 'destination_bank', 'destination'], true)])>
                                        <dt class="{{ $label }}">{{ $paymentLabel }}</dt>
                                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $payment[$paymentKey] }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    @elseif (! $reading->isRecipe())
                        <p class="text-[13px] text-gray-700 dark:text-gray-300">{{ $reading->description ?: '—' }}</p>
                    @endif
                    @forelse ($reading->items as $readingItem)
                        <div class="flex items-baseline justify-between gap-2 text-[13px] text-gray-800 dark:text-gray-200">
                            <span>{{ trim($readingItem->medication_name.' '.$readingItem->concentration) }}@if ($readingItem->quantity_text) <span class="text-gray-500">· {{ $readingItem->quantity_text }}</span>@endif</span>
                            @if ($readingItem->confidence !== null)
                                <span @class(['shrink-0 text-[11px] font-medium', 'text-danger-600' => (float) $readingItem->confidence < 0.7, 'text-gray-500' => (float) $readingItem->confidence >= 0.7])>{{ (int) round((float) $readingItem->confidence * 100) }}%</span>
                            @endif
                        </div>
                    @empty
                        @if ($reading->isRecipe())
                            <p class="text-[13px] text-gray-500">No se identificaron medicamentos.</p>
                        @endif
                    @endforelse
                    @if (filled($reading->doctor_name))
                        <div class="mt-1 {{ $label }}">Médico: {{ $reading->doctor_name }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Crear pedido --}}
    @if ($conversation->isOpen())
        <div class="sticky bottom-0 mt-auto -mx-3 -mb-3 border-t border-gray-200 bg-gray-50/95 p-3 backdrop-blur dark:border-white/10 dark:bg-gray-950/90">
            <div class="mb-2 text-[12px] text-gray-500 dark:text-gray-400">Crear el pedido en la sucursal que elija</div>
            <div class="grid grid-cols-2 gap-2">
                {{ $this->convertToPickupAction }}
                {{ $this->convertToDeliveryAction }}
            </div>
        </div>
    @endif
</div>
