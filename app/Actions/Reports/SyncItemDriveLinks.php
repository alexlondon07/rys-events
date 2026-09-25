<?php

namespace App\Actions\Reports;

use App\Models\ReportItem;
use App\Services\ReportImport\ImportValueNormalizer;

/**
 * Sincroniza la evidencia de Google Drive de un ítem a partir del texto que
 * pega el usuario (un enlace de archivo por línea).
 *
 * Las fotos son idempotentes: se crean las nuevas y se quitan las que ya no
 * aparecen en el texto. Nunca toca las fotos subidas localmente.
 */
class SyncItemDriveLinks
{
    public function __construct(private readonly ImportValueNormalizer $normalizer) {}

    public function handle(ReportItem $item, string $links): void
    {
        $fileIds = collect(preg_split('/[\r\n,]+/', $links) ?: [])
            ->map(fn (string $line): ?string => $this->normalizer->driveFileId($line))
            ->filter()
            ->unique()
            ->values();

        $item->photos()
            ->where('source', 'drive')
            ->whereNotIn('drive_file_id', $fileIds->all())
            ->delete();

        $existing = $item->photos()->where('source', 'drive')->pluck('drive_file_id')->all();
        $order = (int) $item->photos()->max('sort_order');

        foreach ($fileIds as $fileId) {
            if (in_array($fileId, $existing, true)) {
                continue;
            }

            $item->photos()->create([
                'source' => 'drive',
                'drive_file_id' => $fileId,
                'drive_url' => "https://drive.google.com/file/d/{$fileId}/view",
                'layout' => $item->photo_layout ?? 'pair',
                'sort_order' => ++$order,
                'sync_status' => 'linked',
            ]);
        }

        $item->updated_in_app_at = now();
        $item->save();
    }
}
