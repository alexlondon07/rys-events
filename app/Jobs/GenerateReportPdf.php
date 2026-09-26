<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\Reports\ReportPdfGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Genera el PDF de un informe en la cola y deja el estado en `reports.pdf_status`
 * para que la pantalla del informe lo refleje sin bloquear la petición.
 *
 * Estados: `queued` → `processing` → `ready` o `failed`.
 */
class GenerateReportPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public Report $report) {}

    public function handle(ReportPdfGenerator $generator): void
    {
        $this->report->update([
            'pdf_status' => 'processing',
            'pdf_error' => null,
        ]);

        try {
            $path = $generator->generate($this->report);
        } catch (Throwable $exception) {
            report($exception);

            $this->report->update([
                'pdf_status' => 'failed',
                'pdf_error' => $exception->getMessage(),
            ]);

            return;
        }

        $this->report->update([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
            'pdf_status' => 'ready',
            'pdf_error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->report->update([
            'pdf_status' => 'failed',
            'pdf_error' => $exception?->getMessage() ?: 'La generación del PDF falló.',
        ]);
    }
}
