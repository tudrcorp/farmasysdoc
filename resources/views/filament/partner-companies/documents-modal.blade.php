@php
    use App\Enums\PartnerCompanyDocumentType;

    /** @var \Illuminate\Support\Collection<int, \App\Models\PartnerCompanyDocument> $documents */
    $expectedTypes = [
        PartnerCompanyDocumentType::AgreementLetter,
        PartnerCompanyDocumentType::Rif,
        PartnerCompanyDocumentType::LegalRepresentativeId,
        PartnerCompanyDocumentType::CommercialRegistry,
    ];
    $loadedTypes = $documents->pluck('type')->filter()->map(fn ($type) => $type->value)->unique()->all();
@endphp

<div class="space-y-6">
    <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Expediente básico</h3>
        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($expectedTypes as $type)
                @php($isLoaded = in_array($type->value, $loadedTypes, true))
                <li class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-white/10">
                    <span class="text-gray-700 dark:text-gray-200">{{ $type->label() }}</span>
                    <x-filament::badge :color="$isLoaded ? 'success' : 'warning'" size="sm">
                        {{ $isLoaded ? 'Cargado' : 'Pendiente' }}
                    </x-filament::badge>
                </li>
            @endforeach
        </ul>
    </section>

    <section>
        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Archivos adjuntos</h3>

        @if ($documents->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-white/15 dark:text-gray-400">
                Este aliado no tiene documentos cargados.
                <a href="{{ $editUrl }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">Cargar desde Editar</a>.
            </div>
        @else
            <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
                @foreach ($documents as $document)
                    <li class="flex flex-col gap-2 px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-950 dark:text-white">
                                {{ $document->type?->label() ?? 'Documento' }}
                            </p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $document->displayName() }}">
                                {{ $document->displayName() }}
                            </p>
                            @if ($document->expires_at)
                                <p class="mt-1 text-xs {{ $document->isExpired() ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $document->isExpired() ? 'Vencido el' : 'Vence el' }} {{ $document->expires_at->format('d/m/Y') }}
                                </p>
                            @endif
                            @if (filled($document->notes))
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $document->notes }}</p>
                            @endif
                        </div>
                        <div class="shrink-0">
                            @if ($document->fileExists())
                                <x-filament::button
                                    tag="a"
                                    :href="$document->downloadUrl()"
                                    target="_blank"
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-o-arrow-top-right-on-square"
                                >
                                    Abrir
                                </x-filament::button>
                            @else
                                <x-filament::badge color="danger" size="sm">Archivo no encontrado</x-filament::badge>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
