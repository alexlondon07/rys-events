@php
    $tabs = [
        ['route' => 'profile.edit', 'label' => 'Perfil'],
        ['route' => 'security.edit', 'label' => 'Seguridad'],
        ['route' => 'appearance.edit', 'label' => 'Apariencia'],
    ];
@endphp

<nav aria-label="Configuración de la cuenta" class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-lg border border-[#D3CBBB] bg-white p-1 shadow-sm">
    @foreach ($tabs as $tab)
        <a
            href="{{ route($tab['route']) }}"
            @class([
                'whitespace-nowrap rounded-md px-4 py-2 text-sm font-semibold transition',
                'bg-[#17150F] text-white' => request()->routeIs($tab['route']),
                'text-[#5F584A] hover:bg-[#F3F1EC] hover:text-[#17150F]' => ! request()->routeIs($tab['route']),
            ])
            wire:navigate
        >{{ $tab['label'] }}</a>
    @endforeach
</nav>
