<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light" data-theme="rys">
    <head>
        @include('partials.head')
        <script>
            document.documentElement.classList.remove('dark');
            document.documentElement.classList.add('light');
            document.documentElement.style.colorScheme = 'light';
        </script>
    </head>
    <body class="min-h-screen bg-[#F3F1EC] text-[#17150F] antialiased">
        <div class="grid min-h-screen lg:grid-cols-2">
            <aside class="relative hidden overflow-hidden bg-[#0F0E0B] p-12 lg:flex lg:flex-col">
                <div class="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-[#C9A043]/20 blur-3xl"></div>
                <div class="pointer-events-none absolute inset-x-0 bottom-0 h-1 bg-gradient-to-r from-[#C9A043] via-[#C9A043]/40 to-transparent"></div>

                <a href="{{ route('home') }}" class="relative z-10 flex items-center gap-3" wire:navigate>
                    <span class="flex size-11 items-center justify-center rounded-full border border-[#C9A043] bg-[#1E1C17] text-[#E2C274]">
                        <span class="font-display text-xs font-bold tracking-tight">RYS</span>
                    </span>
                    <span class="font-display text-lg font-semibold text-white">Grupo RYS</span>
                </a>

                <div class="relative z-10 mt-auto">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#E2C274]">Informe de actividades</p>
                    <h2 class="font-display mt-3 text-4xl font-bold leading-tight text-white">
                        Evidencia clara,<br>entregas a tiempo.
                    </h2>
                    <p class="mt-4 max-w-sm text-sm leading-6 text-[#CFC8B8]">
                        Cargue la plantilla, revise los cambios y genere el informe de cada evento con la identidad de Grupo RYS.
                    </p>
                    <ul class="mt-8 space-y-3 text-sm text-[#CFC8B8]">
                        <li class="flex items-center gap-3"><span class="text-[#E2C274]">✓</span> Carga por partes desde Excel</li>
                        <li class="flex items-center gap-3"><span class="text-[#E2C274]">✓</span> Evidencia fotográfica por ítem</li>
                        <li class="flex items-center gap-3"><span class="text-[#E2C274]">✓</span> PDF con la plantilla oficial</li>
                    </ul>
                </div>
            </aside>

            <main class="flex min-h-screen items-center justify-center p-6 sm:p-10">
                <div class="w-full max-w-md">
                    <a href="{{ route('home') }}" class="mb-8 flex flex-col items-center gap-3 lg:hidden" wire:navigate>
                        <span class="flex size-12 items-center justify-center rounded-full border border-[#C9A043] bg-[#1E1C17] text-[#E2C274]">
                            <span class="font-display text-sm font-bold tracking-tight">RYS</span>
                        </span>
                        <span class="font-display text-lg font-semibold text-[#17150F]">Grupo RYS</span>
                    </a>

                    <div class="rounded-2xl border border-[#E3DED3] bg-white p-8 shadow-sm sm:p-10">
                        <div class="flex flex-col gap-6">
                            {{ $slot }}
                        </div>
                    </div>

                    <p class="mt-6 text-center text-xs text-[#5F584A]">© {{ date('Y') }} Grupo RYS S.A.S</p>
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
        <script>
            document.documentElement.classList.remove('dark');
        </script>
    </body>
</html>
