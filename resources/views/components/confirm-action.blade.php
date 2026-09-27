@props([
    'name',
    'action',
    'title' => 'Confirmar acción',
    'message',
    'confirmLabel' => 'Confirmar',
    'form' => null,
])

<flux:modal.trigger :name="$name">
    {{ $trigger }}
</flux:modal.trigger>

<flux:modal :name="$name" class="max-w-md">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <flux:subheading class="mt-2">{{ $message }}</flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button type="button" variant="ghost">Cancelar</flux:button>
            </flux:modal.close>
            @if ($form)
                <flux:button type="submit" :form="$form" variant="danger">{{ $confirmLabel }}</flux:button>
            @else
                <flux:modal.close>
                    <flux:button type="button" variant="danger" wire:click="{{ $action }}">{{ $confirmLabel }}</flux:button>
                </flux:modal.close>
            @endif
        </div>
    </div>
</flux:modal>
