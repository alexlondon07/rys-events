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
    <body class="min-h-screen bg-[#F3F1EC] text-[#17150F]">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-[#2A2720] !bg-[#0F0E0B] !text-white">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group heading="Gestión" class="grid">
                    <flux:sidebar.item icon="document-text" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        Informes
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-up-tray" :href="route('reports.import')" :current="request()->routeIs('reports.import')" wire:navigate>
                        Importar desde Excel
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit')" wire:navigate>
                        Configuración
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group heading="Biblioteca" class="grid">
                    <flux:sidebar.item icon="rectangle-stack" :href="route('catalog.index')" :current="request()->routeIs('catalog.*')" wire:navigate>
                        Catálogo de ítems
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="document-duplicate" :href="route('templates.index')" :current="request()->routeIs('templates.*')" wire:navigate>
                        Plantillas de texto
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if (auth()->user()->isAdmin())
                    <flux:sidebar.group heading="Administración" class="grid">
                        <flux:sidebar.item icon="building-office-2" :href="route('company.edit')" :current="request()->routeIs('company.*')" wire:navigate>
                            Empresa
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                            Usuarios y roles
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

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
