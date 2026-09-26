<?php

namespace App\Services\Drive;

use Google\Client as GoogleClient;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Cliente real de Google Drive v3.
 *
 * Usa una cuenta de servicio (recomendado): el cliente comparte la carpeta raíz
 * de eventos con el correo de la cuenta como lector, una sola vez. Como
 * alternativa acepta una API key para carpetas públicas ("cualquiera con el
 * enlace").
 *
 * Se habla con la API por HTTP (usando el cliente autenticado de Google) para
 * tener tipos claros y controlar los errores con {@see DriveException}.
 */
class GoogleDriveClient implements DriveClient
{
    private const API = 'https://www.googleapis.com/drive/v3';

    public function isConfigured(): bool
    {
        return $this->credentialsPath() !== null || $this->apiKey() !== null;
    }

    public function download(string $fileId): string
    {
        try {
            $response = $this->http()->request('GET', self::API."/files/{$fileId}", [
                'query' => ['alt' => 'media', 'supportsAllDrives' => 'true'],
            ]);
            $this->ensureSuccess($response, "No se pudo descargar la foto de Drive ({$fileId}).");
            $body = (string) $response->getBody();
        } catch (DriveException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DriveException("No se pudo descargar la foto de Drive ({$fileId}): {$exception->getMessage()}", previous: $exception);
        }

        $temporary = tempnam(sys_get_temp_dir(), 'drive_');

        if ($temporary === false || file_put_contents($temporary, $body) === false) {
            throw new DriveException('No se pudo guardar temporalmente la foto descargada.');
        }

        return $temporary;
    }

    public function listImages(string $folderId): array
    {
        try {
            $images = [];
            $pageToken = null;

            do {
                $query = [
                    'q' => sprintf("'%s' in parents and trashed = false and mimeType contains 'image/'", $folderId),
                    'fields' => 'nextPageToken, files(id, name, mimeType)',
                    'pageSize' => 100,
                    'supportsAllDrives' => 'true',
                    'includeItemsFromAllDrives' => 'true',
                ];

                if ($pageToken) {
                    $query['pageToken'] = $pageToken;
                }

                $response = $this->http()->request('GET', self::API.'/files', ['query' => $query]);
                $this->ensureSuccess($response, "No se pudo listar la carpeta de Drive ({$folderId}).");

                $data = json_decode((string) $response->getBody(), true);

                foreach ($data['files'] ?? [] as $file) {
                    $images[] = [
                        'id' => (string) ($file['id'] ?? ''),
                        'name' => (string) ($file['name'] ?? ''),
                        'mime_type' => (string) ($file['mimeType'] ?? ''),
                    ];
                }

                $pageToken = $data['nextPageToken'] ?? null;
            } while ($pageToken);

            return $images;
        } catch (DriveException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DriveException("No se pudo listar la carpeta de Drive ({$folderId}): {$exception->getMessage()}", previous: $exception);
        }
    }

    private function http(): ClientInterface
    {
        if (! $this->isConfigured()) {
            throw new DriveException('Google Drive no está configurado. Defina GOOGLE_DRIVE_CREDENTIALS (cuenta de servicio) o GOOGLE_DRIVE_API_KEY.');
        }

        $client = new GoogleClient;

        if ($path = $this->credentialsPath()) {
            $client->setAuthConfig($path);
            $client->addScope('https://www.googleapis.com/auth/drive.readonly');
        } else {
            $client->setDeveloperKey((string) $this->apiKey());
        }

        $client->authorize();

        return $client->getHttpClient();
    }

    private function ensureSuccess(ResponseInterface $response, string $message): void
    {
        $status = $response->getStatusCode();

        if ($status >= 400) {
            throw new DriveException("{$message} (HTTP {$status})");
        }
    }

    private function credentialsPath(): ?string
    {
        $path = config('services.google.drive.credentials');

        return is_string($path) && $path !== '' && is_file($path) ? $path : null;
    }

    private function apiKey(): ?string
    {
        $key = config('services.google.drive.api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
