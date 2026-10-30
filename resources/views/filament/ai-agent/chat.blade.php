@php
    use App\Models\AiAgentMessage;
    use Illuminate\Support\Str;

    $messages = $this->getMessages();
    $budget = $this->getBudget();
    $spent = $budget->spentThisMonthUsd();
    $conversations = $this->getConversations();
    $chipClass = 'inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 transition hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20';
@endphp

<x-filament-panels::page>
    <div
        x-data="{
            pending: '',
            sending: false,
            drawer: false,
            submit() {
                const question = $wire.draft.trim();
                if (question === '' || this.sending || $wire.awaitingAnswer) { return; }
                this.pending = question;
                this.sending = true;
                this.scrollDown();
                this.$nextTick(() => this.resize());
                $wire.draft = '';
                $wire.send(question).finally(() => { this.pending = ''; this.sending = false; this.scrollDown(); });
            },
            resize() {
                const box = this.$refs.box;
                box.style.height = 'auto';
                box.style.height = Math.min(box.scrollHeight, 180) + 'px';
            },
            scrollDown() {
                this.$nextTick(() => { const area = this.$refs.thread; area.scrollTop = area.scrollHeight; });
            },
        }"
        x-init="scrollDown(); new MutationObserver(() => scrollDown()).observe($refs.thread, { childList: true, subtree: true, characterData: true })"
        x-on:keydown.escape.window="drawer = false"
        class="relative flex h-[calc(100dvh-11rem)] min-h-[32rem] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
        <header class="flex items-center justify-between gap-2 border-b border-gray-100 px-3 py-2 dark:border-white/10">
            <div class="flex items-center gap-1">
                <x-filament::icon-button
                    icon="heroicon-m-bars-3"
                    color="gray"
                    label="Conversaciones"
                    x-on:click="drawer = true"
                    x-bind:aria-expanded="drawer"
                    aria-controls="ai-agent-conversations"
                />
                <x-filament::icon-button
                    icon="heroicon-m-pencil-square"
                    color="gray"
                    label="Nueva conversación"
                    wire:click="startConversation"
                />
            </div>

            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span>US$ {{ number_format($spent, 2, ',', '.') }} de {{ number_format($budget->limitUsd(), 2, ',', '.') }} este mes</span>
                <span class="hidden h-1.5 w-16 overflow-hidden rounded-full bg-gray-100 sm:block dark:bg-white/10">
                    <span
                        @class(['block h-full rounded-full', 'bg-success-600' => ! $budget->isNearLimit(), 'bg-warning-500' => $budget->isNearLimit()])
                        style="width: {{ $budget->limitUsd() > 0 ? min(100, $spent / $budget->limitUsd() * 100) : 100 }}%"
                    ></span>
                </span>
            </div>
        </header>

        <div
            x-show="drawer"
            x-cloak
            x-transition.opacity
            x-on:click="drawer = false"
            class="absolute inset-0 z-10 bg-gray-950/30"
        ></div>

        <aside
            id="ai-agent-conversations"
            x-show="drawer"
            x-cloak
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="absolute inset-y-0 left-0 z-20 flex w-72 max-w-[85%] flex-col gap-3 border-r border-gray-100 bg-white p-3 dark:border-white/10 dark:bg-gray-900"
        >
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Conversaciones</h2>
                <x-filament::icon-button icon="heroicon-m-x-mark" color="gray" label="Cerrar" x-on:click="drawer = false" />
            </div>

            <nav aria-label="Conversaciones" class="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto">
                @forelse ($conversations as $conversation)
                    <div
                        wire:key="conversation-{{ $conversation->id }}"
                        @class([
                            'group flex items-center rounded-xl transition',
                            'bg-gray-100 dark:bg-white/10' => $conversationId === $conversation->id,
                            'hover:bg-gray-50 dark:hover:bg-white/5' => $conversationId !== $conversation->id,
                        ])
                    >
                        <button
                            type="button"
                            wire:click="openConversation({{ $conversation->id }})"
                            x-on:click="drawer = false"
                            class="min-w-0 flex-1 truncate px-3 py-2.5 text-left text-sm text-gray-700 dark:text-gray-200"
                        >{{ $conversation->title }}</button>
                        <x-filament::icon-button
                            icon="heroicon-m-trash"
                            color="gray"
                            size="sm"
                            label="Borrar conversación"
                            wire:click="deleteConversation({{ $conversation->id }})"
                            wire:confirm="¿Borrar esta conversación?"
                        />
                    </div>
                @empty
                    <p class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Tus conversaciones aparecerán aquí.</p>
                @endforelse
            </nav>
        </aside>

        <div x-ref="thread" class="min-h-0 flex-1 overflow-y-auto px-4 py-6">
            <div class="mx-auto flex max-w-[44rem] flex-col gap-6">
                @if ($budget->isNearLimit())
                    <x-filament::callout icon="heroicon-m-exclamation-triangle" color="warning" heading="El agente se acerca a su límite de gasto del mes." />
                @endif

                @if ($messages->isEmpty())
                    <div x-show="pending === ''" class="flex flex-col items-center py-8 text-center">
                        <span class="flex size-12 items-center justify-center rounded-2xl bg-primary-400 text-gray-950">
                            <x-filament::icon icon="heroicon-m-sparkles" class="size-6" />
                        </span>
                        <h2 class="mt-4 text-2xl font-semibold text-gray-950 dark:text-white">¿Qué quieres saber del negocio?</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pregunta, pide un reporte o busca ideas para mejorar.</p>

                        <div class="mt-6 grid w-full gap-2 sm:grid-cols-2">
                            @foreach ($this->getSuggestions() as $suggestion)
                                <button
                                    type="button"
                                    wire:key="suggestion-{{ $loop->index }}"
                                    x-on:click="$wire.draft = @js($suggestion['question']); submit()"
                                    class="rounded-xl p-3 text-left ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:ring-white/10 dark:hover:bg-white/5"
                                >
                                    <span class="block text-sm font-medium text-gray-950 dark:text-white">{{ $suggestion['title'] }}</span>
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $suggestion['question'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @foreach ($messages as $message)
                    @if ($message->role === AiAgentMessage::ROLE_USER)
                        <article wire:key="message-{{ $message->id }}" class="ml-auto max-w-[85%] rounded-2xl rounded-br-md bg-success-600 px-4 py-2.5 text-sm text-white">
                            <p class="whitespace-pre-wrap break-words">{{ $message->content }}</p>
                        </article>
                    @else
                        <article wire:key="message-{{ $message->id }}" class="flex gap-3" x-data="{ copied: false }">
                            <span @class([
                                'flex size-7 shrink-0 items-center justify-center rounded-lg',
                                'bg-primary-400 text-gray-950' => ! $message->failed,
                                'bg-danger-500 text-white' => $message->failed,
                            ])>
                                <x-filament::icon :icon="$message->failed ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-sparkles'" class="size-4" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div x-ref="answer" class="fi-prose max-w-none overflow-x-auto text-sm">
                                    {!! Str::markdown($message->content, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                    @unless ($message->failed)
                                        <button
                                            type="button"
                                            class="{{ $chipClass }}"
                                            x-on:click="navigator.clipboard.writeText($refs.answer.innerText); copied = true; setTimeout(() => copied = false, 1500)"
                                            x-text="copied ? 'Copiado' : 'Copiar'"
                                        ></button>
                                    @endunless

                                    @foreach ($message->reports as $report)
                                        <a class="{{ $chipClass }}" href="{{ route('ai-agent.report', [$report, 'xlsx']) }}" title="{{ $report->title }}">Excel</a>
                                        <a class="{{ $chipClass }}" href="{{ route('ai-agent.report', [$report, 'pdf']) }}" title="{{ $report->title }}">PDF</a>
                                    @endforeach

                                    @if (filled($message->tool_calls))
                                        <details class="w-full">
                                            <summary class="{{ $chipClass }} w-fit cursor-pointer list-none">Qué consultó ({{ count($message->tool_calls) }})</summary>
                                            <ul class="mt-2 flex flex-col gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                @foreach ($message->tool_calls as $call)
                                                    <li class="rounded-lg bg-gray-50 p-2 dark:bg-white/5">
                                                        <strong>{{ $call['tool'] }}</strong>
                                                        @if (filled($call['error'] ?? null))
                                                            <span class="text-danger-600 dark:text-danger-400">· {{ $call['error'] }}</span>
                                                        @endif
                                                        <code class="mt-1 block break-all">{{ json_encode($call['arguments'], JSON_UNESCAPED_UNICODE) }}</code>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endif
                @endforeach

                <template x-if="pending !== ''">
                    <article class="ml-auto max-w-[85%] rounded-2xl rounded-br-md bg-success-600 px-4 py-2.5 text-sm text-white">
                        <p class="whitespace-pre-wrap break-words" x-text="pending"></p>
                    </article>
                </template>

                <article x-show="pending !== '' || $wire.awaitingAnswer" x-cloak class="flex gap-3">
                    <span class="flex size-7 shrink-0 animate-pulse items-center justify-center rounded-lg bg-primary-400 text-gray-950">
                        <x-filament::icon icon="heroicon-m-sparkles" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-500 dark:text-gray-400" wire:stream="agent-status">Pensando…</p>
                        <div class="fi-prose max-w-none overflow-x-auto text-sm" wire:stream="agent-answer"></div>
                    </div>
                </article>
            </div>
        </div>

        <form x-on:submit.prevent="submit()" class="px-4 pb-3">
            <div class="mx-auto flex max-w-[44rem] items-end gap-2 rounded-2xl bg-white p-2 shadow-md ring-1 ring-gray-950/10 transition focus-within:ring-2 focus-within:ring-success-600 dark:bg-gray-800 dark:ring-white/10">
                <label class="sr-only" for="ai-agent-draft">Tu pregunta</label>
                <textarea
                    id="ai-agent-draft"
                    x-ref="box"
                    wire:model="draft"
                    rows="1"
                    maxlength="4000"
                    placeholder="Pregunta sobre el negocio…"
                    class="block max-h-44 min-h-9 w-full resize-none border-0 bg-transparent px-2 py-1.5 text-sm text-gray-950 outline-none placeholder:text-gray-400 focus:ring-0 dark:text-white"
                    x-on:input="resize()"
                    x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); submit() }"
                ></textarea>
                <button
                    type="submit"
                    aria-label="Enviar"
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-success-600 text-white transition hover:bg-success-700 disabled:opacity-50"
                    x-bind:disabled="sending || $wire.awaitingAnswer"
                >
                    <x-filament::icon icon="heroicon-m-arrow-up" class="size-5" />
                </button>
            </div>
            <p class="mt-2 text-center text-xs text-gray-400">El agente solo lee datos y puede equivocarse. Revisa las cifras importantes antes de decidir.</p>
        </form>
    </div>
</x-filament-panels::page>
