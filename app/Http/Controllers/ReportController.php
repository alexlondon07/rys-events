<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportImport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Descarga el Excel original de una carga (versión) del informe.
     */
    public function downloadImport(Report $report, ReportImport $import): StreamedResponse
    {
        abort_unless($import->report_id === $report->id, 404);
        abort_unless($import->file_path && Storage::disk('local')->exists($import->file_path), 404);

        return Storage::disk('local')->download($import->file_path, $import->original_name);
    }

    private function loadReport(Report $report): Report
    {
        return $report->load([
            'municipality.department',
            'items.photos',
            'imports.user',
            'imports.activityLogs',
            'activityLogs.user',
            'activityLogs.import',
        ]);
    }
}
