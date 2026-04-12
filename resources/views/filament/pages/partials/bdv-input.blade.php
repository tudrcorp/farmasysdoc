@props([
    'name',
    'label',
    'placeholder' => null,
    'class' => '',
])
<div @class(['space-y-1', $class])>
    <label class="text-sm font-medium text-gray-950 dark:text-white" for="bdv-{{ $name }}">{{ $label }}</label>
    <input
        id="bdv-{{ $name }}"
        type="text"
        wire:model="{{ $name }}"
        @if ($placeholder !== null) placeholder="{{ $placeholder }}" @endif
        class="fi-input block w-full rounded-lg border border-gray-950/10 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
    />
    @error($name)
        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
    @enderror
</div>
