<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $summary
 * @property list<array<string, mixed>>|null $errors
 */
#[Fillable([
    'report_id', 'user_id', 'original_name', 'file_path', 'status', 'summary',
    'errors', 'applied_at',
])]
class ReportImport extends Model
{
    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'errors' => 'array',
            'applied_at' => 'datetime',
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
}
