<?php

namespace App\Models;

use App\Services\Photos\EvidenceLink;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $artist_name
 * @property string|null $category_label
 * @property string|null $evidence_url
 * @property CarbonInterface|null $updated_in_app_at
 * @property CarbonInterface|null $imported_at
 */
#[Fillable([
    'report_id', 'ref', 'type', 'category_label', 'specification', 'artist_name',
    'narrative', 'quantity', 'unit', 'sort_order', 'photo_layout', 'drive_folder_id',
    'evidence_url', 'drive_synced_at', 'add_standard_texts', 'internal_notes',
    'updated_in_app_at', 'imported_at',
])]
class ReportItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'add_standard_texts' => 'boolean',
            'drive_synced_at' => 'datetime',
            'updated_in_app_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** @return HasMany<ReportItemPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ReportItemPhoto::class)->orderBy('sort_order');
    }

    /**
     * Fotos que caben por página según la distribución elegida en el Excel.
     */
    public function photosPerPage(): int
    {
        return match ($this->photo_layout) {
            'single' => 1,
            'collage' => 4,
            default => 2,
        };
    }

    /**
     * Enlace de evidencia del ítem (Drive, imagen directa u otro proveedor).
     * Si solo hay `drive_folder_id` (datos heredados) se reconstruye la URL.
     */
    public function evidenceLink(): ?EvidenceLink
    {
        $url = $this->evidence_url
            ?: ($this->drive_folder_id ? "https://drive.google.com/drive/folders/{$this->drive_folder_id}" : null);

        return EvidenceLink::make($url);
    }
}
