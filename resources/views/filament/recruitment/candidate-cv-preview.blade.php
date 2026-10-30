@php
    /** @var \App\Models\RecruitmentCandidate $record */
    $record = $getRecord();
    $url = route('recruitment.candidate-cv', $record);
    $extension = $record->cvExtension();
@endphp

<div class="flex flex-col gap-3">
    @if ($extension === 'pdf')
        <iframe src="{{ $url }}" title="Curriculum vitae" class="h-[75vh] w-full rounded-lg border border-gray-200 dark:border-white/10"></iframe>
    @elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true))
        <img src="{{ $url }}" alt="Curriculum vitae" class="max-h-[75vh] w-full rounded-lg border border-gray-200 object-contain dark:border-white/10">
    @else
        <p class="text-sm text-gray-600 dark:text-gray-300">Este formato no se puede previsualizar aquí. Ábrelo para compararlo con los datos.</p>
    @endif

    <x-filament::link :href="$url" target="_blank" icon="heroicon-m-arrow-top-right-on-square">
        {{ $record->cv_original_name ?: 'Abrir CV' }}
    </x-filament::link>
</div>
