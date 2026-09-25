<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración global de la empresa (una sola fila).
 */
#[Fillable([
    'name', 'nit', 'logo_path', 'signature_path', 'legal_rep_name',
    'legal_rep_title', 'contact_email', 'contact_phone',
])]
class CompanySetting extends Model
{
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }

    public function signatureUrl(): ?string
    {
        return $this->signature_path ? asset('storage/'.$this->signature_path) : null;
    }

    public function removeLogo(): void
    {
        if ($this->logo_path) {
            Storage::disk('public')->delete($this->logo_path);
            $this->update(['logo_path' => null]);
        }
    }

    public function removeSignature(): void
    {
        if ($this->signature_path) {
            Storage::disk('public')->delete($this->signature_path);
            $this->update(['signature_path' => null]);
        }
    }
}
