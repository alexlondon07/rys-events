<?php

namespace App\Services\Drive;

/**
 * Acceso a Google Drive para traer las fotos de evidencia.
 *
 * Se abstrae detrás de esta interfaz para poder probar la sincronización sin
 * credenciales reales: en los tests se reemplaza por un doble.
 */
interface DriveClient
{
    /**
     * ¿Hay credenciales configuradas para hablar con Drive?
     */
    public function isConfigured(): bool;

    /**
     * Descarga un archivo de Drive a una ruta temporal local.
     *
     * @return string ruta absoluta del archivo temporal (el llamador lo borra)
     *
     * @throws DriveException
     */
    public function download(string $fileId): string;

    /**
     * Lista las imágenes de una carpeta de Drive.
     *
     * @return list<array{id:string, name:string, mime_type:string}>
     *
     * @throws DriveException
     */
    public function listImages(string $folderId): array;
}
