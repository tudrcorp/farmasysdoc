<?php

namespace App\Support\Filament;

/**
 * Reinicia el scroll del panel slide-over al abrirse (misma ventana Livewire puede reutilizarse).
 *
 * @return array<string, string>
 */
final class SlideoverModalScrollFix
{
    public static function extraModalWindowAttributes(string ...$extraClasses): array
    {
        $classes = array_merge(['farmadoc-sale-slideover-window'], $extraClasses);
        $class = trim(implode(' ', array_filter($classes)));

        return [
            'class' => $class,
            '@open-modal.window' => <<<'JS'
if (($event.detail?.id ?? '') === ($el.closest('[data-fi-modal-id]')?.getAttribute('data-fi-modal-id') ?? '')) {
    const reset = () => {
        $el.scrollTop = 0;
        $el.querySelectorAll('.farmadoc-sale-slideover-entry .fi-in-entry-content').forEach((n) => {
            n.scrollTop = 0;
        });
    };
    requestAnimationFrame(() => requestAnimationFrame(reset));
    setTimeout(reset, 80);
}
JS,
        ];
    }
}
