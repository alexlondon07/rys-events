<?php

namespace App\Support;

/**
 * Controla si los observers registran actividad en la bitácora.
 *
 * La importación desde Excel escribe su propia bitácora con origen "excel",
 * por lo que pausa el registro automático para no duplicar entradas.
 */
class ActivityLogContext
{
    private static bool $recording = true;

    public static function isRecording(): bool
    {
        return self::$recording;
    }

    public static function pause(): void
    {
        self::$recording = false;
    }

    public static function resume(): void
    {
        self::$recording = true;
    }

    public static function withoutRecording(callable $callback): mixed
    {
        $previous = self::$recording;
        self::$recording = false;

        try {
            return $callback();
        } finally {
            self::$recording = $previous;
        }
    }
}
