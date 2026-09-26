<?php

namespace Tests\Support;

use App\Services\Drive\DriveClient;
use App\Services\Drive\DriveException;

/**
 * Doble de {@see DriveClient} para probar la sincronización sin credenciales.
 */
class FakeDriveClient implements DriveClient
{
    /** @var array<string, string> fileId => contenido binario */
    public array $files = [];

    /** @var array<string, list<array{id:string, name:string, mime_type:string}>> */
    public array $folders = [];

    /** @var list<string> fileIds que fallan al descargar */
    public array $failDownloads = [];

    public function __construct(private readonly bool $configured = true) {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function download(string $fileId): string
    {
        if (in_array($fileId, $this->failDownloads, true) || ! isset($this->files[$fileId])) {
            throw new DriveException("Sin permiso para el archivo {$fileId}.");
        }

        $temporary = tempnam(sys_get_temp_dir(), 'fake_drive_');
        file_put_contents($temporary, $this->files[$fileId]);

        return $temporary;
    }

    public function listImages(string $folderId): array
    {
        return $this->folders[$folderId] ?? [];
    }

    public function withImage(string $fileId): self
    {
        $this->files[$fileId] = $this->jpeg();

        return $this;
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(1200, 900);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 100, 50));
        ob_start();
        imagejpeg($image, null, 80);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
