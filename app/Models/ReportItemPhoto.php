<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'report_item_id', 'source', 'drive_file_id', 'drive_url', 'path', 'thumb_path',
    'original_name', 'caption', 'layout', 'sort_order', 'sync_status', 'sync_error',
    'taken_at', 'width', 'height', 'size_bytes',
])]
class ReportItemPhoto extends Model
{
    protected function casts(): array
    {
        return ['taken_at' => 'datetime'];
    }

    /** @return BelongsTo<ReportItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ReportItem::class, 'report_item_id');
    }

    public function thumbUrl(): ?string
    {
        if ($this->thumb_path) {
            return asset('storage/'.$this->thumb_path);
        }

        if ($this->path) {
            return asset('storage/'.$this->path);
        }

        return $this->driveThumbUrl();
    }

    public function fullUrl(): ?string
    {
        if ($this->path) {
            return asset('storage/'.$this->path);
        }

        return $this->driveThumbUrl();
    }

    /**
     * Google Drive sirve la miniatura pública de un archivo por su ID.
     */
    public function driveThumbUrl(): ?string
    {
        return $this->drive_file_id
            ? 'https://drive.google.com/thumbnail?id='.$this->drive_file_id.'&sz=w1600'
            : null;
    }
}
