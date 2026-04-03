@php
    /** @var array<int, array{name: string, meta: string, quantity: string}> $rows */
    $rows = $rows ?? [];
@endphp

<div class="fi-ios-transfer-items" data-fi-ios-transfer-items>
    @if (count($rows) === 0)
        <div class="fi-ios-transfer-items__empty">
            <div class="fi-ios-transfer-items__empty-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="fi-ios-transfer-items__empty-svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                </svg>
            </div>
            <p class="fi-ios-transfer-items__empty-title">Sin productos</p>
            <p class="fi-ios-transfer-items__empty-sub">Este traslado no tiene líneas registradas.</p>
        </div>
    @else
        <div class="fi-ios-transfer-items__meta">
            <span class="fi-ios-transfer-items__count">{{ count($rows) }} {{ count($rows) === 1 ? 'producto' : 'productos' }}</span>
        </div>
        <div class="fi-ios-transfer-items__group" role="list">
            @foreach ($rows as $index => $row)
                @php
                    $isFirst = $index === 0;
                    $isLast = $index === count($rows) - 1;
                    $glyph = mb_strtoupper(mb_substr((string) $row['name'], 0, 1));
                @endphp
                <div
                    class="fi-ios-transfer-items__row @if ($isFirst) fi-ios-transfer-items__row--first @endif @if ($isLast) fi-ios-transfer-items__row--last @endif"
                    role="listitem"
                >
                    <div class="fi-ios-transfer-items__avatar" aria-hidden="true">
                        <span class="fi-ios-transfer-items__avatar-text">{{ $glyph }}</span>
                    </div>
                    <div class="fi-ios-transfer-items__body">
                        <div class="fi-ios-transfer-items__text">
                            <p class="fi-ios-transfer-items__title">{{ $row['name'] }}</p>
                            <p class="fi-ios-transfer-items__subtitle">{{ $row['meta'] }}</p>
                        </div>
                        <div class="fi-ios-transfer-items__value">
                            <span class="fi-ios-transfer-items__qty">{{ $row['quantity'] }}</span>
                        </div>
                    </div>
                </div>
                @if (! $isLast)
                    <div class="fi-ios-transfer-items__separator" aria-hidden="true"></div>
                @endif
            @endforeach
        </div>
    @endif
</div>
