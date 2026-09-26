<?php

namespace App\Observers;

use App\Models\Report;
use App\Services\ReportActivity\ReportActivityLogger;
use App\Support\ActivityLogContext;

class ReportObserver
{
    public function __construct(private readonly ReportActivityLogger $logger) {}

    public function created(Report $report): void
    {
        if (! ActivityLogContext::isRecording()) {
            return;
        }

        $this->logger->logCreated($report, null, 'app', null, auth()->id());
    }

    public function updated(Report $report): void
    {
        if (! ActivityLogContext::isRecording()) {
            return;
        }

        $this->logger->log($report, $this->logger->capture($report), 'app', null, null, auth()->id());
    }

    public function deleted(Report $report): void
    {
        if (! ActivityLogContext::isRecording() || $report->isForceDeleting()) {
            return;
        }

        $this->logger->logDeleted($report, null, 'app', null, auth()->id());
    }
}
