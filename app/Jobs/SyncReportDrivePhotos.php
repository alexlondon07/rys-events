<?php

namespace App\Jobs;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Encola la sincronización de fotos de Drive de todos los ítems de un informe.
 *
 * Cada ítem va en su propio job para que un archivo sin permiso no detenga el
 * resto y para poder reintentar solo el ítem que falló.
 */
class SyncReportDrivePhotos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public Report $report) {}

    public function handle(): void
    {
        $this->report->items()->get()->each(
            fn ($item) => SyncItemDrivePhotos::dispatch($item),
        );
    }
}
