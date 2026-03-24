@props([
    'title',
    'description',
])

<div class="farmadoc-auth-header flex w-full flex-col gap-2 text-center">
    <flux:heading size="xl" class="farmadoc-auth-heading">{{ $title }}</flux:heading>
    <flux:subheading class="farmadoc-auth-subheading">
        {{ $description }}
    </flux:subheading>
</div>
