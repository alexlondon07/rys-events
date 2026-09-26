<?php

namespace App\Services\Photos;

/**
 * Interpreta el enlace de evidencia de un ítem sin importar el proveedor.
 *
 * Tipos soportados:
 * - `drive_folder`: carpeta de Google Drive (se incrusta con embeddedfolderview).
 * - `drive_file`: archivo de Drive (se muestra la miniatura pública).
 * - `image`: imagen directa (jpg, png, webp, gif, avif, bmp o data URI).
 * - `other`: cualquier otro enlace (se intenta incrustar y se ofrece abrirlo).
 */
class EvidenceLink
{
    public function __construct(public readonly string $url) {}

    public static function make(mixed $value): ?self
    {
        $url = trim((string) $value);

        return $url === '' ? null : new self($url);
    }

    public function type(): string
    {
        if ($this->driveFolderId() !== null) {
            return 'drive_folder';
        }

        if ($this->driveFileId() !== null) {
            return 'drive_file';
        }

        return $this->isDirectImage() ? 'image' : 'other';
    }

    public function label(): string
    {
        return match ($this->type()) {
            'drive_folder' => 'Carpeta de Drive',
            'drive_file' => 'Archivo de Drive',
            'image' => 'Imagen',
            default => 'Enlace',
        };
    }

    public function driveFolderId(): ?string
    {
        return preg_match(
            '~drive\.google\.com/(?:drive/(?:u/\d+/)?folders|folders)/([A-Za-z0-9_-]+)~i',
            $this->url,
            $matches,
        ) ? $matches[1] : null;
    }

    public function driveFileId(): ?string
    {
        if ($this->driveFolderId() !== null) {
            return null;
        }

        if (preg_match('~drive\.google\.com/file/d/([A-Za-z0-9_-]+)~i', $this->url, $matches)) {
            return $matches[1];
        }

        if (preg_match('~^https?://(?:drive|docs)\.google\.com/~i', $this->url)
            && preg_match('~[?&]id=([A-Za-z0-9_-]+)~i', $this->url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function isDirectImage(): bool
    {
        if (preg_match('~^data:image/~i', $this->url)) {
            return true;
        }

        $path = parse_url($this->url, PHP_URL_PATH) ?: '';

        return (bool) preg_match('~\.(jpe?g|png|webp|gif|avif|bmp)$~i', $path);
    }

    /**
     * URL para mostrar como imagen (miniatura de Drive o imagen directa).
     */
    public function imageUrl(): ?string
    {
        if ($fileId = $this->driveFileId()) {
            return 'https://drive.google.com/thumbnail?id='.$fileId.'&sz=w1600';
        }

        return $this->isDirectImage() ? $this->url : null;
    }

    /**
     * URL para incrustar en un iframe (carpeta de Drive u otro proveedor).
     */
    public function embedUrl(): ?string
    {
        if ($folderId = $this->driveFolderId()) {
            return 'https://drive.google.com/embeddedfolderview?id='.$folderId.'#grid';
        }

        return $this->type() === 'other' ? $this->url : null;
    }

    public function openUrl(): string
    {
        return $this->url;
    }

    /**
     * ¿Es seguro incrustar este enlace desde el servidor (Chrome headless para
     * el PDF) y en la vista previa?
     *
     * Bloquea esquemas que no sean http(s), IPs privadas/reservadas y hosts que
     * resuelvan a ellas, para evitar SSRF hacia la red interna. Las imágenes en
     * data URI no salen a la red, así que se permiten.
     */
    public function isServerSafe(): bool
    {
        if (preg_match('~^data:image/~i', $this->url)) {
            return true;
        }

        $scheme = strtolower((string) parse_url($this->url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($this->url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) !== false ? $host : gethostbyname($host);

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
