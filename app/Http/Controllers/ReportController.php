<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Report;
use App\Models\ReportImport;
use App\Models\ReportItem;
use App\Services\Reports\ReportExcelExporter;
use App\Services\Reports\ReportPdfGenerator;
use App\Services\Text\TextTemplateRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function show(Report $report): View
    {
        $report = $this->loadReport($report);

        return view('reports.show', [
            'report' => $report,
            'recentActivity' => $report->activityLogs()->with('user')->latest('id')->limit(8)->get(),
            'activityCount' => $report->activityLogs()->count(),
        ]);
    }

    public function preview(Report $report): View
    {
        $report = $this->loadReport($report);
        $renderer = app(TextTemplateRenderer::class);

        return view('reports.preview', [
            'report' => $report,
            'company' => CompanySetting::current(),
            'standardTexts' => $report->items->mapWithKeys(fn (ReportItem $item): array => [
                $item->id => $item->add_standard_texts ? $renderer->standardTexts($report, $item) : '',
            ]),
        ]);
    }

    /**
     * Vista que Chrome renderiza para generar el PDF (URL firmada, sin sesión).
     */
    public function pdfRender(Report $report): View
    {
        return $this->preview($report);
    }

    /**
     * Genera el PDF del informe y lo guarda en el storage.
     */
    public function generatePdf(Report $report, ReportPdfGenerator $generator): RedirectResponse
    {
        try {
            $generator->generate($report);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'No se pudo generar el PDF. Verifique que Chrome esté disponible e intente de nuevo.');
        }

        return back()->with('status', 'PDF generado correctamente.');
    }

    /**
     * Descarga el PDF ya generado del informe.
     */
    public function downloadPdf(Report $report): StreamedResponse
    {
        abort_unless($report->pdf_path && Storage::disk('local')->exists($report->pdf_path), 404);

        return Storage::disk('local')->download($report->pdf_path, "informe-{$report->contract_number}.pdf");
    }

    /**
     * Exporta el informe a la plantilla de Excel para completarlo fuera.
     */
    public function downloadExcel(Report $report, ReportExcelExporter $exporter): StreamedResponse
    {
        $path = $exporter->export($report);

        return Storage::disk('local')->download($path, "informe-{$report->contract_number}.xlsx");
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
        ]);
    }
}
