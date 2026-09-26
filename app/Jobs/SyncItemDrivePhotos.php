<?php

namespace App\Jobs;

use App\Models\ReportItem;
use App\Services\Drive\DriveClient;
use App\Services\Photos\PhotoOptimizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Trae al almacenamiento propio las fotos de Drive de un ítem.
 *
 * 1. Si el ítem tiene carpeta de Drive, lista las imágenes y crea los registros
 *    que falten (incremental: nunca borra ni duplica).
 * 2. Descarga cada foto pendiente, la optimiza a JPEG y la guarda; el PDF deja
 *    de depender de Drive.
 *
 * Las fotos sin permiso quedan marcadas con `sync_error` y no detienen el resto.
 */
class SyncItemDrivePhotos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public ReportItem $item) {}

    public function handle(DriveClient $client, PhotoOptimizer $optimizer): void
    {
        if (! $client->isConfigured()) {
            return;
        }

        $this->importFolder($client);
        $this->downloadPending($client, $optimizer);

        $this->item->update(['drive_synced_at' => now()]);
    }

    private function importFolder(DriveClient $client): void
    {
        $folderId = $this->item->drive_folder_id ?: $this->item->evidenceLink()?->driveFolderId();

        if (! $folderId) {
            return;
        }

        $existing = $this->item->photos()->whereNotNull('drive_file_id')->pluck('drive_file_id')->all();
        $order = (int) $this->item->photos()->max('sort_order');

        foreach ($client->listImages($folderId) as $file) {
            if (in_array($file['id'], $existing, true)) {
                continue;
            }

            $this->item->photos()->create([
                'source' => 'drive',
                'drive_file_id' => $file['id'],
                'drive_url' => 'https://drive.google.com/file/d/'.$file['id'].'/view',
                'original_name' => $file['name'],
                'layout' => $this->item->photo_layout ?? 'pair',
                'sort_order' => ++$order,
                'sync_status' => 'pending',
            ]);
        }
    }

    private function downloadPending(DriveClient $client, PhotoOptimizer $optimizer): void
    {
        $photos = $this->item->photos()
            ->where('source', 'drive')
            ->whereNotNull('drive_file_id')
            ->whereNull('path')
            ->get();

        $directory = "reports/{$this->item->report_id}/items/{$this->item->id}";

        foreach ($photos as $photo) {
            $temporary = null;

            try {
                $temporary = $client->download((string) $photo->drive_file_id);

                $uuid = (string) Str::uuid();
                $path = "{$directory}/{$uuid}.jpg";
                $thumbPath = "{$directory}/thumbs/{$uuid}.jpg";

                $meta = $optimizer->optimize($temporary, Storage::disk('public')->path($path));
                $optimizer->optimize($temporary, Storage::disk('public')->path($thumbPath), PhotoOptimizer::THUMB_DIMENSION, 70);

                $photo->update([
                    'path' => $path,
                    'thumb_path' => $thumbPath,
                    'width' => $meta['width'],
                    'height' => $meta['height'],
                    'size_bytes' => $meta['size'],
                    'sync_status' => 'synced',
                    'sync_error' => null,
                ]);
            } catch (Throwable $exception) {
                report($exception);

                $photo->update([
                    'sync_status' => 'failed',
                    'sync_error' => $exception->getMessage(),
                ]);
            } finally {
                if ($temporary && is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        }
    }
}
