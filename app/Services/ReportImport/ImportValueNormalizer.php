<?php

namespace App\Services\ReportImport;

use Illuminate\Support\Str;

class ImportValueNormalizer
{
    public function key(?string $value): string
    {
        return Str::of((string) $value)->ascii()->lower()->squish()->toString();
    }

    public function type(mixed $value): ?string
    {
        return match ($this->key((string) $value)) {
            'artistico', 'artistic' => 'artistic',
            'tecnico', 'technical' => 'technical',
            default => null,
        };
    }

    public function boolean(mixed $value): ?bool
    {
        return match ($this->key((string) $value)) {
            'si', 'yes', '1', 'true' => true,
            'no', '0', 'false' => false,
            default => null,
        };
    }

    /** @return array{0:?string, 1:bool} Canonical value and whether it was normalized. */
    public function unit(mixed $value): array
    {
        $original = trim((string) $value);
        $key = rtrim($this->key($original), '.');
        $canonical = match ($key) {
            'dia', 'dias' => 'días',
            'camion', 'camiones' => 'camiones',
            'agrupada', 'agrupado' => 'agrupada',
            'unidad', 'unidades' => 'unidades',
            'hora', 'horas' => 'horas',
            default => null,
        };

        return [$canonical, $canonical !== null && $original !== $canonical];
    }

    public function layout(mixed $value): ?string
    {
        return match ($this->key((string) $value)) {
            '1 por pagina', 'una por pagina', 'single' => 'single',
            '2 por pagina', 'dos por pagina', 'pair' => 'pair',
            'collage 2x2', 'collage 2x1', 'collage' => 'collage',
            default => null,
        };
    }

    public function driveFolderId(mixed $value): ?string
    {
        $url = trim((string) $value);

        if ($url === '') {
            return null;
        }

        return preg_match('~(?:drive\.google\.com/(?:drive/(?:u/\d+/)?folders|folders)/)([A-Za-z0-9_-]+)~i', $url, $matches)
            ? $matches[1]
            : null;
    }

    public function driveFileId(mixed $value): ?string
    {
        $url = trim((string) $value);

        if ($url === '' || str_contains($url, '/folders/')) {
            return null;
        }

        foreach ([
            '~drive\.google\.com/file/d/([A-Za-z0-9_-]+)~i',
            '~[?&]id=([A-Za-z0-9_-]+)~i',
        ] as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
