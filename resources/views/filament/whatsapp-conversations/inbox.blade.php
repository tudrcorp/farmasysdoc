@php
    use App\Enums\WhatsappConversationHandler;
    use App\Enums\WhatsappConversationStatus;
    use App\Enums\WhatsappMessageAuthor;
    use App\Enums\WhatsappMessageType;
    use App\Filament\Resources\WhatsappConversations\Pages\WhatsappInbox;
    use App\Filament\Resources\WhatsappConversations\WhatsappConversationResource;
    use App\Models\WhatsappAgentSetting;
    use Carbon\CarbonInterface;
    use Illuminate\Support\Str;

    $conversation = $this->selectedConversation;
    $agentName = WhatsappAgentSetting::current()->agentDisplayName();
    $counts = $this->folderCounts;

    $initials = fn (string $name): string => mb_strtoupper(collect(preg_split('/\s+/u', trim($name)) ?: [])
        ->filter(fn ($part) => preg_match('/\p{L}/u', (string) $part) === 1)
        ->take(2)
        ->map(fn ($part) => mb_substr((string) $part, 0, 1))
        ->implode('')) ?: '#';

    $avatarPalette = [
        'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
        'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
        'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
        'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
        'bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-300',
        'bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-300',
        'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300',
    ];
    $avatarColor = fn (?string $seed): string => $avatarPalette[crc32((string) $seed) % count($avatarPalette)];

    $statusDot = fn (?WhatsappConversationStatus $status): string => match ($status) {
        WhatsappConversationStatus::NeedsAnalyst => 'bg-danger-500',
        WhatsappConversationStatus::PendingNextShift => 'bg-warning-500',
        WhatsappConversationStatus::InProgress => 'bg-sky-500',
        WhatsappConversationStatus::Bot => 'bg-violet-500',
        default => 'bg-gray-400',
    };

    $listTime = function (?CarbonInterface $at): string {
        if ($at === null) {
            return '';
        }
        $at = $at->timezone('America/Caracas');

        return match (true) {
            $at->isToday() => $at->format('h:i a'),
            $at->isYesterday() => 'Ayer',
            $at->greaterThan(now()->subDays(6)) => Str::ucfirst($at->locale('es')->isoFormat('dddd')),
            default => $at->format('d/m/y'),
        };
    };

    $dayLabel = function (CarbonInterface $at): string {
        $at = $at->timezone('America/Caracas');

        return match (true) {
            $at->isToday() => 'Hoy',
            $at->isYesterday() => 'Ayer',
            default => Str::ucfirst($at->locale('es')->isoFormat('dddd D [de] MMMM')),
        };
    };

    $previewIcon = fn (?WhatsappMessageType $type): ?string => match ($type) {
        WhatsappMessageType::Image => 'heroicon-m-camera',
        WhatsappMessageType::Audio => 'heroicon-m-microphone',
        WhatsappMessageType::Location => 'heroicon-m-map-pin',
        WhatsappMessageType::Document => 'heroicon-m-paper-clip',
        default => null,
    };

    $previewText = fn ($message): string => match (true) {
        $message === null => '',
        filled($message->body) => (string) $message->body,
        $message->type === WhatsappMessageType::Image => 'Foto',
        $message->type === WhatsappMessageType::Audio => 'Nota de voz',
        $message->type === WhatsappMessageType::Location => 'Ubicación',
        $message->type === WhatsappMessageType::Document => 'Documento',
        default => 'Mensaje',
    };
@endphp

