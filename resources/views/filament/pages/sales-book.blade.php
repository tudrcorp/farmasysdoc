@php
    use App\Support\Fiscal\SalesBookLayout;
    $book = $this->book;
    $columns = SalesBookLayout::columns();
@endphp

<x-filament-panels::page>
    <div class="w-full max-w-none space-y-6">
        <x-filament::section>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <label class="space-y-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Sucursal</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="branchId">
                            @foreach ($this->branchOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="space-y-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Quincena</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="periodKey">
                            @foreach ($this->periodOptions() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <div class="flex items-end">
                    @if ($book === null)
                        <x-filament::badge color="gray">Seleccione sucursal y quincena</x-filament::badge>
                    @elseif ($book['is_closed'])
                        <x-filament::badge color="success" icon="heroicon-m-lock-closed">
                            Cerrado · versión {{ $book['closed']['version'] ?? '' }} · {{ $book['closed']['closed_at'] ?? '' }}
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="warning" icon="heroicon-m-pencil-square">Borrador (sin cerrar)</x-filament::badge>
                    @endif
                </div>
            </div>
        </x-filament::section>

        @if ($book !== null)
            @if ($book['issues'] !== [])
                <x-filament::section>
                    <x-slot name="heading">Validaciones</x-slot>
                    <x-slot name="description">Los errores impiden cerrar el libro. Las advertencias deben revisarse.</x-slot>

                    <ul class="space-y-2 text-sm">
                        @foreach ($book['issues'] as $issue)
                            <li @class([
                                'rounded-lg px-3 py-2',
                                'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-300' => $issue['level'] === 'error',
                                'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300' => $issue['level'] !== 'error',
                            ])>
                                <span class="font-semibold">{{ $issue['level'] === 'error' ? 'Error' : 'Advertencia' }}:</span>
                                {{ $issue['message'] }}
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endif

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                @foreach ([
                    ['Total ventas (Bs)', $book['totals']['total']],
                    ['Base imponible (Bs)', $book['summary']['item_42'] + $book['summary']['item_442'] + $book['summary']['item_443']],
                    ['Débito fiscal IVA (Bs)', $book['summary']['item_47']],
                    ['IVA retenido por clientes (Bs)', $book['summary']['item_66']],
                ] as [$label, $value])
                    <x-filament::section compact>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ SalesBookLayout::money($value) }}</div>
                    </x-filament::section>
                @endforeach
            </div>

            @if (($book['printers'] ?? []) !== [])
                <x-filament::section>
                    <x-slot name="heading">Reportes Z por máquina</x-slot>
                    <div class="flex flex-wrap gap-3 text-sm">
                        @foreach ($book['printers'] as $printer)
                            <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $printer['name'] }} · {{ $printer['serial'] }}</div>
                                <div class="text-gray-600 dark:text-gray-300">
                                    Z {{ str_pad((string) $printer['first_z'], 4, '0', STR_PAD_LEFT) }} al {{ str_pad((string) $printer['last_z'], 4, '0', STR_PAD_LEFT) }}
                                    · {{ $printer['count'] }} reporte(s)
                                    @if ($printer['missing'] !== [])
                                        · <span class="text-danger-600 dark:text-danger-400">faltan {{ count($printer['missing']) }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            <x-filament::section>
                <x-slot name="heading">{{ $book['header']['company_name'] }} · {{ $book['header']['branch_name'] }}</x-slot>
                <x-slot name="description">RIF {{ $book['header']['company_rif'] }} · {{ $book['header']['month_name'] }} {{ $book['header']['year'] }} · {{ $book['header']['half_label'] }} · {{ $book['header']['range_label'] }}</x-slot>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-xs dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                @foreach ($columns as $column)
                                    <th class="px-2 py-2 text-center align-bottom font-medium text-gray-600 dark:text-gray-300">{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @forelse ($book['rows'] as $row)
                                <tr @class(['bg-primary-50/40 dark:bg-primary-500/5' => $row['taxpayer_type'] === 'CONTRIBUYENTE'])>
                                    @foreach ($columns as $column)
                                        @php $value = SalesBookLayout::value($row, $column['key'], $book['header']); @endphp
                                        <td @class([
                                            'whitespace-nowrap px-2 py-1 text-gray-800 dark:text-gray-200',
                                            'text-right tabular-nums' => $column['money'] ?? false,
                                            'text-danger-600 dark:text-danger-400' => ($column['money'] ?? false) && $value < 0,
                                        ])>
                                            {{ ($column['money'] ?? false) ? SalesBookLayout::money($value) : $value }}
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columns) }}" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                        No hay reportes Z ni retenciones en esta quincena.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold dark:bg-white/5">
                            <tr>
                                @foreach ($columns as $index => $column)
                                    <td class="whitespace-nowrap px-2 py-2 text-right tabular-nums text-gray-900 dark:text-white">
                                        {{ ($column['money'] ?? false) ? SalesBookLayout::money($book['totals'][$column['key']]) : ($index === 6 ? 'TOTALES' : '') }}
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Resumen de ventas y débitos fiscales (Forma 30)</x-slot>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr class="text-gray-600 dark:text-gray-300">
                                <th class="px-3 py-2 text-left font-medium">Débitos fiscales</th>
                                <th class="px-3 py-2 font-medium">Ítem</th>
                                <th class="px-3 py-2 text-right font-medium">Base imponible</th>
                                <th class="px-3 py-2 font-medium">Ítem</th>
                                <th class="px-3 py-2 text-right font-medium">Débito fiscal</th>
                                <th class="px-3 py-2 font-medium">Ítem</th>
                                <th class="px-3 py-2 text-right font-medium">IVA retenido</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach (SalesBookLayout::summaryLines() as [$label, $baseItem, $baseKey, $taxItem, $taxKey, $retItem, $retKey])
                                <tr class="text-gray-800 dark:text-gray-200">
                                    <td class="px-3 py-2">{{ $label }}</td>
                                    <td class="px-3 py-2 text-center">{{ $baseItem }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $baseKey ? SalesBookLayout::money($book['summary'][$baseKey]) : '' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $taxItem }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $taxKey ? SalesBookLayout::money($book['summary'][$taxKey]) : '' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $retItem }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $retKey ? SalesBookLayout::money($book['summary'][$retKey]) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            @if ($this->versions !== [])
                <x-filament::section>
                    <x-slot name="heading">Historial de cierres</x-slot>
                    <ul class="divide-y divide-gray-100 text-sm dark:divide-white/10">
                        @foreach ($this->versions as $version)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                                <div class="text-gray-800 dark:text-gray-200">
                                    <span class="font-semibold">Versión {{ $version->version }}</span>
                                    · {{ $this->statusLabel($version) }}
                                    · cerrado {{ $version->closed_at?->format('d/m/Y h:i A') }} por {{ $version->closed_by }}
                                    @if ($version->reopened_at)
                                        · reabierto {{ $version->reopened_at->format('d/m/Y h:i A') }} por {{ $version->reopened_by }}: «{{ $version->reopen_reason }}»
                                    @endif
                                    <div class="font-mono text-xs text-gray-500 dark:text-gray-400">SHA-256 {{ $version->content_hash }}</div>
                                </div>
                                <div class="flex gap-2">
                                    <x-filament::link :href="$this->downloadVersionUrl($version->id, 'pdf')" target="_blank" icon="heroicon-m-document-arrow-down">PDF</x-filament::link>
                                    <x-filament::link :href="$this->downloadVersionUrl($version->id, 'xlsx')" target="_blank" icon="heroicon-m-table-cells">Excel</x-filament::link>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
