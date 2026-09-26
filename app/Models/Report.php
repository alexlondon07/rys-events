<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonInterface|null $report_date
 * @property CarbonInterface|null $period_start
 * @property CarbonInterface|null $period_end
 * @property CarbonInterface|null $event_start
 * @property CarbonInterface|null $event_end
 * @property CarbonInterface|null $updated_in_app_at
 * @property CarbonInterface|null $imported_at
 * @property-read Municipality|null $municipality
 */
#[Fillable([
    'user_id', 'municipality_id', 'contract_number', 'report_date', 'period_start',
    'period_end', 'subject', 'contract_object', 'event_name', 'event_start',
    'event_end', 'cover_url', 'cover_path', 'cover_template',
    'introduction', 'event_description', 'conclusion', 'signer_name',
    'status', 'current_step', 'pdf_path', 'pdf_generated_at', 'pdf_status', 'pdf_error',
    'updated_in_app_at', 'imported_at',
])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'event_start' => 'date',
            'event_end' => 'date',
            'pdf_generated_at' => 'datetime',
            'updated_in_app_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return HasMany<ReportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReportItem::class)->orderBy('sort_order');
    }

    /** @return HasMany<ReportImport, $this> */
    public function imports(): HasMany
    {
        return $this->hasMany(ReportImport::class)->latest();
    }

    /** @return HasMany<ReportActivityLog, $this> */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ReportActivityLog::class)->latest('id');
    }

    /**
     * URL de la foto de portada del informe.
     *
     * Si la imagen se subió en la aplicación se sirve desde el storage propio;
     * si viene como enlace de Google Drive se usa su miniatura pública.
     */
    public function coverImageUrl(): ?string
    {
        if ($this->cover_path) {
            return asset('storage/'.$this->cover_path);
        }

        if (! $this->cover_url) {
            return null;
        }

        foreach (['~drive\.google\.com/file/d/([A-Za-z0-9_-]+)~i', '~[?&]id=([A-Za-z0-9_-]+)~i'] as $pattern) {
            if (preg_match($pattern, $this->cover_url, $matches)) {
                return 'https://drive.google.com/thumbnail?id='.$matches[1].'&sz=w1600';
            }
        }

        return $this->cover_url;
    }

    public function inferredCurrentStep(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        if ($this->conclusion && $this->signer_name) {
            return 6;
        }

        if ($items->where('type', 'technical')->isNotEmpty()) {
            return 5;
        }

        if ($items->where('type', 'artistic')->isNotEmpty()) {
            return 4;
        }

        if ($this->event_name || $this->event_description) {
            return 3;
        }

        return max(1, (int) $this->current_step);
    }
}
