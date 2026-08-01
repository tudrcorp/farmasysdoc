<?php

namespace App\Filament\Tables\Columns\Summarizers\Concerns;

use Filament\Support\Concerns\CanBeCopied;
use Illuminate\Support\Js;

trait RendersCopyableSummary
{
    use CanBeCopied;

    protected function setUp(): void
    {
        parent::setUp();

        $this->copyable();
        $this->copyMessage('Monto copiado');
        $this->copyableState(fn (mixed $state): string => number_format((float) ($state ?? 0), 2, ',', '.'));
    }

    public function toEmbeddedHtml(): string
    {
        $state = $this->getState();
        $formatted = $this->formatState($state);
        $copyText = $this->getCopyableState($state) ?? $formatted;

        $copyableStateJs = Js::from($copyText);
        $copyMessageJs = Js::from($this->getCopyMessage($state));
        $copyMessageDurationJs = Js::from($this->getCopyMessageDuration($state));

        $attributes = $this->getExtraAttributeBag()
            ->merge([
                'x-on:click.prevent.stop' => <<<JS
                    window.navigator.clipboard.writeText({$copyableStateJs})
                    \$tooltip({$copyMessageJs}, {
                        theme: \$store.theme,
                        timeout: {$copyMessageDurationJs},
                    })
                    JS,
                'x-tooltip' => '{
                    content: '.Js::from('Clic para copiar el monto').',
                    theme: $store.theme,
                }',
                'role' => 'button',
                'tabindex' => '0',
            ], escape: false)
            ->class([
                'fi-ta-text-summary',
                'fi-copyable',
                'farmadoc-summary-copyable',
            ]);

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <?php if (filled($label = $this->getLabel())) { ?>
                <span class="fi-ta-text-summary-label">
                    <?= e($label) ?>
                </span>
            <?php } ?>

            <span>
                <?= e($formatted) ?>
            </span>
        </div>

        <?php return (string) ob_get_clean();
    }
}
