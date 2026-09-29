<?php

namespace App\Jobs;

use App\Models\ReportAiRun;
use App\Services\Reports\ReportAiReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
        $run = DB::transaction(function (): ?ReportAiRun {
            $lockedRun = ReportAiRun::query()->lockForUpdate()->find($this->run->getKey());
            if (! $lockedRun || ! in_array($lockedRun->status, ['queued', 'processing'], true)) {
                return null;
            }

            $lockedRun->update([
                'status' => 'processing',
                'started_at' => $lockedRun->started_at ?? now(),
                'error' => null,
            ]);

            return $lockedRun->fresh(['report.items.photos']);
        });

        if (! $run) {
            return;
        }

        $report = $run->report;

        try {
            $generated = $service->generate($report);

            DB::transaction(function () use ($run, $generated): void {
                $lockedRun = ReportAiRun::query()->lockForUpdate()->find($run->id);
                if (! $lockedRun || $lockedRun->status === 'cancelled') {
                    return;
                }

                $lockedRun->update([
                    'status' => 'ready',
                    'result' => $generated['result'],
                    'input_tokens' => $generated['input_tokens'],
                    'output_tokens' => $generated['output_tokens'],
                    'cost_usd' => $generated['cost_usd'],
                    'completed_at' => now(),
                    'error' => null,
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        ReportAiRun::query()
            ->whereKey($this->run->getKey())
            ->whereIn('status', ['queued', 'processing'])
            ->update([
                'status' => 'failed',
                'error' => $exception?->getMessage() ?: 'La generación del borrador IA falló.',
                'completed_at' => now(),
            ]);
    }
}
