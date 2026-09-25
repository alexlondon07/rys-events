<?php

namespace App\Observers;

use App\Models\Report;
use App\Models\ReportItem;
use App\Services\ReportActivity\ReportActivityLogger;
use App\Support\ActivityLogContext;

class ReportItemObserver
{
    public function __construct(private readonly ReportActivityLogger $logger) {}

    public function created(ReportItem $item): void
    {
        $report = $this->reportFor($item);

        if ($report === null) {
            return;
        }

        $this->logger->logCreated($report, $item, 'app', null, auth()->id());
    }

    public function updated(ReportItem $item): void
    {
        $report = $this->reportFor($item);

        if ($report === null) {
            return;
        }

        $this->logger->log($report, $this->logger->capture($item), 'app', $item, null, auth()->id());
    }

    public function deleted(ReportItem $item): void
    {
        $report = $this->reportFor($item);

        if ($report === null) {
            return;
        }

        $this->logger->logDeleted($report, $item, 'app', null, auth()->id());
    }

    private function reportFor(ReportItem $item): ?Report
    {
        if (! ActivityLogContext::isRecording()) {
            return null;
        }

        return $item->report;
    }
}
