<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use App\Jobs\GenerateReportPdf;
use App\Jobs\SyncReportDrivePhotos;
use App\Models\CompanySetting;
use App\Models\Report;
use App\Models\ReportImport;
use App\Models\ReportItem;
use App\Services\Drive\DriveClient;
use App\Services\Photos\CollageBuilder;
use App\Services\Reports\ReportExcelExporter;
use App\Services\Text\TextTemplateRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function show(Request $request, Report $report): View
    {
        Gate::authorize('view', $report);

        $report = $this->loadReport($report);
        $selectedItem = trim((string) $request->query('item')) ?: null;

        $activity = fn () => $report->activityLogs()
            ->when($selectedItem, fn ($query) => $query->where('item_ref', $selectedItem));

        return view('reports.show', [
            'report' => $report,
            'recentActivity' => $activity()->with('user')->latest('id')->limit(12)->get(),
            'activityCount' => $activity()->count(),
            'selectedItem' => $selectedItem,
        ]);
    }

    public function preview(Report $report): View
    {
        Gate::authorize('view', $report);

        return $this->renderPreview($report);
    }

    public function createPublicShare(Report $report): RedirectResponse
    {
        Gate::authorize('update', $report);
        abort_unless($report->status === 'final', 422, 'Finalice el informe antes de compartirlo.');

        $report->forceFill([
            'public_share_token' => Str::random(64),
            'public_share_enabled_at' => now(),
        ])->save();

        return back()->with('status', 'Enlace público generado. Compártalo únicamente con el cliente.');
    }

    public function revokePublicShare(Report $report): RedirectResponse
    {
        Gate::authorize('update', $report);

        $report->forceFill([
            'public_share_token' => null,
            'public_share_enabled_at' => null,
        ])->save();

        return back()->with('status', 'El enlace público fue revocado.');
    }

    public function publicShow(string $token): View
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1, 404);

        $report = Report::query()
            ->where('public_share_token', $token)
            ->where('status', 'final')
            ->firstOrFail();

        return $this->renderPreview($report, true);
    }

    private function renderPreview(Report $report, bool $isPublic = false): View
    {
        $report = $this->loadReport($report);
        $renderer = app(TextTemplateRenderer::class);
        $collageBuilder = app(CollageBuilder::class);

        $collages = $report->items
            ->where('photo_layout', 'collage')
            ->mapWithKeys(function (ReportItem $item) use ($collageBuilder): array {
                $urls = [];

                foreach ($item->photos->chunk($item->photosPerPage()) as $index => $chunk) {
                    $path = $collageBuilder->build($item, $chunk);
                    $urls[$index] = $path ? asset('storage/'.$path) : null;
                }

                return [$item->id => $urls];
            });

        $publicShareUrl = $report->public_share_token
            ? route('reports.public.show', ['token' => $report->public_share_token])
            : null;

        return view('reports.preview', [
            'report' => $report,
            'company' => CompanySetting::current(),
            'collages' => $collages,
            'standardTexts' => $report->items->mapWithKeys(fn (ReportItem $item): array => [
                $item->id => $item->add_standard_texts ? $renderer->standardTexts($report, $item) : '',
            ]),
            'isPublic' => $isPublic,
            'publicShareUrl' => $publicShareUrl,
            'publicShareQr' => $publicShareUrl ? $this->qrCode($publicShareUrl) : null,
        ]);
    }

    private function qrCode(string $value): string
    {
        $renderer = new ImageRenderer(new RendererStyle(150), new SvgImageBackEnd());

        return base64_encode((new Writer($renderer))->writeString($value));
    }

    /**
     * Vista que Chrome renderiza para generar el PDF (URL firmada, sin sesión).
     */
    public function pdfRender(Report $report): View
    {
        return $this->renderPreview($report);
    }

    /**
     * Encola la sincronización de fotos de Drive de todos los ítems.
     */
    public function syncDrive(Report $report): RedirectResponse
    {
        Gate::authorize('update', $report);

        if (! app(DriveClient::class)->isConfigured()) {
            return back()->with('error', 'Google Drive no está configurado. Defina la cuenta de servicio para traer las fotos.');
        }

        SyncReportDrivePhotos::dispatch($report);

        return back()->with('status', 'Sincronización de fotos de Drive en cola. Aparecerán al terminar.');
    }

    /**
     * Encola la generación del PDF del informe.
     */
    public function generatePdf(Report $report): RedirectResponse
    {
        Gate::authorize('update', $report);

        if (in_array($report->pdf_status, ['queued', 'processing'], true)) {
            return back()->with('status', 'El PDF ya se está generando.');
        }

        $report->update(['pdf_status' => 'queued', 'pdf_error' => null]);
        GenerateReportPdf::dispatch($report);

        return back()->with('status', 'El PDF se está generando en segundo plano. La descarga se habilita al terminar.');
    }

    /**
     * Estado de la generación del PDF, consultado por la pantalla del informe.
     */
    public function pdfStatus(Report $report): JsonResponse
    {
        Gate::authorize('view', $report);

        return response()->json([
            'status' => $report->pdf_status ?: 'idle',
            'ready' => $report->pdf_status === 'ready' && (bool) $report->pdf_path,
            'error' => $report->pdf_error,
        ]);
    }

    /**
     * Descarga el PDF ya generado del informe.
     */
    public function downloadPdf(Report $report): StreamedResponse
    {
        Gate::authorize('view', $report);

        abort_unless($report->pdf_path && Storage::disk('local')->exists($report->pdf_path), 404);

        return Storage::disk('local')->download($report->pdf_path, "informe-{$report->contract_number}.pdf");
    }

    /**
     * Exporta el informe a la plantilla de Excel para completarlo fuera.
     */
    public function downloadExcel(Report $report, ReportExcelExporter $exporter): StreamedResponse
    {
        Gate::authorize('view', $report);

        $path = $exporter->export($report);

        return Storage::disk('local')->download($path, "informe-{$report->contract_number}.xlsx");
    }

    /**
     * Descarga el Excel original de una carga (versión) del informe.
     */
    public function downloadImport(Report $report, ReportImport $import): StreamedResponse
    {
        Gate::authorize('view', $report);

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
