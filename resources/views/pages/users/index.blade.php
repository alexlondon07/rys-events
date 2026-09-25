<?php

use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Usuarios')] class extends Component {
    public string $search = '';

    public bool $showForm = false;

    public string $name = '';

    public string $email = '';

    public string $role = 'editor';

    public string $password = '';

    public string $password_confirmation = '';

    public ?int $editingId = null;

    public string $editName = '';

    public string $editEmail = '';

    #[Computed]
    public function users()
    {
        return User::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderByRaw("case when role = 'admin' then 0 else 1 end")
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function roleOptions(): array
    {
        return UserRole::options();
    }

    #[Computed]
    public function adminCount(): int
    {
        return User::query()->where('role', UserRole::Admin->value)->count();
    }

    #[Computed]
    public function activeAdminCount(): int
    {
        return User::query()->where('role', UserRole::Admin->value)->where('active', true)->count();
    }

    public function toggleForm(): void
    {
        $this->showForm = ! $this->showForm;
        $this->cancelEdit();
        $this->resetValidation();

        if (! $this->showForm) {
            $this->reset('name', 'email', 'role', 'password', 'password_confirmation');
            $this->role = UserRole::Editor->value;
        }
    }

    public function startEdit(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        $this->editingId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        $this->showForm = false;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->reset('editName', 'editEmail');
        $this->resetValidation();
    }

    public function updateUser(): void
    {
        $user = User::query()->findOrFail($this->editingId);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
        ], [
            'editName.required' => 'El nombre es obligatorio.',
            'editEmail.required' => 'El correo es obligatorio.',
            'editEmail.unique' => 'Ya existe un usuario con ese correo.',
        ]);

        $user->update([
            'name' => $validated['editName'],
            'email' => $validated['editEmail'],
        ]);

        $this->cancelEdit();
        unset($this->users);

        Flux::toast(variant: 'success', text: 'Datos actualizados.');
    }

    public function toggleActive(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        if ($user->id === Auth::id()) {
            Flux::toast(variant: 'danger', text: 'No puede desactivar su propio usuario.');

            return;
        }

        if ($user->isActive() && $user->isAdmin() && $this->activeAdminCount <= 1) {
            Flux::toast(variant: 'danger', text: 'Debe quedar al menos un administrador activo.');

            return;
        }

        $user->update(['active' => ! $user->isActive()]);
        unset($this->users, $this->adminCount, $this->activeAdminCount);

        Flux::toast(variant: 'success', text: $user->isActive() ? 'Usuario habilitado.' : 'Usuario deshabilitado.');
    }

    public function createUser(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        User::create([
            ...$validated,
            'email_verified_at' => now(),
        ]);

        $this->reset('name', 'email', 'role', 'password', 'password_confirmation', 'showForm');
        $this->role = UserRole::Editor->value;
        unset($this->users);

        Flux::toast(variant: 'success', text: 'Usuario creado correctamente.');
    }

    public function updateRole(int $userId, string $role): void
    {
        $newRole = UserRole::tryFrom($role);

        if ($newRole === null) {
            Flux::toast(variant: 'danger', text: 'Rol no válido.');

            return;
        }

        $user = User::query()->findOrFail($userId);

        if ($user->isAdmin() && $newRole === UserRole::Editor && $this->adminCount <= 1) {
            Flux::toast(variant: 'danger', text: 'Debe existir al menos un administrador.');

            return;
        }

        if ($user->id === Auth::id() && $newRole === UserRole::Editor) {
            Flux::toast(variant: 'danger', text: 'No puede quitarte a ti mismo el rol de administrador.');

            return;
        }

        $user->update(['role' => $newRole]);
        unset($this->users, $this->adminCount);

        Flux::toast(variant: 'success', text: 'Rol actualizado.');
    }

    public function deleteUser(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        if ($user->id === Auth::id()) {
            Flux::toast(variant: 'danger', text: 'No puede eliminar su propio usuario.');

            return;
        }

        if ($user->isAdmin() && $this->adminCount <= 1) {
            Flux::toast(variant: 'danger', text: 'Debe existir al menos un administrador.');

            return;
        }

        $user->delete();
        unset($this->users, $this->adminCount);

        Flux::toast(variant: 'success', text: 'Usuario eliminado.');
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-6 py-4">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-[#7F5C12]">Administración</p>
            <h1 class="font-display mt-1 text-3xl font-bold tracking-tight text-[#17150F]">Usuarios y roles</h1>
            <p class="mt-2 text-[#5F584A]">Cree cuentas y defina quién administra el sistema.</p>
        </div>
        <flux:button wire:click="toggleForm" variant="primary" :icon="$showForm ? 'x-mark' : 'plus'">
            {{ $showForm ? 'Cancelar' : 'Nuevo usuario' }}
        </flux:button>
    </header>

    @if ($showForm)
        <form wire:submit="createUser" class="rounded-xl border border-[#D3CBBB] bg-white p-6 shadow-sm">
            <h2 class="font-display text-lg font-bold text-[#17150F]">Crear usuario</h2>
            <p class="mt-1 text-sm text-[#5F584A]">El usuario podrá ingresar de inmediato con la contraseña asignada.</p>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="name" label="Nombre completo" type="text" required />
                <flux:input wire:model="email" label="Correo electrónico" type="email" required />
                <flux:select wire:model="role" label="Rol">
                    @foreach ($this->roleOptions as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div></div>
                <flux:input wire:model="password" label="Contraseña" type="password" required />
                <flux:input wire:model="password_confirmation" label="Confirmar contraseña" type="password" required />
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-[#E3DED3] pt-5">
                <flux:button type="button" wire:click="toggleForm" variant="ghost">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" icon="check">Crear usuario</flux:button>
            </div>
        </form>
    @endif

    @if ($editingId)
        <form wire:submit="updateUser" class="rounded-xl border border-[#D3CBBB] bg-white p-6 shadow-sm">
            <h2 class="font-display text-lg font-bold text-[#17150F]">Editar usuario</h2>
            <p class="mt-1 text-sm text-[#5F584A]">Actualice el nombre o el correo de la cuenta.</p>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="editName" label="Nombre completo" type="text" required />
                <flux:input wire:model="editEmail" label="Correo electrónico" type="email" required />
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-[#E3DED3] pt-5">
                <flux:button type="button" wire:click="cancelEdit" variant="ghost">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" icon="check">Guardar cambios</flux:button>
            </div>
        </form>
    @endif

    <section class="overflow-hidden rounded-xl border border-[#E3DED3] bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-[#E3DED3] p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <h2 class="font-display text-lg font-bold text-[#17150F]">Cuentas</h2>
                <span class="rounded-full bg-[#ECE8E0] px-2.5 py-1 text-xs font-semibold text-[#5F584A]">{{ $this->users->count() }}</span>
            </div>
            <div class="w-full sm:w-72">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por nombre o correo" />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-[#E3DED3] bg-[#FAF9F6] text-xs font-semibold uppercase tracking-wide text-[#5F584A]">
                    <tr>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Rol</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">2FA</th>
                        <th class="px-4 py-3">Creado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#EDE9E0]">
                    @forelse ($this->users as $user)
                        <tr class="text-[#17150F] transition hover:bg-[#FBFAF6]">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#17150F] text-xs font-bold text-[#E2C274]">{{ $user->initials() }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold">{{ $user->name }}</p>
                                        <p class="truncate text-xs text-[#5F584A]">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                @if ($user->id === auth()->id())
                                    <span class="inline-flex rounded-full bg-[#F6EEDB] px-2.5 py-1 text-xs font-semibold text-[#7F5C12]">{{ $user->role->label() }}</span>
                                @else
                                    <flux:select wire:change="updateRole({{ $user->id }}, $event.target.value)" size="sm" class="w-40">
                                        @foreach ($this->roleOptions as $value => $label)
                                            <flux:select.option value="{{ $value }}" :selected="$user->role->value === $value">{{ $label }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-[#E7F3EC] text-[#2C7549]' => $user->isActive(),
                                    'bg-[#F9E3E0] text-[#A8261D]' => ! $user->isActive(),
                                ])>{{ $user->isActive() ? 'Habilitado' : 'Deshabilitado' }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                    'bg-[#E7F3EC] text-[#2C7549]' => $user->two_factor_confirmed_at,
                                    'bg-[#ECE8E0] text-[#5F584A]' => ! $user->two_factor_confirmed_at,
                                ])>{{ $user->two_factor_confirmed_at ? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td class="px-4 py-4 text-[#5F584A]">{{ $user->created_at?->format('d/m/Y') }}</td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="startEdit({{ $user->id }})">
                                        Editar
                                    </flux:button>

                                    @if ($user->id === auth()->id())
                                        <span class="px-2 text-xs font-semibold text-[#5F584A]">Sesión actual</span>
                                    @else
                                        <flux:button size="sm" variant="ghost" :icon="$user->isActive() ? 'no-symbol' : 'check-circle'" wire:click="toggleActive({{ $user->id }})">
                                            {{ $user->isActive() ? 'Deshabilitar' : 'Habilitar' }}
                                        </flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            wire:click="deleteUser({{ $user->id }})"
                                            wire:confirm="¿Eliminar a {{ $user->name }}? Esta acción no se puede deshacer."
                                        >
                                            Eliminar
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-[#5F584A]">No se encontraron usuarios.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
