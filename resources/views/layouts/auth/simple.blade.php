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
    <body class="min-h-dvh bg-[#F3F1EC] text-[#17150F] antialiased">
        <div class="relative flex min-h-dvh items-center justify-center overflow-hidden px-6 py-6">
            <div class="pointer-events-none absolute -top-40 left-1/2 size-[34rem] -translate-x-1/2 rounded-full bg-[#C9A043]/10 blur-3xl"></div>
            <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-[#C9A043] to-transparent"></div>

            <div class="relative z-10 flex w-full max-w-md flex-col">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2.5" wire:navigate>
                    <span class="flex size-12 items-center justify-center rounded-full border border-[#C9A043] bg-[#0F0E0B] text-[#E2C274] shadow-sm">
                        <span class="font-display text-sm font-bold tracking-tight">RYS</span>
                    </span>
                    <span class="text-center">
                        <span class="font-display block text-2xl font-bold text-[#17150F]">Grupo RYS</span>
                        <span class="mt-0.5 block text-xs font-bold uppercase tracking-[0.2em] text-[#7F5C12]">Informe de actividades</span>
                    </span>
                </a>

                <div class="mt-6 rounded-2xl border border-[#E3DED3] bg-white p-6 shadow-sm sm:p-8">
                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>

                <p class="mt-5 text-center text-xs text-[#5F584A]">© {{ date('Y') }} Grupo RYS S.A.S</p>
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
