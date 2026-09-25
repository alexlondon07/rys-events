@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Grupo RYS" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center rounded-full border border-[#C9A043] bg-[#1E1C17] text-[#E2C274]">
            <span class="font-display text-[10px] font-bold tracking-tight">RYS</span>
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Grupo RYS" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center rounded-full border border-[#C9A043] bg-[#1E1C17] text-[#E2C274]">
            <span class="font-display text-[10px] font-bold tracking-tight">RYS</span>
        </x-slot>
    </flux:brand>
@endif
