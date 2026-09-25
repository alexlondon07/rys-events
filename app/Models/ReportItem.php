<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $artist_name
 * @property string|null $category_label
 * @property CarbonInterface|null $updated_in_app_at
 * @property CarbonInterface|null $imported_at
 */
#[Fillable([
    'report_id', 'ref', 'type', 'category_label', 'specification', 'artist_name',
    'narrative', 'quantity', 'unit', 'sort_order', 'photo_layout', 'drive_folder_id',
    'drive_synced_at', 'add_standard_texts', 'internal_notes', 'updated_in_app_at',
    'imported_at',
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
}
