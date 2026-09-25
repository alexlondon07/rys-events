<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Contracts\View\View;

class ReportController extends Controller
{
    public function show(Report $report): View
    {
        return view('reports.show', [
            'report' => $this->loadReport($report),
        ]);
    }

    public function preview(Report $report): View
    {
        return view('reports.preview', [
            'report' => $this->loadReport($report),
        ]);
    }

    private function loadReport(Report $report): Report
    {
        return $report->load([
            'municipality.department',
            'items.photos',
            'imports',
            'activityLogs.user',
            'activityLogs.import',
        ]);
    }
}
