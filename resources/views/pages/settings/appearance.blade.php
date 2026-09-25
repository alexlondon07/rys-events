<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Apariencia')] class extends Component {
    //
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 py-4">
    @include('partials.settings-heading')
    @include('partials.settings-nav')

    <x-pages::settings.layout :heading="__('Apariencia')" :subheading="__('Tema visual del sistema')">
        <div class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm">
            <div class="flex items-start gap-4">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#F6EEDB] text-[#7F5C12]">
                    <flux:icon.sun class="size-6" />
                </div>
                <div class="min-w-0">
                    <p class="font-display text-lg font-bold text-[#17150F]">Tema claro de la marca</p>
                    <p class="mt-1 text-sm leading-6 text-[#5F584A]">
                        Grupo RYS usa una identidad visual fija (negro, dorado y gris claro) tanto en la aplicación
                        como en el PDF del informe. Por eso el tema oscuro no está disponible: mantiene el mismo
                        contraste y evita que los informes se vean distintos según el equipo.
                    </p>
                </div>
            </div>

            <div class="mt-5 flex items-center gap-2 rounded-lg border border-[#BBD8C5] bg-[#F1F8F3] px-4 py-3 text-sm font-semibold text-[#2C7549]">
                <flux:icon.check-circle class="size-5 shrink-0" />
                Tema claro activo
            </div>
        </div>
    </x-pages::settings.layout>
</div>
