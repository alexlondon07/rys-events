<?php

namespace App\Services\Photos;

use App\Models\ReportItem;
use App\Models\ReportItemPhoto;
use GdImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Compone las fotos de una página en una sola imagen tipo collage.
 *
 * Se usa cuando la distribución del ítem es "collage": las fotos de cada
 * página (hasta 4) se recortan al centro y se pegan en una rejilla 2x2 con GD.
 * El resultado se cachea en el disco público; solo se vuelve a componer cuando
 * cambia el conjunto, el orden o el contenido de las fotos.
 *
 * Devuelve `null` cuando alguna foto no es una subida local (por ejemplo un
 * enlace de Drive) o el archivo no existe, para que la vista caiga en la
 * rejilla normal.
 */
class CollageBuilder
{
    public function __construct(private readonly PhotoOptimizer $optimizer) {}

    /**
     * @param  Collection<int, ReportItemPhoto>  $photos
     * @return string|null ruta relativa en el disco público
     */
    public function build(ReportItem $item, Collection $photos): ?string
    {
        $cells = $this->localCells($photos);

        if ($cells === []) {
            return null;
        }

        $relative = $this->cachePath($item, $cells);
        $absolute = Storage::disk('public')->path($relative);

        if (is_file($absolute)) {
            return $relative;
        }

        try {
            $this->compose($cells, $absolute);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return $relative;
    }

    /**
     * @param  Collection<int, ReportItemPhoto>  $photos
     * @return list<array{id:int, updated_at:int, file:string, absolute:string}>
     */
    private function localCells(Collection $photos): array
    {
        $cells = [];

        foreach ($photos as $photo) {
            if ($photo->source !== 'upload' || ! $photo->path) {
                return [];
            }

            $absolute = Storage::disk('public')->path($photo->path);

            if (! is_file($absolute)) {
                return [];
            }

            $cells[] = [
                'id' => (int) $photo->id,
                'updated_at' => $photo->updated_at?->getTimestamp() ?? 0,
                'file' => basename($photo->path),
                'absolute' => $absolute,
            ];
        }

        return $cells;
    }

    /**
     * @param  list<array{id:int, updated_at:int, file:string, absolute:string}>  $cells
     */
    private function cachePath(ReportItem $item, array $cells): string
    {
        $fingerprint = implode('|', array_map(
            fn (array $cell): string => "{$cell['id']}:{$cell['updated_at']}:{$cell['file']}",
            $cells,
        ));

        $key = md5($item->id.'|'.$item->photo_layout.'|'.$fingerprint);

        return "reports/{$item->report_id}/items/{$item->id}/collages/{$key}.jpg";
    }

    /**
     * @param  list<array{id:int, updated_at:int, file:string, absolute:string}>  $cells
     */
    private function compose(array $cells, string $destination): void
    {
        $columns = max(1, min((int) config('reports.collage.columns', 2), count($cells)));
        $rows = max(1, (int) ceil(count($cells) / $columns));
        $cellWidth = max(1, (int) config('reports.collage.cell_width', 800));
        $cellHeight = max(1, (int) config('reports.collage.cell_height', 600));
        $gap = max(0, (int) config('reports.collage.gap', 8));
        $quality = max(1, min(100, (int) config('reports.collage.quality', 82)));

        $width = $columns * $cellWidth + ($columns - 1) * $gap;
        $height = $rows * $cellHeight + ($rows - 1) * $gap;

        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, $this->background($canvas));

        foreach ($cells as $index => $cell) {
            $column = $index % $columns;
            $row = (int) floor($index / $columns);
            $x = $column * ($cellWidth + $gap);
            $y = $row * ($cellHeight + $gap);

            $image = $this->optimizer->load($cell['absolute']);
            $covered = $this->cover($image, $cellWidth, $cellHeight);
            imagecopy($canvas, $covered, $x, $y, 0, 0, $cellWidth, $cellHeight);
            imagedestroy($covered);
        }

        if (! is_dir($directory = dirname($destination))) {
            mkdir($directory, 0775, true);
        }

        imagejpeg($canvas, $destination, $quality);
        imagedestroy($canvas);
    }

    /**
     * Recorta la imagen al centro para que cubra la celda sin deformarla.
     */
    private function cover(GdImage $image, int $cellWidth, int $cellHeight): GdImage
    {
        if ($cellWidth < 1 || $cellHeight < 1) {
            throw new RuntimeException('Tamaño de celda inválido.');
        }

        $width = max(1, imagesx($image));
        $height = max(1, imagesy($image));
        $scale = max($cellWidth / $width, $cellHeight / $height);
        $scaledWidth = max($cellWidth, (int) ceil($width * $scale));
        $scaledHeight = max($cellHeight, (int) ceil($height * $scale));

        $scaled = imagecreatetruecolor($scaledWidth, $scaledHeight);
        imagefill($scaled, 0, 0, $this->background($scaled));
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $width, $height);
        imagedestroy($image);

        $cell = imagecreatetruecolor($cellWidth, $cellHeight);
        imagefill($cell, 0, 0, $this->background($cell));
        imagecopy(
            $cell,
            $scaled,
            0,
            0,
            (int) floor(($scaledWidth - $cellWidth) / 2),
            (int) floor(($scaledHeight - $cellHeight) / 2),
            $cellWidth,
            $cellHeight,
        );
        imagedestroy($scaled);

        return $cell;
    }

    private function background(GdImage $canvas): int
    {
        $white = imagecolorallocate($canvas, 255, 255, 255);

        if ($white === false) {
            throw new RuntimeException('No se pudo preparar el fondo del collage.');
        }

        return $white;
    }
}
