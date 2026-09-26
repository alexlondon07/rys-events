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
    <body class="min-h-dvh bg-[#EEEBE3] text-[#17150F] antialiased">
        <div class="rys-auth-shell relative flex min-h-dvh items-center justify-center overflow-hidden px-5 py-8 sm:px-8">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-[#C9A043]"></div>
            <div class="pointer-events-none absolute inset-y-0 left-[7%] hidden w-px bg-[#D8D1C2] lg:block"></div>
            <div class="pointer-events-none absolute inset-y-0 right-[7%] hidden w-px bg-[#D8D1C2] lg:block"></div>

            <div class="relative z-10 flex w-full max-w-[27rem] flex-col">
                <a href="{{ route('home') }}" class="group flex items-center gap-3" wire:navigate>
                    <span class="flex size-12 items-center justify-center border border-[#C9A043] bg-[#17150F] text-[#E2C274]">
                        <span class="font-display text-xs font-bold tracking-[0.08em]">RYS</span>
                    </span>
                    <span>
                        <span class="font-display block text-xl font-bold tracking-[-0.025em] text-[#17150F]">Grupo RYS</span>
                        <span class="mt-0.5 block text-[10px] font-bold uppercase tracking-[0.16em] text-[#7F5C12]">Gestión de informes</span>
                    </span>
                </a>

                <div class="mt-8 border border-[#D3CBBB] border-l-4 border-l-[#C9A043] bg-white p-6 shadow-[0_12px_32px_rgba(23,21,15,0.06)] sm:p-8">
                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-between text-[11px] text-[#5F584A]">
                    <span class="font-semibold uppercase tracking-[0.12em]">Acceso interno</span>
                    <span>© {{ date('Y') }} Grupo RYS S.A.S</span>
                </div>
            </div>
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
