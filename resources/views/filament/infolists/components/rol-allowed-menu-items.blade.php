@php
    /** @var list<array{name: string, count: int, items: list<string>}> $groups */
    $groups = $groups ?? [];
    $total = (int) ($total ?? 0);
    $isFullAccess = (bool) ($isFullAccess ?? false);
    $isEmpty = (bool) ($isEmpty ?? false);
@endphp

<div class="farmadoc-rol-perms">
    @if ($isEmpty)
        <div class="farmadoc-rol-perms__empty">
            <p class="farmadoc-rol-perms__empty-title">Sin permisos de menú</p>
            <p class="farmadoc-rol-perms__empty-sub">Este rol no tiene módulos asignados.</p>
        </div>
    @else
        <div class="farmadoc-rol-perms__summary">
            @if ($isFullAccess)
                <span class="farmadoc-rol-perms__pill farmadoc-rol-perms__pill--success">Acceso completo</span>
            @else
                <span class="farmadoc-rol-perms__pill farmadoc-rol-perms__pill--info">
                    {{ $total }} {{ $total === 1 ? 'módulo' : 'módulos' }}
                </span>
            @endif
            <span class="farmadoc-rol-perms__summary-meta">
                {{ count($groups) }} {{ count($groups) === 1 ? 'grupo' : 'grupos' }}
            </span>
        </div>

        <div class="farmadoc-rol-perms__grid">
            @foreach ($groups as $group)
                <section class="farmadoc-rol-perms__group" aria-label="{{ $group['name'] }}">
                    <header class="farmadoc-rol-perms__group-head">
                        <h3 class="farmadoc-rol-perms__group-title">{{ $group['name'] }}</h3>
                        <span class="farmadoc-rol-perms__group-count">{{ $group['count'] }}</span>
                    </header>
                    <ul class="farmadoc-rol-perms__chips">
                        @foreach ($group['items'] as $item)
                            <li class="farmadoc-rol-perms__chip">{{ $item }}</li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</div>
