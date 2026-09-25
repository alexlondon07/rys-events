<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $action
 * @property string $source
 */
#[Fillable([
    'report_id', 'report_item_id', 'item_ref', 'report_import_id', 'user_id',
    'action', 'field', 'label', 'old_value', 'new_value', 'source', 'created_at',
])]
class ReportActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** @return BelongsTo<ReportItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ReportItem::class, 'report_item_id');
    }

    /** @return BelongsTo<ReportImport, $this> */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ReportImport::class, 'report_import_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
