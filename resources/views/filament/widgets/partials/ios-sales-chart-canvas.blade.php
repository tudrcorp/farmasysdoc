<canvas
    x-ref="canvas"
    @if ($maxHeight ?? null)
        style="max-height: {{ $maxHeight }}"
    @endif
></canvas>

<span
    x-ref="backgroundColorElement"
    class="fi-wi-chart-bg-color"
></span>

<span
    x-ref="borderColorElement"
    class="fi-wi-chart-border-color"
></span>

<span
    x-ref="gridColorElement"
    class="fi-wi-chart-grid-color"
></span>

<span
    x-ref="textColorElement"
    class="fi-wi-chart-text-color"
></span>
