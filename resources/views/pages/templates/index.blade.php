<?php

use App\Models\TextTemplate;
use App\Services\Text\TextTemplateRenderer;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Plantillas de texto')] class extends Component {
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $key = 'introduction';

    public string $name = '';

    public string $body = '';

    public bool $active = true;

    /** @return \Illuminate\Support\Collection<int, TextTemplate> */
    #[Computed]
    public function templates()
    {
        return TextTemplate::query()->orderBy('key')->orderBy('name')->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function keyOptions(): array
    {
        return TextTemplate::keyOptions();
    }

    /** @return list<string> */
    #[Computed]
    public function variables(): array
    {
        return TextTemplateRenderer::VARIABLES;
    }

    public function create(): void
    {
        $this->reset('editingId', 'name', 'body');
        $this->key = 'introduction';
        $this->active = true;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function startEdit(int $id): void
    {
        $template = TextTemplate::query()->findOrFail($id);

        $this->editingId = $template->id;
        $this->key = $template->key;
        $this->name = $template->name;
        $this->body = $template->body_with_variables;
        $this->active = $template->active;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset('editingId', 'name', 'body');
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'key' => ['required', 'string', 'in:'.implode(',', array_keys(TextTemplate::keyOptions()))],
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'body.required' => 'El texto es obligatorio.',
        ]);

        $data = [
            'key' => $validated['key'],
            'name' => $validated['name'],
            'body_with_variables' => $validated['body'],
            'active' => $this->active,
        ];

        if ($this->editingId) {
            TextTemplate::query()->findOrFail($this->editingId)->update($data);
        } else {
            TextTemplate::query()->create($data);
        }

        $this->cancel();
        unset($this->templates);
        Flux::toast(variant: 'success', text: 'Plantilla guardada.');
    }

    public function toggleActive(int $id): void
    {
        $template = TextTemplate::query()->findOrFail($id);
        $template->update(['active' => ! $template->active]);
        unset($this->templates);
    }

    public function delete(int $id): void
    {
        TextTemplate::query()->findOrFail($id)->delete();
        unset($this->templates);
        Flux::toast(variant: 'success', text: 'Plantilla eliminada.');
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 py-4">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-[#7F5C12]">Biblioteca</p>
            <h1 class="font-display mt-1 text-3xl font-bold tracking-tight text-[#17150F]">Plantillas de texto</h1>
            <p class="mt-2 text-[#5F584A]">Textos reutilizables con variables para no reescribir cada informe.</p>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus">Nueva plantilla</flux:button>
    </header>

    @if ($showForm)
        <form wire:submit="save" class="rounded-xl border border-[#D3CBBB] bg-white p-6 shadow-sm">
            <h2 class="font-display text-lg font-bold text-[#17150F]">{{ $editingId ? 'Editar plantilla' : 'Nueva plantilla' }}</h2>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <flux:select wire:model="key" label="Uso">
                    @foreach ($this->keyOptions as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="name" label="Nombre" type="text" required />
            </div>

            <div class="mt-5">
                <flux:textarea wire:model="body" label="Texto" rows="7" required />
                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-[#5F584A]">
                    <span class="font-semibold">Variables:</span>
                    @foreach ($this->variables as $variable)
                        <code class="rounded bg-[#F3F1EC] px-1.5 py-0.5 font-mono text-[#7F5C12]">{{ '{'.$variable.'}' }}</code>
                    @endforeach
                </div>
            </div>

            <div class="mt-5">
                <flux:checkbox wire:model="active" label="Activa" />
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-[#E3DED3] pt-5">
                <flux:button type="button" wire:click="cancel" variant="ghost">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" icon="check">Guardar</flux:button>
            </div>
        </form>
    @endif

    <section class="overflow-hidden rounded-xl border border-[#E3DED3] bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-[#E3DED3] px-5 py-4">
            <h2 class="font-display text-lg font-bold text-[#17150F]">Plantillas</h2>
            <span class="rounded-full bg-[#ECE8E0] px-2.5 py-1 text-xs font-semibold text-[#5F584A]">{{ $this->templates->count() }}</span>
        </div>

        <div class="divide-y divide-[#EDE9E0]">
            @forelse ($this->templates as $template)
                <article class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-[#F6EEDB] px-2.5 py-1 text-xs font-semibold text-[#7F5C12]">{{ $this->keyOptions[$template->key] ?? $template->key }}</span>
                            <p class="font-semibold">{{ $template->name }}</p>
                            @unless ($template->active)
                                <span class="rounded-full bg-[#ECE8E0] px-2.5 py-1 text-xs font-semibold text-[#5F584A]">Inactiva</span>
                            @endunless
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm text-[#5F584A]">{{ $template->body_with_variables }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <flux:button size="sm" variant="ghost" :icon="$template->active ? 'no-symbol' : 'check-circle'" wire:click="toggleActive({{ $template->id }})">
                            {{ $template->active ? 'Desactivar' : 'Activar' }}
                        </flux:button>
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="startEdit({{ $template->id }})">Editar</flux:button>
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $template->id }})" wire:confirm="¿Eliminar la plantilla {{ $template->name }}?">Eliminar</flux:button>
                    </div>
                </article>
            @empty
                <p class="px-5 py-10 text-center text-sm text-[#5F584A]">Aún no hay plantillas. Cree la primera.</p>
            @endforelse
        </div>
    </section>
</div>
