<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mi perfil')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();
        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated)->save();

        Flux::toast(variant: 'success', text: 'Perfil actualizado correctamente.');
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 py-4">
    @include('partials.settings-heading')
    @include('partials.settings-nav')

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
        <section class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm sm:p-7">
            <div class="flex items-center gap-4 border-b border-[#E3DED3] pb-6">
                <div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-[#17150F] font-display text-lg font-bold text-[#E2C274]">
                    {{ auth()->user()->initials() }}
                </div>
                <div class="min-w-0">
                    <h2 class="font-display truncate text-xl font-bold text-[#17150F]">{{ auth()->user()->name }}</h2>
                    <p class="mt-1 truncate text-sm text-[#5F584A]">{{ auth()->user()->email }}</p>
                    <span class="mt-2 inline-flex rounded-full bg-[#F6EEDB] px-2.5 py-1 text-xs font-semibold text-[#7F5C12]">
                        {{ auth()->user()->role->label() }}
                    </span>
                </div>
            </div>

            <form wire:submit="updateProfileInformation" class="mt-6 space-y-5">
                <div>
                    <h3 class="font-display text-lg font-bold">Información personal</h3>
                    <p class="mt-1 text-sm text-[#5F584A]">Estos datos identifican quién realiza cambios e importaciones.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="name" label="Nombre completo" type="text" required autofocus autocomplete="name" />
                    <flux:input wire:model="email" label="Correo electrónico" type="email" required autocomplete="email" />
                </div>

                <div class="flex justify-end border-t border-[#E3DED3] pt-5">
                    <flux:button variant="primary" type="submit" icon="check" data-test="update-profile-button">
                        Guardar cambios
                    </flux:button>
                </div>
            </form>
        </section>

        <aside class="space-y-5">
            <section class="rounded-xl border border-[#E3DED3] bg-white p-5 shadow-sm">
                <div class="flex size-10 items-center justify-center rounded-full bg-[#F6EEDB] text-[#7F5C12]">
                    <flux:icon.shield-check class="size-5" />
                </div>
                <h2 class="font-display mt-4 text-lg font-bold">Seguridad</h2>
                <p class="mt-2 text-sm leading-6 text-[#5F584A]">Actualice su contraseña y configure la verificación en dos pasos.</p>
                <div class="mt-4 flex items-center gap-2 text-xs font-semibold">
                    <span @class([
                        'size-2 rounded-full',
                        'bg-[#2C7549]' => auth()->user()->two_factor_confirmed_at,
                        'bg-[#C9A043]' => ! auth()->user()->two_factor_confirmed_at,
                    ])></span>
                    {{ auth()->user()->two_factor_confirmed_at ? '2FA activo' : '2FA pendiente de configurar' }}
                </div>
                <flux:button :href="route('security.edit')" class="mt-5 w-full" variant="ghost" icon="key" wire:navigate>
                    Abrir seguridad
                </flux:button>
            </section>

            <section class="rounded-xl border border-[#E8D6A8] bg-[#F6EEDB] p-5">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#7F5C12]">Acceso habilitado</p>
                <p class="mt-2 text-sm leading-6 text-[#5F584A]">Esta instalación local no requiere verificar el correo para ingresar.</p>
            </section>
        </aside>
    </div>
</div>
