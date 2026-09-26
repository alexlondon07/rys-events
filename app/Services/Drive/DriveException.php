<?php

namespace App\Services\Drive;

use RuntimeException;

/**
 * Error al hablar con Google Drive (sin permiso, archivo inexistente, etc.).
 */
class DriveException extends RuntimeException {}
