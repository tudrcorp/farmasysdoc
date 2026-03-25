@php
    use Illuminate\Support\Str;

    $displayName = $user ? filament()->getUserName($user) : '';
    $firstName = $user
        ? Str::of($user->name ?? '')->trim()->explode(' ')->filter()->first()
        : '';
@endphp

<x-filament-widgets::widget
    class="fi-wi-widget fi-ios-account-widget"
>
    <div
        class="fi-ios-account-shell relative overflow-hidden rounded-[18px] border border-[#C6C6C8]/40 shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.03)] ring-1 ring-black/[0.03] dark:border-white/[0.10] dark:shadow-[0_1px_0_rgba(255,255,255,0.04)_inset] dark:ring-white/[0.06]"
        style="font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'SF Pro Display', 'Segoe UI', system-ui, sans-serif; -webkit-font-smoothing: antialiased;"
    >
        {{-- Tema Farmaadmin: primary #FCE422, info #18ACB2, success #0E949A --}}
        <div
            class="pointer-events-none absolute inset-0 bg-gradient-to-br from-[#FCE422]/38 via-[#18ACB2]/26 to-[#0E949A]/30 dark:from-[#FCE422]/16 dark:via-[#18ACB2]/18 dark:to-[#0E949A]/22"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute inset-0 bg-white/80 backdrop-blur-md dark:bg-[#1C1C1E]/86 dark:backdrop-blur-md"
            aria-hidden="true"
        ></div>

        <div
            class="pointer-events-none absolute inset-x-0 top-0 z-[1] h-px bg-gradient-to-r from-transparent via-white/80 to-transparent dark:via-white/15"
            aria-hidden="true"
        ></div>

        <div class="relative z-[1] flex items-center gap-3 px-3 py-2.5">
            @if ($user)
                <x-filament-panels::avatar.user
                    class="fi-ios-account-avatar shrink-0 shadow-[0_2px_8px_rgba(0,0,0,0.08)] ring-[0.5px] ring-black/[0.06] dark:shadow-[0_2px_10px_rgba(0,0,0,0.35)] dark:ring-white/[0.08]"
                    size="sm"
                    :user="$user"
                    loading="lazy"
                />
            @endif

            <div class="min-w-0 flex-1 space-y-0.5">
                <p
                    class="text-[10px] font-medium leading-none tracking-[0.02em] text-[#8E8E93] dark:text-zinc-500"
                >
                    {{ __('Bienvenido a :app', ['app' => config('app.name')]) }}
                </p>
                <h2
                    class="truncate text-[16px] font-semibold leading-[1.2] tracking-[-0.02em] text-[#000000] dark:text-white"
                >
                    @if (filled($firstName))
                        {{ __('Hola, :name', ['name' => $firstName]) }}
                    @else
                        {{ $displayName }}
                    @endif
                </h2>
                @if ($user && filled($firstName) && $displayName !== $firstName)
                    <p
                        class="truncate text-[12px] font-normal leading-snug text-[#3C3C43]/72 dark:text-zinc-400"
                    >
                        {{ $displayName }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
