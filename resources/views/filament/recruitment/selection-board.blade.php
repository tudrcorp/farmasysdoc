@php
    use App\Enums\RecruitmentStage;

    $applicationsByStage = $this->getApplicationsByStage();
@endphp

<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        <label class="flex max-w-md flex-col gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
            Solicitud de personal
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="requestId">
                    <option value="">Todas las solicitudes aprobadas</option>
                    @foreach ($this->getRequestOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            Arrastra cada tarjeta a su etapa. Para contratar o descartar, abre la tarjeta del candidato.
        </p>

        <div
            x-data="{ dragging: null, over: null }"
            class="flex gap-3 overflow-x-auto pb-3"
        >
            @foreach (RecruitmentStage::cases() as $stage)
                @php
                    $applications = $applicationsByStage->get($stage->value, collect());
                    $droppable = $stage->isOpen();
                @endphp

                <section
                    wire:key="stage-{{ $stage->value }}"
                    aria-label="{{ $stage->label() }}"
                    @if ($droppable)
                        x-on:dragover.prevent="over = '{{ $stage->value }}'"
                        x-on:dragleave="over = null"
                        x-on:drop.prevent="over = null; if (dragging) { $wire.moveApplication(dragging, '{{ $stage->value }}') }"
                    @endif
                    :class="over === '{{ $stage->value }}' ? 'ring-2 ring-primary-500' : ''"
                    class="flex min-h-64 w-64 shrink-0 flex-col gap-2 rounded-xl bg-gray-100 p-3 dark:bg-white/5"
                >
                    <header class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $stage->label() }}</h3>
                        <x-filament::badge :color="$stage->filamentColor()">{{ $applications->count() }}</x-filament::badge>
                    </header>

                    @foreach ($applications as $application)
                        <article
                            wire:key="application-{{ $application->id }}"
                            @if ($stage !== RecruitmentStage::Hired)
                                draggable="true"
                                x-on:dragstart="dragging = {{ $application->id }}"
                                x-on:dragend="dragging = null"
                            @endif
                            class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                        >
                            <a href="{{ \App\Filament\Resources\Hr\Recruitment\Applications\RecruitmentApplicationResource::getUrl('view', ['record' => $application]) }}" class="flex items-center gap-2">
                                @if ($photoUrl = $application->candidate->photoUrl())
                                    <img src="{{ $photoUrl }}" alt="" class="size-9 rounded-full object-cover">
                                @else
                                    <span class="flex size-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 dark:bg-white/10">
                                        <x-filament::icon icon="heroicon-m-user" class="size-5" />
                                    </span>
                                @endif
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">{{ $application->candidate->fullName() }}</span>
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $application->recruitmentRequest->optionLabel() }}</span>
                                </span>
                            </a>

                            @if ($application->rating || $application->interview_at)
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($application->rating)
                                        {{ $application->rating }}/5
                                    @endif
                                    @if ($application->interview_at)
                                        · Entrevista {{ $application->interview_at->format('d/m h:i a') }}
                                    @endif
                                </p>
                            @endif

                            @if ($droppable)
                                <label class="mt-2 block">
                                    <span class="sr-only">Mover a otra etapa</span>
                                    <select
                                        x-on:change="$wire.moveApplication({{ $application->id }}, $event.target.value)"
                                        class="w-full rounded-md border-gray-200 bg-transparent py-1 text-xs text-gray-600 dark:border-white/10 dark:text-gray-300"
                                    >
                                        @foreach (RecruitmentStage::open() as $option)
                                            <option value="{{ $option->value }}" @selected($option === $stage)>{{ $option->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        </article>
                    @endforeach
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
