@props([
    'heading' => null,
    'logo' => true,
    'subheading' => null,
    'slogan' => null,
])

<header class="fi-simple-header">
    @if ($logo)
        <x-filament-panels::logo />
    @endif

    @if (filled($slogan))
        <p class="fi-simple-header-slogan">
            @if ($slogan instanceof \Illuminate\Contracts\Support\Htmlable)
                {!! $slogan->toHtml() !!}
            @else
                {{ $slogan }}
            @endif
        </p>
    @endif

    @if (filled($heading))
        <h1 class="fi-simple-header-heading">
            {{ $heading }}
        </h1>
    @endif

    @if (filled($subheading))
        <p class="fi-simple-header-subheading">
            {{ $subheading }}
        </p>
    @endif
</header>
