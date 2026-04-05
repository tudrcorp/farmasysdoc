<x-filament-widgets::widget class="fi-wi-widget fi-ios-partner-credit-widget">
    <div
        class="fi-ios-partner-credit-widget__shell"
        @if (filled($pollingInterval ?? null))
            wire:poll.{{ $pollingInterval }}
        @endif
    >
        <div class="fi-ios-partner-credit-widget__glow" aria-hidden="true"></div>
        <div class="fi-ios-partner-credit-widget__wash" aria-hidden="true"></div>
        <div class="fi-ios-partner-credit-widget__rim" aria-hidden="true"></div>
        <div class="fi-ios-partner-credit-widget__sheen" aria-hidden="true"></div>

        <div class="fi-ios-partner-credit-widget__content">
            <div class="fi-ios-partner-credit-widget__icon-wrap" aria-hidden="true">
                <x-filament::icon
                    icon="heroicon-o-banknotes"
                    class="fi-ios-partner-credit-widget__icon-svg size-7 text-teal-700 dark:text-teal-200"
                />
            </div>

            <div class="fi-ios-partner-credit-widget__body">
                <p class="fi-ios-partner-credit-widget__eyebrow">
                    Crédito disponible
                </p>
                <p class="fi-ios-partner-credit-widget__amount tabular-nums">
                    {{ $remainingFormatted }}
                </p>
                @if (filled($partnerLine))
                    <p class="fi-ios-partner-credit-widget__caption">
                        {{ $partnerLine }}
                    </p>
                @endif
                <div class="fi-ios-partner-credit-widget__metrics" role="group" aria-label="Detalle de cupo">
                    <p class="fi-ios-partner-credit-widget__metric">
                        <span class="fi-ios-partner-credit-widget__metric-label">Tope de línea</span>
                        <span class="fi-ios-partner-credit-widget__metric-value tabular-nums">{{ $limitFormatted }}</span>
                    </p>
                    <p class="fi-ios-partner-credit-widget__metric">
                        <span class="fi-ios-partner-credit-widget__metric-label">Consumido</span>
                        <span class="fi-ios-partner-credit-widget__metric-value tabular-nums">{{ $consumedFormatted }}</span>
                    </p>
                </div>
                <p class="fi-ios-partner-credit-widget__footnote">
                    Se actualiza automáticamente. Los pedidos a crédito en «En proceso» restan de su cupo.
                </p>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
