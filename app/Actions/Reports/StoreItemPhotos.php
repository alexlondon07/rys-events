<?php

namespace App\Actions\Reports;

use App\Models\ReportItem;
use App\Services\Photos\PhotoOptimizer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Procesa y guarda las fotos subidas a un ítem.
 *
 * Cada imagen se redimensiona y comprime a JPEG (foto y miniatura) mediante
 * {@see PhotoOptimizer}. Devuelve cuántas se guardaron; las que fallan se
 * reportan al log sin interrumpir el resto del lote.
 */
class StoreItemPhotos
{
    public function __construct(private readonly PhotoOptimizer $optimizer) {}

    /**
     * @param  array<int, mixed>  $files
     * @return int número de fotos guardadas
     */
    public function handle(ReportItem $item, array $files): int
    {
        $order = (int) $item->photos()->max('sort_order');
        $stored = 0;
        $limit = max(1, (int) config('reports.photos.max_per_item', 60));
        $remaining = max(0, $limit - $item->photos()->count());

        foreach ($files as $file) {
            if (! $file || $remaining <= 0) {
                break;
            }

            try {
                $this->store($item, $file, ++$order);
                $stored++;
                $remaining--;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($stored > 0) {
            $item->updated_in_app_at = now();
            $item->save();
        }

        return $stored;
    }

    private function store(ReportItem $item, mixed $file, int $order): void
    {
        $uuid = (string) Str::uuid();
        $directory = "reports/{$item->report_id}/items/{$item->id}";
        $path = "{$directory}/{$uuid}.jpg";
        $thumbPath = "{$directory}/thumbs/{$uuid}.jpg";

        $meta = $this->optimizer->optimize($file->getRealPath(), Storage::disk('public')->path($path));
        $this->optimizer->optimize($file->getRealPath(), Storage::disk('public')->path($thumbPath), PhotoOptimizer::THUMB_DIMENSION, 70);

        $item->photos()->create([
            'source' => 'upload',
            'path' => $path,
            'thumb_path' => $thumbPath,
            'original_name' => $file->getClientOriginalName(),
            'layout' => $item->photo_layout ?? 'pair',
            'sort_order' => $order,
            'width' => $meta['width'],
            'height' => $meta['height'],
            'size_bytes' => $meta['size'],
        ]);
    }
}
