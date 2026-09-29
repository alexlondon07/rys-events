@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Grupo RYS" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center overflow-hidden rounded-md border border-[#C9A043] bg-black">
            <x-app-logo-image class="size-full" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Grupo RYS" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center overflow-hidden rounded-md border border-[#C9A043] bg-black">
            <x-app-logo-image class="size-full" />
        </x-slot>
    </flux:brand>
@endif
