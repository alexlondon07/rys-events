<?php

namespace App\Jobs;

use App\Models\ReportAiRun;
use App\Services\Reports\ReportAiReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class GenerateReportAiDraft implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(public ReportAiRun $run) {}

    public function handle(ReportAiReportService $service): void
    {
        $run = $this->run->fresh(['report.items.photos']);
        if (! $run) {
            return;
        }

        $report = $run->report;
        $run->update([
            'status' => 'processing',
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $generated = $service->generate($report);

            $run->update([
                'status' => 'ready',
                'result' => $generated['result'],
                'input_tokens' => $generated['input_tokens'],
                'output_tokens' => $generated['output_tokens'],
                'cost_usd' => $generated['cost_usd'],
                'completed_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->run->update([
            'status' => 'failed',
            'error' => $exception?->getMessage() ?: 'La generación del borrador IA falló.',
            'completed_at' => now(),
        ]);
    }
}
