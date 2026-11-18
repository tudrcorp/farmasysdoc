@php
    use App\Enums\WhatsappMessageAuthor;
    use App\Enums\WhatsappMessageType;
    use App\Models\WhatsappMessage;

    /**
     * @var WhatsappMessage $message
     * @var bool $startsGroup Primer mensaje de una racha del mismo autor.
     * @var string $agentName
     */
    $author = $message->author;
    $isCustomer = $author === WhatsappMessageAuthor::Customer;
    $isAgent = $author === WhatsappMessageAuthor::Agent;
    $mediaUrl = $message->hasStoredMedia() ? route('whatsapp-agent.message-media', ['message' => $message]) : null;
    $time = $message->created_at?->timezone('America/Caracas');
    $status = $message->delivery_status;
@endphp

@if ($author === WhatsappMessageAuthor::System)
    <div class="my-2 flex justify-center">
        <span class="rounded-lg bg-white/80 px-3 py-1 text-center text-[11.5px] text-gray-600 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/10 dark:text-gray-300 dark:ring-white/10">
            {{ $message->body }} · {{ $time?->format('h:i a') }}
        </span>
    </div>
@else
    <div @class([
        'group/msg flex w-full',
        'justify-start' => $isCustomer,
        'justify-end' => ! $isCustomer,
        'mt-3' => $startsGroup,
        'mt-0.5' => ! $startsGroup,
    ])>
        <div @class([
            'relative max-w-[78%] px-2.5 pt-1.5 pb-1 text-[14.5px] leading-snug shadow-[0_1px_0.5px_rgba(11,20,26,0.13)] sm:max-w-[65%]',
            'rounded-lg' => ! $startsGroup,
            'rounded-lg rounded-tl-none' => $startsGroup && $isCustomer,
            'rounded-lg rounded-tr-none' => $startsGroup && ! $isCustomer,
            'bg-white text-gray-900 dark:bg-gray-800 dark:text-gray-100' => $isCustomer,
            'bg-violet-100 text-gray-900 dark:bg-violet-950 dark:text-violet-50' => $isAgent,
            'bg-[#d9fdd3] text-gray-900 dark:bg-emerald-900 dark:text-emerald-50' => $author === WhatsappMessageAuthor::Analyst,
            'ring-1 ring-danger-400' => $status === WhatsappMessage::STATUS_FAILED,
        ])>
            @if ($startsGroup && ! $isCustomer)
                <div @class([
                    'mb-0.5 flex items-center gap-1 text-[12px] font-semibold',
                    'text-violet-700 dark:text-violet-300' => $isAgent,
                    'text-emerald-700 dark:text-emerald-300' => ! $isAgent,
                ])>
                    @if ($isAgent)
                        <x-filament::icon icon="heroicon-m-sparkles" class="h-3.5 w-3.5" />
                        {{ $agentName }}
                    @else
                        {{ $message->sentBy?->name ?? 'Analista' }}
                    @endif
                </div>
            @endif

            @if ($message->type === WhatsappMessageType::Image)
                @if ($mediaUrl)
                    <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="-mx-1 mb-1 block overflow-hidden rounded-md">
                        <img src="{{ $mediaUrl }}" alt="Imagen enviada por el cliente" class="max-h-72 w-full object-cover" loading="lazy" x-on:load="$dispatch('whatsapp-media-loaded')">
                    </a>
                @else
                    <div class="mb-1 flex h-32 w-56 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-500 dark:bg-white/5">
                        <x-filament::loading-indicator class="mr-2 h-4 w-4" /> Descargando imagen…
                    </div>
                @endif
                @if ($message->prescriptionReading)
                    @php $reading = $message->prescriptionReading; @endphp
                    <div class="mb-1 flex max-w-xs items-start gap-1 rounded-md bg-gray-100 px-1.5 py-1 text-[11.5px] leading-snug text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        <x-filament::icon :icon="$reading->image_kind?->icon() ?? 'heroicon-m-photo'" class="mt-px h-3.5 w-3.5 shrink-0" />
                        <span>
                            {{ $reading->summaryLine() }}
                            @if ($reading->requires_analyst)
                                <span class="font-semibold text-danger-600 dark:text-danger-400">· revisar</span>
                            @endif
                        </span>
                    </div>
                @endif
            @elseif ($message->type === WhatsappMessageType::Audio)
                @if ($mediaUrl)
                    <audio controls preload="none" src="{{ $mediaUrl }}" class="mb-1 h-10 w-64 max-w-full"></audio>
                @else
                    <div class="mb-1 text-xs italic opacity-70">Descargando nota de voz…</div>
                @endif
                @if (filled($message->transcription))
                    <div class="mb-1 border-l-2 border-gray-300 pl-2 text-[13px] italic text-gray-600 dark:border-white/20 dark:text-gray-300">{{ $message->transcription }}</div>
                @endif
            @elseif ($message->type === WhatsappMessageType::Location && $message->latitude !== null)
                <a href="https://www.google.com/maps?q={{ $message->latitude }},{{ $message->longitude }}" target="_blank" rel="noopener"
                   class="mb-1 flex items-center gap-2 rounded-md bg-gray-100 px-2 py-2 text-sm font-medium text-gray-800 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-100">
                    <x-filament::icon icon="heroicon-m-map-pin" class="h-5 w-5 text-danger-500" /> Ubicación compartida
                </a>
            @elseif ($message->type === WhatsappMessageType::Document && $mediaUrl)
                <a href="{{ $mediaUrl }}" target="_blank" rel="noopener"
                   class="mb-1 flex min-w-[220px] items-center gap-2.5 rounded-md bg-black/5 px-2.5 py-2 hover:bg-black/10 dark:bg-white/10 dark:hover:bg-white/15">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white text-sky-600 shadow-sm dark:bg-gray-900 dark:text-sky-300">
                        <x-filament::icon icon="heroicon-m-document-text" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-[13.5px] font-medium">{{ $message->meta['filename'] ?? ($isCustomer && filled($message->body) ? $message->body : 'Documento') }}</span>
                        <span class="block text-[11px] uppercase text-gray-500">{{ pathinfo((string) $message->media_path, PATHINFO_EXTENSION) }}</span>
                    </span>
                </a>
            @elseif ($message->type === WhatsappMessageType::Other && blank($message->body))
                <div class="text-[13px] italic opacity-60">Sticker o mensaje no compatible</div>
            @endif

            @if (filled($message->body) && ! ($message->type === WhatsappMessageType::Document && $isCustomer && $mediaUrl))
                <span class="whitespace-pre-line break-words">{{ $message->body }}</span>
            @endif

            <span class="float-right ml-2 mt-1.5 flex translate-y-0.5 items-center gap-0.5 text-[11px] leading-none text-gray-500 dark:text-gray-400">
                {{ $time?->format('h:i a') }}
                @unless ($isCustomer)
                    @if ($status === WhatsappMessage::STATUS_PENDING)
                        <x-filament::icon icon="heroicon-m-clock" class="h-3.5 w-3.5" title="Enviando…" />
                    @elseif ($status === WhatsappMessage::STATUS_FAILED)
                        <x-filament::icon icon="heroicon-m-exclamation-circle" class="h-3.5 w-3.5 text-danger-500" title="No se envió" />
                    @else
                        <x-filament::icon icon="heroicon-m-check" class="h-3.5 w-3.5" title="Enviado" />
                    @endif
                @endunless
            </span>

            @if ($status === WhatsappMessage::STATUS_FAILED)
                <div class="clear-both mt-1 flex items-center justify-end gap-2 border-t border-danger-200 pt-1 text-[11.5px] text-danger-600 dark:border-danger-500/30 dark:text-danger-400">
                    No se envió
                    @if ($author === WhatsappMessageAuthor::Analyst)
                        <button type="button" wire:click="retryMessage({{ $message->id }})" class="font-semibold underline">Reintentar</button>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif
