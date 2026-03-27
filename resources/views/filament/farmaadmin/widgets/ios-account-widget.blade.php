@php
    use Illuminate\Support\Str;

    $displayName = $user ? filament()->getUserName($user) : '';
    $firstName = $user
        ? Str::of($user->name ?? '')->trim()->explode(' ')->filter()->first()
        : '';
@endphp

<x-filament-widgets::widget class="fi-wi-widget fi-farmaadmin-account-widget">
    <div class="fi-farmaadmin-account-widget__shell">
        <div class="fi-farmaadmin-account-widget__glow" aria-hidden="true"></div>
        <div class="fi-farmaadmin-account-widget__wash" aria-hidden="true"></div>
        <div class="fi-farmaadmin-account-widget__rim" aria-hidden="true"></div>

        <div class="fi-farmaadmin-account-widget__content">
            @if ($user)
                <x-filament-panels::avatar.user
                    class="fi-farmaadmin-account-widget__avatar shrink-0"
                    size="sm"
                    :user="$user"
                    loading="lazy"
                />
            @endif

            <div class="fi-farmaadmin-account-widget__text">
                <p class="fi-farmaadmin-account-widget__eyebrow">
                    {{ __('Bienvenido a :app', ['app' => config('app.name')]) }}
                </p>
                <h2 class="fi-farmaadmin-account-widget__title">
                    @if (filled($firstName))
                        {{ __('Hola, :name', ['name' => $firstName]) }}
                    @else
                        {{ $displayName }}
                    @endif
                </h2>
                @if ($user && filled($firstName) && $displayName !== $firstName)
                    <p class="fi-farmaadmin-account-widget__subtitle">
                        {{ $displayName }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
