<?php

use App\Models\CompanySetting;
use Flux\Flux;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Empresa')] class extends Component {
    use WithFileUploads;

    public string $name = '';

    public string $nit = '';

    public string $legal_rep_name = '';

    public string $legal_rep_title = '';

    public string $contact_email = '';

    public string $contact_phone = '';

    public $logo = null;

    public $signature = null;

    public ?string $existingLogo = null;

    public ?string $existingSignature = null;

    public function mount(): void
    {
        $settings = CompanySetting::current();

        $this->name = $settings->name ?? '';
        $this->nit = $settings->nit ?? '';
        $this->legal_rep_name = $settings->legal_rep_name ?? '';
        $this->legal_rep_title = $settings->legal_rep_title ?? '';
        $this->contact_email = $settings->contact_email ?? '';
        $this->contact_phone = $settings->contact_phone ?? '';
        $this->existingLogo = $settings->logoUrl();
        $this->existingSignature = $settings->signatureUrl();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'nit' => ['nullable', 'string', 'max:50'],
            'legal_rep_name' => ['nullable', 'string', 'max:255'],
            'legal_rep_title' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'signature' => ['nullable', 'image', 'max:2048'],
        ]);

        $settings = CompanySetting::current();
        $data = Arr::except($validated, ['logo', 'signature']);

        if ($this->logo) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $data['logo_path'] = $this->logo->store('company', 'public');
        }

        if ($this->signature) {
            if ($settings->signature_path) {
                Storage::disk('public')->delete($settings->signature_path);
            }

            $data['signature_path'] = $this->signature->store('company', 'public');
        }

        $settings->update($data);

        $this->reset('logo', 'signature');
        $this->existingLogo = $settings->fresh()->logoUrl();
        $this->existingSignature = $settings->fresh()->signatureUrl();

        Flux::toast(variant: 'success', text: 'Configuración guardada.');
    }

    public function removeLogo(): void
    {
        CompanySetting::current()->removeLogo();

        $this->existingLogo = null;
        $this->reset('logo');

        Flux::toast(variant: 'success', text: 'Logo eliminado.');
    }

    public function removeSignature(): void
    {
        CompanySetting::current()->removeSignature();

        $this->existingSignature = null;
        $this->reset('signature');

        Flux::toast(variant: 'success', text: 'Firma eliminada.');
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 py-4">
    <header>
        <p class="text-sm font-semibold text-[#7F5C12]">Administración</p>
        <h1 class="font-display mt-1 text-3xl font-bold tracking-tight text-[#17150F]">Configuración de la empresa</h1>
        <p class="mt-2 text-[#5F584A]">Estos datos aparecen en la portada, el encabezado y la firma del informe.</p>
    </header>

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-6">
            <section class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm">
                <h2 class="font-display text-lg font-bold text-[#17150F]">Identidad</h2>
                <p class="mt-1 text-sm text-[#5F584A]">Nombre legal y NIT de Grupo RYS.</p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="name" label="Razón social" type="text" />
                    <flux:input wire:model="nit" label="NIT" type="text" />
                    <flux:input wire:model="contact_email" label="Correo de contacto" type="text" />
                    <flux:input wire:model="contact_phone" label="Teléfono" type="text" />
                </div>
            </section>

            <section class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm">
                <h2 class="font-display text-lg font-bold text-[#17150F]">Representante legal</h2>
                <p class="mt-1 text-sm text-[#5F584A]">Quien firma el informe al cierre.</p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="legal_rep_name" label="Nombre" type="text" />
                    <flux:input wire:model="legal_rep_title" label="Cargo" type="text" />
                </div>
            </section>

            <section class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm">
                <h2 class="font-display text-lg font-bold text-[#17150F]">Marca</h2>
                <p class="mt-1 text-sm text-[#5F584A]">Logo del encabezado y firma escaneada. Formatos de imagen, máximo 2 MB.</p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-[#17150F]">Logo</label>
                        <label class="mt-2 flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-[#D3CBBB] bg-white px-3 py-2 text-sm text-[#5F584A] transition hover:border-[#C9A043]">
                            <span class="truncate">{{ $logo ? $logo->getClientOriginalName() : 'Seleccionar imagen…' }}</span>
                            <span class="shrink-0 rounded-md bg-[#17150F] px-3 py-1.5 text-xs font-semibold text-white">Elegir archivo</span>
                            <input wire:model="logo" type="file" accept="image/*" class="sr-only" />
                        </label>
                        @error('logo') <p class="mt-1 text-xs font-semibold text-[#A8261D]">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-[#17150F]">Firma</label>
                        <label class="mt-2 flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-[#D3CBBB] bg-white px-3 py-2 text-sm text-[#5F584A] transition hover:border-[#C9A043]">
                            <span class="truncate">{{ $signature ? $signature->getClientOriginalName() : 'Seleccionar imagen…' }}</span>
                            <span class="shrink-0 rounded-md bg-[#17150F] px-3 py-1.5 text-xs font-semibold text-white">Elegir archivo</span>
                            <input wire:model="signature" type="file" accept="image/*" class="sr-only" />
                        </label>
                        @error('signature') <p class="mt-1 text-xs font-semibold text-[#A8261D]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="check">Guardar configuración</flux:button>
            </div>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-6 lg:self-start">
            <section class="rounded-xl border border-[#E3DED3] bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="font-display font-bold text-[#17150F]">Logo</h2>
                    @if ($existingLogo || $logo)
                        <button type="button" wire:click="removeLogo" class="text-xs font-semibold text-[#A8261D] hover:underline">Quitar</button>
                    @endif
                </div>
                <div class="mt-4 flex h-32 items-center justify-center rounded-lg border border-dashed border-[#D3CBBB] bg-[#FAF9F6]">
                    @if ($logo && $logo->isPreviewable())
                        <img src="{{ $logo->temporaryUrl() }}" alt="Logo nuevo" class="max-h-24 max-w-full object-contain" />
                    @elseif ($existingLogo)
                        <img src="{{ $existingLogo }}" alt="Logo actual" class="max-h-24 max-w-full object-contain" />
                    @else
                        <span class="text-sm text-[#5F584A]">Sin logo</span>
                    @endif
                </div>
            </section>

            <section class="rounded-xl border border-[#E3DED3] bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="font-display font-bold text-[#17150F]">Firma</h2>
                    @if ($existingSignature || $signature)
                        <button type="button" wire:click="removeSignature" class="text-xs font-semibold text-[#A8261D] hover:underline">Quitar</button>
                    @endif
                </div>
                <div class="mt-4 flex h-32 items-center justify-center rounded-lg border border-dashed border-[#D3CBBB] bg-[#FAF9F6]">
                    @if ($signature && $signature->isPreviewable())
                        <img src="{{ $signature->temporaryUrl() }}" alt="Firma nueva" class="max-h-24 max-w-full object-contain" />
                    @elseif ($existingSignature)
                        <img src="{{ $existingSignature }}" alt="Firma actual" class="max-h-24 max-w-full object-contain" />
                    @else
                        <span class="text-sm text-[#5F584A]">Sin firma</span>
                    @endif
                </div>
            </section>
        </aside>
    </form>
</div>
