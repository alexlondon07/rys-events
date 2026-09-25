<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $summary
 * @property list<array<string, mixed>>|null $errors
 */
#[Fillable([
    'report_id', 'user_id', 'original_name', 'file_path', 'status', 'version',
    'file_hash', 'photos_added', 'summary', 'errors', 'applied_at',
])]
class ReportImport extends Model
{
    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'errors' => 'array',
            'applied_at' => 'datetime',
            'version' => 'integer',
            'photos_added' => 'integer',
        ];
    }

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ReportActivityLog, $this> */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ReportActivityLog::class, 'report_import_id');
    }

    public function summaryLine(): string
    {
        $result = $this->summary['result'] ?? [];

        return sprintf(
            '%d nuevos · %d actualizados · %d sin cambios · %d fotos',
            $result['created'] ?? 0,
            $result['updated'] ?? 0,
            $result['unchanged'] ?? 0,
            $this->photos_added,
        );
    }
}
