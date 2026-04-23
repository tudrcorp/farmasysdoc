@props([
    /** @var list<array{id: string, label: string, hint: string, href: string, force_full_page: bool}> $items */
    'items' => [],
])

@if ($items !== [])
<nav class="farmadoc-ios-shortcuts" aria-label="Accesos directos">
    <ul class="farmadoc-ios-shortcuts__list">
        @foreach ($items as $index => $item)
            <li class="farmadoc-ios-shortcuts__item">
                <a
                    href="{{ $item['href'] }}"
                    @if (! empty($item['force_full_page']))
                        onclick="event.preventDefault(); window.location.assign(@js($item['href']));"
                    @endif
                    class="farmadoc-ios-shortcuts__segment farmadoc-ios-shortcuts__segment--{{ $index % 6 }}"
                >
                    <span class="farmadoc-ios-shortcuts__bubble" aria-hidden="true">{{ $index + 1 }}</span>
                    <span class="farmadoc-ios-shortcuts__copy">
                        <span class="farmadoc-ios-shortcuts__title">{{ $item['label'] }}</span>
                        <span class="farmadoc-ios-shortcuts__hint">{{ $item['hint'] }}</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
@endif
