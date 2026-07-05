@props([
    'name',
    'label',
    'placeholder' => null,
    'class' => '',
    'idPrefix' => 'bdv',
])
<div @class(['space-y-1', $class])>
    <label class="farmadoc-bdv-pm-field-label" for="{{ $idPrefix }}-{{ $name }}">{{ $label }}</label>
    <input
        id="{{ $idPrefix }}-{{ $name }}"
        type="text"
        wire:model="{{ $name }}"
        @if ($placeholder !== null) placeholder="{{ $placeholder }}" @endif
        class="farmadoc-bdv-pm-field-input fi-input"
    />
    @error($name)
        <p class="farmadoc-bdv-pm-field-error">{{ $message }}</p>
    @enderror
</div>
