<?php

use App\Models\ItemCatalog;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Catálogo de ítems')] class extends Component {
    private const UNITS = ['días', 'camiones', 'agrupada', 'unidades', 'horas'];

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $type = 'artistic';

    public string $category_label = '';

    public string $specification = '';

    public string $default_narrative = '';

    public string $default_unit = '';

    public string $default_quantity = '';

    public bool $active = true;

    /** @return \Illuminate\Support\Collection<int, ItemCatalog> */
    #[Computed]
    public function items()
    {
        return ItemCatalog::query()
            ->orderByRaw("case when type = 'artistic' then 0 else 1 end")
            ->orderBy('category_label')
            ->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function typeOptions(): array
    {
        return ItemCatalog::typeOptions();
    }

    /** @return list<string> */
    #[Computed]
    public function unitOptions(): array
    {
        return self::UNITS;
    }

    public function create(): void
    {
        $this->reset('editingId', 'category_label', 'specification', 'default_narrative', 'default_unit', 'default_quantity');
        $this->type = 'artistic';
        $this->active = true;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function startEdit(int $id): void
    {
        $item = ItemCatalog::query()->findOrFail($id);

        $this->editingId = $item->id;
        $this->type = $item->type;
        $this->category_label = (string) $item->category_label;
        $this->specification = (string) $item->specification;
        $this->default_narrative = (string) $item->default_narrative;
        $this->default_unit = (string) $item->default_unit;
        $this->default_quantity = $item->default_quantity === null ? '' : (string) $item->default_quantity;
        $this->active = $item->active;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset('editingId', 'category_label', 'specification', 'default_narrative', 'default_unit', 'default_quantity');
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'type' => ['required', 'in:artistic,technical'],
            'category_label' => ['required', 'string', 'max:255'],
            'default_quantity' => ['nullable', 'numeric', 'min:0'],
        ], [
            'category_label.required' => 'La categoría es obligatoria.',
            'default_quantity.numeric' => 'La cantidad debe ser un número.',
        ]);

        $data = [
            'type' => $validated['type'],
            'category_label' => $validated['category_label'],
            'specification' => $this->specification ?: null,
            'default_narrative' => $this->default_narrative ?: null,
            'default_unit' => $this->default_unit ?: null,
            'default_quantity' => $this->default_quantity === '' ? null : round((float) $this->default_quantity, 3),
            'active' => $this->active,
        ];

        if ($this->editingId) {
            ItemCatalog::query()->findOrFail($this->editingId)->update($data);
        } else {
            ItemCatalog::query()->create($data);
        }

        $this->cancel();
        unset($this->items);
        Flux::toast(variant: 'success', text: 'Ítem guardado en el catálogo.');
    }

    public function toggleActive(int $id): void
    {
        $item = ItemCatalog::query()->findOrFail($id);
        $item->update(['active' => ! $item->active]);
        unset($this->items);
    }

    public function delete(int $id): void
    {
        ItemCatalog::query()->findOrFail($id)->delete();
        unset($this->items);
        Flux::toast(variant: 'success', text: 'Ítem eliminado del catálogo.');
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 py-4">
    <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-semibold text-[#7F5C12]">Biblioteca</p>
            <h1 class="font-display mt-1 text-3xl font-bold tracking-tight text-[#17150F]">Catálogo de ítems</h1>
            <p class="mt-2 text-[#5F584A]">Especificaciones y narrativas reutilizables para importar a los informes.</p>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus">Nuevo ítem</flux:button>
    </header>

    @if ($showForm)
        <form wire:submit="save" class="rounded-xl border border-[#D3CBBB] bg-white p-6 shadow-sm">
            <h2 class="font-display text-lg font-bold text-[#17150F]">{{ $editingId ? 'Editar ítem' : 'Nuevo ítem' }}</h2>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <flux:select wire:model="type" label="Tipo">
                    @foreach ($this->typeOptions as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="category_label" label="Categoría" type="text" required />
            </div>

            <div class="mt-5 grid gap-5">
                <flux:textarea wire:model="specification" label="Especificación contratada" rows="3" />
                <flux:textarea wire:model="default_narrative" label="Actividad ejecutada sugerida" rows="4" />
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                <flux:input wire:model="default_quantity" label="Cantidad" type="number" step="0.001" />
                <flux:select wire:model="default_unit" label="Unidad">
                    <flux:select.option value="">Sin unidad</flux:select.option>
                    @foreach ($this->unitOptions as $unit)
                        <flux:select.option value="{{ $unit }}">{{ $unit }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex items-end pb-2">
                    <flux:checkbox wire:model="active" label="Activo" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-[#E3DED3] pt-5">
                <flux:button type="button" wire:click="cancel" variant="ghost">Cancelar</flux:button>
                <flux:button type="submit" variant="primary" icon="check">Guardar</flux:button>
            </div>
        </form>
    @endif

    <section class="overflow-hidden rounded-xl border border-[#E3DED3] bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-[#E3DED3] px-5 py-4">
            <h2 class="font-display text-lg font-bold text-[#17150F]">Ítems</h2>
            <span class="rounded-full bg-[#ECE8E0] px-2.5 py-1 text-xs font-semibold text-[#5F584A]">{{ $this->items->count() }}</span>
        </div>

        <div class="divide-y divide-[#EDE9E0]">
            @forelse ($this->items as $item)
                <article class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-[#F6EEDB] text-[#7F5C12]' => $item->type === 'artistic',
                                'bg-[#E7F3EC] text-[#2C7549]' => $item->type === 'technical',
                            ])>{{ $this->typeOptions[$item->type] ?? $item->type }}</span>
                            <p class="font-semibold">{{ $item->category_label }}</p>
                            @unless ($item->active)
                                <span class="rounded-full bg-[#ECE8E0] px-2.5 py-1 text-xs font-semibold text-[#5F584A]">Inactivo</span>
                            @endunless
                        </div>
                        @if ($item->specification)
                            <p class="mt-1 line-clamp-2 text-sm text-[#5F584A]">{{ $item->specification }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <flux:button size="sm" variant="ghost" :icon="$item->active ? 'no-symbol' : 'check-circle'" wire:click="toggleActive({{ $item->id }})">
                            {{ $item->active ? 'Desactivar' : 'Activar' }}
                        </flux:button>
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="startEdit({{ $item->id }})">Editar</flux:button>
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})" wire:confirm="¿Eliminar {{ $item->category_label }} del catálogo?">Eliminar</flux:button>
                    </div>
                </article>
            @empty
                <p class="px-5 py-10 text-center text-sm text-[#5F584A]">Aún no hay ítems. Cree el primero.</p>
            @endforelse
        </div>
    </section>
</div>