<x-filament-panels::page>
    <div
        wire:poll.{{ $this->pollInterval() }}.visible
        x-data="{
            details: false,
            listOpen: true,
            panelOpen: true,
            init() {
                try {
                    this.listOpen = localStorage.getItem('fd-wa-list-open') !== '0';
                    this.panelOpen = localStorage.getItem('fd-wa-panel-open') !== '0';
                } catch (error) {}
                this.$watch('listOpen', (value) => { try { localStorage.setItem('fd-wa-list-open', value ? '1' : '0') } catch (error) {} });
                this.$watch('panelOpen', (value) => { try { localStorage.setItem('fd-wa-panel-open', value ? '1' : '0') } catch (error) {} });
            },
            toggleDetails() {
                if (window.matchMedia('(min-width: 1280px)').matches) {
                    this.panelOpen = ! this.panelOpen;
                } else {
                    this.details = true;
                }
            },
            gridClasses() {
                const hasPanel = this.panelOpen && this.$wire.selectedId !== null;
                if (this.listOpen && hasPanel) return 'lg:grid-cols-[360px_minmax(0,1fr)] xl:grid-cols-[360px_minmax(0,1fr)_340px]';
                if (this.listOpen) return 'lg:grid-cols-[360px_minmax(0,1fr)]';
                if (hasPanel) return 'lg:grid-cols-[76px_minmax(0,1fr)] xl:grid-cols-[76px_minmax(0,1fr)_340px]';
                return 'lg:grid-cols-[76px_minmax(0,1fr)]';
            },
        }"
        x-bind:class="gridClasses()"
        class="relative -mt-2 grid h-[calc(100dvh-6.5rem)] min-h-[560px] grid-cols-1 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 transition-[grid-template-columns] duration-200 dark:bg-gray-900 dark:ring-white/10"
    >
        {{-- ═══════════ Lista de chats ═══════════ --}}
        <aside @class([
            'min-h-0 flex-col border-gray-200 lg:flex lg:border-r dark:border-white/10',
            'hidden' => $conversation !== null,
            'flex' => $conversation === null,
        ])>
            {{-- Lista colapsada: solo avatares --}}
            <div class="hidden min-h-0 flex-1 flex-col items-center gap-1 py-3" x-bind:class="! listOpen && 'lg:flex'">
                <button type="button" x-on:click="listOpen = true" title="Mostrar la lista de chats"
                    class="mb-2 flex h-10 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10">
                    <x-filament::icon icon="heroicon-m-chevron-double-right" class="h-5 w-5" />
                </button>
                @if (($counts['por_atender'] ?? 0) > 0)
                    <button type="button" wire:click="selectFolder('por_atender')" x-on:click="listOpen = true" title="Por atender"
                        class="mb-2 flex h-6 min-w-6 items-center justify-center rounded-full bg-danger-500 px-1.5 text-[11px] font-bold text-white">
                        {{ $counts['por_atender'] }}
                    </button>
                @endif
                <div class="flex min-h-0 w-full flex-1 flex-col items-center gap-2.5 overflow-y-auto px-2 pt-1.5 pb-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($this->conversations as $railItem)
                        @php
                            $railName = $railItem->contact?->displayName() ?? 'Cliente';
                            $railSelected = $conversation?->id === $railItem->id;
                            $railUnread = $railItem->unread_count > 0 && $railItem->status !== WhatsappConversationStatus::Bot && ! $railSelected;
                        @endphp
                        <button type="button" wire:key="rail-{{ $railItem->id }}" wire:click="selectConversation({{ $railItem->id }})" title="{{ $railName }}"
                            @class([
                                'relative shrink-0 rounded-full transition',
                                'ring-2 ring-emerald-500 ring-offset-2 ring-offset-white dark:ring-offset-gray-900' => $railSelected,
                                'mt-0.5' => $loop->first,
                                'hover:opacity-80' => ! $railSelected,
                            ])>
                            <span class="flex h-11 w-11 items-center justify-center rounded-full text-[13px] font-semibold {{ $avatarColor($railItem->contact?->phone) }}">{{ $initials($railName) }}</span>
                            <span class="absolute right-0 bottom-0 h-3 w-3 rounded-full ring-2 ring-white dark:ring-gray-900 {{ $statusDot($railItem->status) }}"></span>
                            @if ($railUnread)
                                <span class="absolute -top-1 -right-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-emerald-500 px-1 text-[10px] font-bold text-white ring-2 ring-white dark:ring-gray-900">{{ $railItem->unread_count }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="flex min-h-0 flex-1 flex-col" x-bind:class="! listOpen && 'lg:hidden'">
            <div class="flex flex-col gap-3 px-4 pt-4 pb-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Chats</h2>
                    <div class="flex items-center gap-1">
                        <a href="{{ WhatsappConversationResource::getUrl('history') }}" class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5">
                            <x-filament::icon icon="heroicon-m-clock" class="h-4 w-4" /> Historial
                        </a>
                        <button type="button" x-on:click="listOpen = false" title="Ocultar la lista de chats"
                            class="hidden h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:flex dark:text-gray-400 dark:hover:bg-white/5">
                            <x-filament::icon icon="heroicon-m-chevron-double-left" class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <label class="flex items-center gap-2 rounded-xl bg-gray-100 px-3 py-2 focus-within:bg-white focus-within:ring-2 focus-within:ring-emerald-500/40 dark:bg-white/5 dark:focus-within:bg-white/10">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-4 w-4 shrink-0 text-gray-400" />
                    <input type="search" wire:model.live.debounce.350ms="search" placeholder="Buscar por nombre, teléfono o cédula"
                        class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-white">
                </label>

                <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach (WhatsappInbox::FOLDERS as $key => $folderItem)
                        @php $active = $this->folder === $key; @endphp
                        <button type="button" wire:click="selectFolder('{{ $key }}')" @class([
                            'flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-[12.5px] font-medium transition',
                            'bg-emerald-600 text-white shadow-sm' => $active,
                            'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => ! $active,
                        ])>
                            {{ $folderItem['label'] }}
                            @if (($counts[$key] ?? 0) > 0)
                                <span @class([
                                    'min-w-[18px] rounded-full px-1 text-center text-[10.5px] font-bold leading-[18px]',
                                    'bg-white/25 text-white' => $active,
                                    'bg-danger-500 text-white' => ! $active && $key === 'por_atender',
                                    'bg-emerald-600 text-white' => ! $active && $key !== 'por_atender',
                                ])>{{ $counts[$key] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto border-t border-gray-100 dark:border-white/5">
                @forelse ($this->conversations as $item)
                    @php
                        $contactName = $item->contact?->displayName() ?? 'Cliente';
                        $last = $item->latestMessage;
                        $selected = $conversation?->id === $item->id;
                        $showUnread = $item->unread_count > 0 && $item->status !== WhatsappConversationStatus::Bot && ! $selected;
                        $waiting = $item->minutesWaitingForAnalyst();
                        $prefix = match ($last?->author) {
                            WhatsappMessageAuthor::Agent => $agentName.': ',
                            WhatsappMessageAuthor::Analyst => $last->sent_by_user_id === auth()->id() ? 'Tú: ' : ($last->sentBy?->name ?? 'Analista').': ',
                            default => '',
                        };
                        $icon = $previewIcon($last?->type);
                    @endphp
                    <button type="button" wire:key="conversation-{{ $item->id }}" wire:click="selectConversation({{ $item->id }})" @class([
                        'relative flex w-full items-center gap-3 px-4 py-3 text-left transition',
                        'bg-emerald-50/70 dark:bg-emerald-500/10' => $selected,
                        'hover:bg-gray-50 dark:hover:bg-white/5' => ! $selected,
                    ])>
                        @if ($selected)
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-emerald-600"></span>
                        @endif
                        <span class="relative shrink-0">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full text-sm font-semibold {{ $avatarColor($item->contact?->phone) }}">{{ $initials($contactName) }}</span>
                            <span class="absolute right-0 bottom-0 h-3.5 w-3.5 rounded-full ring-2 ring-white dark:ring-gray-900 {{ $statusDot($item->status) }}" title="{{ $item->status?->label() }}"></span>
                        </span>
                        <span class="min-w-0 flex-1 border-b border-gray-100 pb-3 -mb-3 dark:border-white/5">
                            <span class="flex items-baseline justify-between gap-2">
                                <span @class(['truncate text-[15px] text-gray-900 dark:text-white', 'font-semibold' => $showUnread, 'font-medium' => ! $showUnread])>{{ $contactName }}</span>
                                <span @class(['shrink-0 text-[11.5px]', 'font-semibold text-emerald-600' => $showUnread, 'text-gray-400' => ! $showUnread])>{{ $listTime($last?->created_at) }}</span>
                            </span>
                            <span class="mt-0.5 flex items-center justify-between gap-2">
                                <span @class(['flex min-w-0 items-center gap-1 text-[13px]', 'text-gray-800 dark:text-gray-200' => $showUnread, 'text-gray-500 dark:text-gray-400' => ! $showUnread])>
                                    @if ($last?->author && $last->author->isOutbound())
                                        @if ($last->delivery_status === 'pending')
                                            <x-filament::icon icon="heroicon-m-clock" class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                        @elseif ($last->delivery_status === 'failed')
                                            <x-filament::icon icon="heroicon-m-exclamation-circle" class="h-3.5 w-3.5 shrink-0 text-danger-500" />
                                        @else
                                            <x-filament::icon icon="heroicon-m-check" class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                        @endif
                                    @endif
                                    @if ($icon)
                                        <x-filament::icon :icon="$icon" class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                    @endif
                                    <span class="truncate">{{ $prefix }}{{ Str::limit(str_replace("\n", ' ', $previewText($last)), 70) }}</span>
                                </span>
                                @if ($showUnread)
                                    <span class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500 px-1.5 text-[11px] font-bold text-white">{{ $item->unread_count }}</span>
                                @endif
                            </span>
                            @if ($waiting !== null || ($item->handled_by === WhatsappConversationHandler::Analyst && $item->assignedUser))
                                <span class="mt-1 flex items-center gap-2 text-[11.5px]">
                                    @if ($waiting !== null)
                                        <span @class([
                                            'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 font-medium',
                                            'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-300' => $waiting >= 30,
                                            'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300' => $waiting >= 10 && $waiting < 30,
                                            'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300' => $waiting < 10,
                                        ])>
                                            <x-filament::icon icon="heroicon-m-clock" class="h-3 w-3" />
                                            {{ $waiting < 60 ? $waiting.' min' : intdiv($waiting, 60).' h '.($waiting % 60).' min' }}
                                        </span>
                                    @endif
                                    @if ($item->handled_by === WhatsappConversationHandler::Analyst && $item->assignedUser)
                                        <span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400">
                                            <x-filament::icon icon="heroicon-m-user" class="h-3 w-3" /> {{ $item->assigned_user_id === auth()->id() ? 'Tú' : $item->assignedUser->name }}
                                        </span>
                                    @endif
                                </span>
                            @endif
                        </span>
                    </button>
                @empty
                    <div class="flex flex-col items-center gap-3 px-8 py-16 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-white/5">
                            <x-filament::icon icon="heroicon-o-inbox" class="h-7 w-7 text-gray-400" />
                        </span>
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Todo al día</span>
                        <span class="text-xs text-gray-500">No hay conversaciones en esta bandeja.</span>
                    </div>
                @endforelse
            </div>
            </div>
        </aside>

        {{-- ═══════════ Conversación ═══════════ --}}
        <section @class([
            'min-h-0 min-w-0 flex-col lg:flex',
            'flex' => $conversation !== null,
            'hidden' => $conversation === null,
        ])>
            @if ($conversation)
                @php
                    $contact = $conversation->contact;
                    $isOpen = $conversation->isOpen();
                    $isMine = $this->isMine($conversation);
                    $awaiting = in_array($conversation->status, WhatsappConversationStatus::awaitingAnalyst(), true);
                    $subtitle = match (true) {
                        ! $isOpen => $conversation->status?->label(),
                        $conversation->isHandledByAgent() => 'Atendida por '.$agentName,
                        $isMine => 'Conversación asignada a ti',
                        default => 'Atiende '.($conversation->assignedUser?->name ?? 'un analista'),
                    };
                @endphp

                <header class="flex h-16 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-3 sm:px-4 dark:border-white/10 dark:bg-gray-900">
                    <button type="button" wire:click="closeConversationPanel" class="-ml-1 rounded-full p-1.5 text-gray-500 hover:bg-gray-100 lg:hidden dark:hover:bg-white/10" aria-label="Volver a la lista">
                        <x-filament::icon icon="heroicon-m-arrow-left" class="h-5 w-5" />
                    </button>
                    <button type="button" x-on:click="toggleDetails()" title="Ficha del cliente" class="flex min-w-0 flex-1 items-center gap-3 text-left">
                        <span class="relative shrink-0">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold {{ $avatarColor($contact?->phone) }}">{{ $initials($contact?->displayName() ?? 'Cliente') }}</span>
                            <span class="absolute right-0 bottom-0 h-3 w-3 rounded-full ring-2 ring-white dark:ring-gray-900 {{ $statusDot($conversation->status) }}"></span>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-[15px] font-semibold text-gray-900 dark:text-white">{{ $contact?->displayName() }}</span>
                            <span class="block truncate text-[12.5px] text-gray-500 dark:text-gray-400">{{ $contact?->localPhone() }} · {{ $subtitle }}</span>
                        </span>
                    </button>
                    <div class="flex shrink-0 items-center gap-1.5">
                        {{ $this->takeAction }}
                        <x-filament-actions::group
                            :actions="[$this->assignAction, $this->returnToAgentAction, $this->closeAction]"
                            icon="heroicon-m-ellipsis-vertical"
                            color="gray"
                            tooltip="Más acciones"
                            dropdown-placement="bottom-end"
                        />
                        <button type="button" x-on:click="toggleDetails()" aria-label="Mostrar u ocultar la ficha del cliente"
                            x-bind:title="panelOpen ? 'Ocultar la ficha del cliente' : 'Mostrar la ficha del cliente'"
                            class="rounded-full p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/10"
                            x-bind:class="panelOpen && 'xl:bg-emerald-50 xl:text-emerald-700 xl:dark:bg-emerald-500/10 xl:dark:text-emerald-300'">
                            <x-filament::icon icon="heroicon-m-identification" class="h-5 w-5" />
                        </button>
                    </div>
                </header>

                @if ($awaiting && ! $isMine)
                    <div class="flex shrink-0 items-start gap-2.5 border-b border-danger-100 bg-danger-50 px-4 py-2.5 text-[13px] text-danger-800 dark:border-danger-500/20 dark:bg-danger-500/10 dark:text-danger-200">
                        <x-filament::icon icon="heroicon-m-bell-alert" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span><strong>{{ $agentName }} necesita a una persona:</strong> {{ $conversation->escalation_reason ?: 'sin motivo indicado' }}</span>
                    </div>
                @endif

                {{-- Mensajes --}}
                <div
                    wire:key="chat-{{ $conversation->id }}"
                    x-data="{
                        atBottom: true,
                        toBottom() { this.$el.scrollTop = this.$el.scrollHeight },
                    }"
                    x-init="$nextTick(() => toBottom()); new MutationObserver(() => { if (atBottom) $nextTick(() => toBottom()) }).observe($el, { childList: true, subtree: true })"
                    x-on:scroll.passive="atBottom = ($el.scrollHeight - $el.scrollTop - $el.clientHeight) < 140"
                    x-on:whatsapp-scroll-bottom.window="atBottom = true; $nextTick(() => toBottom())"
                    x-on:whatsapp-media-loaded="if (atBottom) toBottom()"
                    @if ($isOpen)
                        x-on:dragover.prevent="$el.classList.add('ring-4', 'ring-inset', 'ring-emerald-400/60')"
                        x-on:dragleave.prevent="$el.classList.remove('ring-4', 'ring-inset', 'ring-emerald-400/60')"
                        x-on:drop.prevent="$el.classList.remove('ring-4', 'ring-inset', 'ring-emerald-400/60'); $dispatch('whatsapp-file-dropped', { file: $event.dataTransfer.files[0] })"
                    @endif
                    class="min-h-0 flex-1 overflow-y-auto bg-[#efeae2] bg-[radial-gradient(rgba(0,0,0,0.035)_1px,transparent_1px)] [background-size:18px_18px] px-3 py-4 sm:px-8 dark:bg-gray-950 dark:bg-[radial-gradient(rgba(255,255,255,0.03)_1px,transparent_1px)]"
                >
                    <div class="mx-auto flex max-w-3xl flex-col">
                        @php $previous = null; @endphp
                        @forelse ($conversation->messages as $message)
                            @php
                                $at = $message->created_at;
                                $newDay = $previous === null || ! $previous->created_at->timezone('America/Caracas')->isSameDay($at->timezone('America/Caracas'));
                                $startsGroup = $newDay
                                    || $previous->author !== $message->author
                                    || $previous->author === WhatsappMessageAuthor::System
                                    || $previous->sent_by_user_id !== $message->sent_by_user_id
                                    || $previous->created_at->diffInMinutes($at) > 5;
                            @endphp
                            @if ($newDay)
                                <div class="sticky top-0 z-10 my-3 flex justify-center">
                                    <span class="rounded-lg bg-white/90 px-3 py-1 text-[12px] font-medium text-gray-600 shadow-sm backdrop-blur dark:bg-gray-800/90 dark:text-gray-300">{{ $dayLabel($at) }}</span>
                                </div>
                            @endif
                            @include('filament.whatsapp-conversations.partials.message', ['message' => $message, 'startsGroup' => $startsGroup, 'agentName' => $agentName])
                            @php $previous = $message; @endphp
                        @empty
                            <div class="m-auto py-20 text-sm text-gray-500">Sin mensajes.</div>
                        @endforelse
                    </div>
                </div>

                {{-- Compositor --}}
                @if ($isOpen)
                    <footer
                        wire:key="composer-{{ $conversation->id }}"
                        wire:ignore
                        x-data="{
                            text: '',
                            replies: @js($this->quickReplies()),
                            showReplies: false,
                            file: null,
                            uploading: false,
                            progress: 0,
                            maxBytes: {{ WhatsappInbox::ATTACHMENT_MAX_KB * 1024 }},
                            resize() {
                                const el = this.$refs.input;
                                el.style.height = 'auto';
                                el.style.height = Math.min(el.scrollHeight, 160) + 'px';
                            },
                            pick() { this.$refs.file.click() },
                            attach(selected) {
                                if (! selected) return;
                                if (selected.size > this.maxBytes) {
                                    alert('El archivo pesa más de 12 MB.');
                                    return;
                                }
                                this.clearFile(false);
                                const isImage = selected.type.startsWith('image/');
                                this.file = {
                                    name: selected.name || 'imagen.png',
                                    size: selected.size < 1048576 ? Math.max(1, Math.round(selected.size / 1024)) + ' KB' : (selected.size / 1048576).toFixed(1) + ' MB',
                                    isImage: isImage,
                                    preview: isImage ? URL.createObjectURL(selected) : null,
                                };
                                this.uploading = true;
                                this.progress = 0;
                                this.$wire.upload('attachment', selected,
                                    () => { this.uploading = false },
                                    () => { this.uploading = false; this.clearFile(false); alert('No se pudo subir el archivo. Intente de nuevo.') },
                                    (event) => { this.progress = event.detail.progress },
                                );
                                this.$nextTick(() => this.$refs.input.focus());
                            },
                            clearFile(notifyServer = true) {
                                if (this.file?.preview) URL.revokeObjectURL(this.file.preview);
                                this.file = null;
                                this.uploading = false;
                                if (this.$refs.file) this.$refs.file.value = '';
                                if (notifyServer) this.$wire.removeAttachment();
                            },
                            pasted(event) {
                                const item = Array.from(event.clipboardData?.items || []).find((entry) => entry.kind === 'file');
                                if (! item) return;
                                event.preventDefault();
                                this.attach(item.getAsFile());
                            },
                            send() {
                                const body = this.text.trim();
                                if (this.file) {
                                    if (this.uploading) return;
                                    this.text = '';
                                    this.$nextTick(() => this.resize());
                                    this.$dispatch('whatsapp-scroll-bottom');
                                    this.$wire.sendAttachment(body);
                                    return;
                                }
                                if (body === '') return;
                                this.text = '';
                                this.$nextTick(() => this.resize());
                                this.$dispatch('whatsapp-scroll-bottom');
                                this.$wire.sendMessage(body);
                            },
                            useReply(reply) {
                                this.text = this.text.trim() === '' ? reply : this.text.trimEnd() + ' ' + reply;
                                this.showReplies = false;
                                this.$nextTick(() => { this.resize(); this.$refs.input.focus(); });
                            },
                        }"
                        x-on:whatsapp-file-dropped.window="attach($event.detail.file)"
                        x-on:whatsapp-attachment-sent.window="clearFile(false)"
                        x-on:whatsapp-attachment-failed.window="clearFile(false)"
                        class="shrink-0 border-t border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-white/10 dark:bg-gray-900"
                    >
                        @if ($conversation->isHandledByAgent())
                            <div class="mx-auto mb-2 flex max-w-3xl items-center gap-1.5 text-[12px] text-violet-700 dark:text-violet-300">
                                <x-filament::icon icon="heroicon-m-sparkles" class="h-3.5 w-3.5" />
                                {{ $agentName }} está atendiendo. Si escribes, tomas la conversación y {{ $agentName }} deja de responder.
                            </div>
                        @endif

                        {{-- Vista previa del adjunto --}}
                        <div x-show="file" x-cloak x-transition class="mx-auto mb-2 flex max-w-3xl items-center gap-3 rounded-xl bg-white p-2 pr-3 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">
                            <template x-if="file && file.isImage">
                                <img x-bind:src="file.preview" alt="Vista previa" class="h-16 w-16 shrink-0 rounded-lg object-cover">
                            </template>
                            <template x-if="file && ! file.isImage">
                                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300">
                                    <x-filament::icon icon="heroicon-o-document-text" class="h-8 w-8" />
                                </span>
                            </template>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[13.5px] font-medium text-gray-900 dark:text-white" x-text="file?.name"></div>
                                <div class="text-[12px] text-gray-500" x-text="uploading ? 'Subiendo… ' + progress + '%' : file?.size + ' · listo para enviar'"></div>
                                <div x-show="uploading" class="mt-1 h-1 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                    <div class="h-full rounded-full bg-emerald-500 transition-all" x-bind:style="'width: ' + progress + '%'"></div>
                                </div>
                            </div>
                            <button type="button" x-on:click="clearFile()" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10" aria-label="Quitar adjunto">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="relative mx-auto flex max-w-3xl items-end gap-1.5">
                            <div class="relative">
                                <button type="button" x-on:click="showReplies = ! showReplies" title="Respuestas rápidas"
                                    class="flex h-11 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-gray-200 dark:hover:bg-white/10"
                                    x-bind:class="showReplies && 'bg-gray-200 text-emerald-600 dark:bg-white/10'">
                                    <x-filament::icon icon="heroicon-m-bolt" class="h-5 w-5" />
                                </button>
                                <div x-show="showReplies" x-cloak x-transition.origin.bottom.left x-on:click.outside="showReplies = false"
                                    class="absolute bottom-14 left-0 z-20 w-80 overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-800 dark:ring-white/10">
                                    <div class="border-b border-gray-100 px-3 py-2 text-[12px] font-semibold text-gray-500 dark:border-white/10">Respuestas rápidas</div>
                                    <template x-for="reply in replies" :key="reply.label">
                                        <button type="button" x-on:click="useReply(reply.text)" class="block w-full px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-white/5">
                                            <span class="block text-[13px] font-medium text-gray-900 dark:text-white" x-text="reply.label"></span>
                                            <span class="block truncate text-[12px] text-gray-500 dark:text-gray-400" x-text="reply.text"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <button type="button" x-on:click="pick()" title="Adjuntar foto o archivo"
                                class="flex h-11 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-gray-200 dark:hover:bg-white/10">
                                <x-filament::icon icon="heroicon-m-paper-clip" class="h-5 w-5" />
                            </button>
                            <input type="file" x-ref="file" class="hidden"
                                accept="image/jpeg,image/png,image/webp,.pdf,.doc,.docx,.xls,.xlsx"
                                x-on:change="attach($event.target.files[0])">
                            <textarea
                                x-ref="input"
                                x-model="text"
                                x-on:input="resize()"
                                x-on:paste="pasted($event)"
                                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); send() }"
                                x-bind:placeholder="file ? 'Agrega un comentario (opcional)' : 'Escribe un mensaje'"
                                rows="1"
                                maxlength="4000"
                                class="block max-h-40 min-h-[44px] flex-1 resize-none rounded-2xl border-0 bg-white px-4 py-[11px] text-[14.5px] leading-snug text-gray-900 shadow-sm ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-emerald-500/50 dark:bg-white/5 dark:text-white dark:ring-white/10"
                            ></textarea>
                            <button type="button" x-on:click="send()" x-bind:disabled="uploading || (! file && text.trim() === '')" title="Enviar (Enter)"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-gray-300 dark:disabled:bg-white/10">
                                <x-filament::icon icon="heroicon-m-paper-airplane" class="h-5 w-5" />
                            </button>
                        </div>
                        <div class="mx-auto mt-1 max-w-3xl pl-24 text-[11px] text-gray-400">Enter para enviar · Shift + Enter salto de línea · arrastra o pega una foto para adjuntarla</div>
                    </footer>
                @else
                    <footer class="shrink-0 border-t border-gray-200 bg-gray-50 px-4 py-3 text-center text-[13px] text-gray-500 dark:border-white/10 dark:bg-gray-900">
                        Conversación finalizada. Si el cliente vuelve a escribir, se abre una nueva.
                    </footer>
                @endif
            @else
                <div class="flex flex-1 flex-col items-center justify-center gap-4 bg-gray-50 p-10 text-center dark:bg-gray-950/40">
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-500/10">
                        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-10 w-10 text-emerald-600 dark:text-emerald-400" />
                    </span>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">Bandeja de pedidos por WhatsApp</div>
                    <p class="max-w-sm text-sm text-gray-500 dark:text-gray-400">
                        En <strong>Por atender</strong> están los clientes que {{ $agentName }} pasó al equipo. Elige un chat para responder; al escribir, la conversación queda a tu nombre.
                    </p>
                </div>
            @endif
        </section>

        {{-- ═══════════ Ficha ═══════════ --}}
        @if ($conversation)
            <aside class="hidden min-h-0 overflow-y-auto border-l border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-950/40" x-bind:class="panelOpen && 'xl:block'">
                <div class="-mb-2 flex justify-end">
                    <button type="button" x-on:click="panelOpen = false" title="Ocultar la ficha del cliente"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/10">
                        <x-filament::icon icon="heroicon-m-chevron-double-right" class="h-5 w-5" />
                    </button>
                </div>
                @include('filament.whatsapp-conversations.partials.details', ['conversation' => $conversation, 'agentName' => $agentName])
            </aside>

            <div x-show="details" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-gray-950/40 xl:hidden" x-on:click="details = false"></div>
            <aside x-show="details" x-cloak
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                class="fixed inset-y-0 right-0 z-50 w-full max-w-sm overflow-y-auto bg-gray-50 p-3 shadow-2xl xl:hidden dark:bg-gray-900">
                <div class="mb-1 flex justify-end">
                    <button type="button" class="rounded-full p-1.5 text-gray-500 hover:bg-gray-200 dark:hover:bg-white/10" x-on:click="details = false" aria-label="Cerrar ficha">
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>
                </div>
                @include('filament.whatsapp-conversations.partials.details', ['conversation' => $conversation, 'agentName' => $agentName])
            </aside>
        @endif
    </div>
</x-filament-panels::page>
