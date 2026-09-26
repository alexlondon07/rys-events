<?php

namespace App\Services\Photos;

use GdImage;
use RuntimeException;

/**
 * Redimensiona y comprime imágenes con GD (sin dependencias externas).
 * Convierte todo a JPEG para que el PDF no pese de más.
 */
class PhotoOptimizer
{
    public const MAX_DIMENSION = 1500;

    public const QUALITY = 70;

    public const THUMB_DIMENSION = 400;

    /**
     * @return array{width:int, height:int, size:int}
     */
    public function optimize(
        string $sourcePath,
        string $destinationPath,
        ?int $maxDimension = null,
        ?int $quality = null,
    ): array {
        $maxDimension ??= (int) config('reports.photos.max_dimension', self::MAX_DIMENSION);
        $quality ??= (int) config('reports.photos.quality', self::QUALITY);

        $image = $this->resizeToFit($this->load($sourcePath), $maxDimension);
        $width = imagesx($image);
        $height = imagesy($image);

        if (! is_dir($directory = dirname($destinationPath))) {
            mkdir($directory, 0775, true);
        }

        imagejpeg($image, $destinationPath, $quality);
        imagedestroy($image);

        return [
            'width' => $width,
            'height' => $height,
            'size' => (int) (filesize($destinationPath) ?: 0),
        ];
    }

    public function load(string $path): GdImage
    {
        $info = @getimagesize($path);

        if ($info === false) {
            throw new RuntimeException('El archivo no es una imagen válida.');
        }

        $image = match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            'image/gif' => imagecreatefromgif($path),
            default => throw new RuntimeException('Formato de imagen no soportado.'),
        };

        if (! $image instanceof GdImage) {
            throw new RuntimeException('No se pudo leer la imagen.');
        }

        return $image;
    }

    private function resizeToFit(GdImage $image, int $maxDimension): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxDimension / max($width, $height));

        if ($scale >= 1) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        $white = imagecolorallocate($canvas, 255, 255, 255);

        if ($white === false) {
            imagedestroy($canvas);
            imagedestroy($image);

            throw new RuntimeException('No se pudo preparar el lienzo de la imagen.');
        }

        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $canvas;
    }
}
