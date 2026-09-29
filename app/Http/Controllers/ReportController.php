<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportAiDraft;
use App\Jobs\GenerateReportPdf;
use App\Jobs\SyncReportDrivePhotos;
use App\Models\CompanySetting;
use App\Models\Report;
use App\Models\ReportAiRun;
use App\Models\ReportImport;
use App\Models\ReportItem;
use App\Services\Drive\DriveClient;
use App\Services\Photos\CollageBuilder;
use App\Services\Reports\ReportAiReportService;
use App\Services\Reports\ReportExcelExporter;
use App\Services\Text\TextTemplateRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const EXCEL_MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const PDF_MIME_TYPE = 'application/pdf';

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
        $renderer = new ImageRenderer(new RendererStyle(150), new SvgImageBackEnd);

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
     * Encola un borrador del agente IA sin cambiar el informe existente.
     */
    public function generateAiDraft(Report $report, ReportAiReportService $service): RedirectResponse
    {
        Gate::authorize('update', $report);
        abort_unless(config('reports.ai.enabled'), 404);

        if (! filled(config('reports.ai.api_key'))) {
            return back()->with('error', 'El agente IA está activo, pero falta configurar OPENAI_API_KEY.');
        }

        $run = DB::transaction(function () use ($report, $service): ?ReportAiRun {
            $lockedReport = Report::query()->lockForUpdate()->findOrFail($report->id);
            $latest = $lockedReport->aiRuns()->first();
            if ($latest && in_array($latest->status, ['queued', 'processing'], true)) {
                return null;
            }

            $lockedReport->load(['municipality.department', 'items.photos']);

            return ReportAiRun::query()->create([
                'report_id' => $lockedReport->id,
                'user_id' => auth()->id(),
                'status' => 'queued',
                'model' => (string) config('reports.ai.model'),
                'prompt_version' => (string) config('reports.ai.prompt_version', ReportAiReportService::PROMPT_VERSION),
                'source_hash' => $service->sourceHash($lockedReport),
            ]);
        });

        if ($run === null) {
            return back()->with('status', 'Ya hay un borrador IA en proceso.');
        }

        GenerateReportAiDraft::dispatch($run);

        return back()->with('status', 'El borrador IA se está generando en segundo plano.');
    }

    /**
     * Estado y resultado limitado para el panel del informe.
     */
    public function aiStatus(Report $report): JsonResponse
    {
        Gate::authorize('view', $report);
        abort_unless(config('reports.ai.enabled'), 404);

        $run = $report->aiRuns()->first();
        $completedAt = $run?->completed_at;

        return response()->json([
            'status' => $run === null ? 'idle' : $run->status,
            'error' => $run?->error,
            'run_id' => $run?->id,
            'completed_at' => $completedAt?->toIso8601String(),
        ]);
    }

    /** Cancela una ejecución pendiente o descarta el resultado si la llamada ya comenzó. */
    public function cancelAiDraft(Report $report, ReportAiRun $run): RedirectResponse
    {
        Gate::authorize('update', $report);
        abort_unless(config('reports.ai.enabled'), 404);
        abort_unless($run->report_id === $report->id, 404);

        $cancelled = DB::transaction(function () use ($report, $run): bool {
            $lockedRun = ReportAiRun::query()
                ->where('report_id', $report->id)
                ->lockForUpdate()
                ->findOrFail($run->id);

            if (! in_array($lockedRun->status, ['queued', 'processing'], true)) {
                return false;
            }

            $lockedRun->update([
                'status' => 'cancelled',
                'error' => 'Cancelado por el usuario.',
                'completed_at' => now(),
            ]);

            return true;
        });

        return back()->with(
            $cancelled ? 'status' : 'error',
            $cancelled
                ? 'Generación cancelada. Si la llamada a IA ya había comenzado, puede terminar y generar un costo; su resultado será descartado.'
                : 'La ejecución ya terminó o fue cancelada.',
        );
    }

    /**
     * Aplica únicamente los textos que el usuario aprobó desde el último borrador.
     */
    public function approveAiDraft(Report $report, ReportAiRun $run, ReportAiReportService $service): RedirectResponse
    {
        Gate::authorize('update', $report);
        abort_unless(config('reports.ai.enabled'), 404);
        abort_unless($run->report_id === $report->id && $run->status === 'ready', 404);

        if (! hash_equals($run->source_hash, $service->sourceHash($report))) {
            return back()->with('error', 'El informe cambió después de generar el borrador. Genere una nueva revisión IA antes de aprobarlo.');
        }

        $result = $run->result ?? [];
        $reportDraft = is_array($result['report'] ?? null) ? $result['report'] : [];
        $allowedReportFields = ['introduction', 'event_description', 'conclusion'];
        $reportChanges = collect($allowedReportFields)
            ->mapWithKeys(fn (string $field): array => [$field => trim((string) ($reportDraft[$field] ?? ''))])
            ->filter(fn (string $value): bool => $value !== '')
            ->all();

        DB::transaction(function () use ($report, $run, $reportChanges, $result): void {
            if ($reportChanges !== []) {
                $report->update($reportChanges);
            }

            $itemsByRef = $report->items()->get()->keyBy('ref');
            $suggestions = is_array($result['items'] ?? null) ? $result['items'] : [];
            foreach ($suggestions as $suggestion) {
                if (! is_array($suggestion)) {
                    continue;
                }

                $ref = trim((string) ($suggestion['ref'] ?? ''));
                $narrative = trim((string) ($suggestion['revised_narrative'] ?? ''));
                $item = $itemsByRef->get($ref);

                if ($item && $narrative !== '') {
                    $item->update(['narrative' => $narrative]);
                }
            }

            $run->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        return back()->with('status', 'El borrador IA fue aprobado y aplicado al informe.');
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

        return Storage::disk('local')->download($report->pdf_path, "informe-{$report->contract_number}.pdf", [
            'Content-Type' => self::PDF_MIME_TYPE,
        ]);
    }

    /**
     * Exporta el informe a la plantilla de Excel para completarlo fuera.
     */
    public function downloadExcel(Report $report, ReportExcelExporter $exporter): StreamedResponse
    {
        Gate::authorize('view', $report);

        $path = $exporter->export($report);

        return Storage::disk('local')->download($path, "informe-{$report->contract_number}.xlsx", [
            'Content-Type' => self::EXCEL_MIME_TYPE,
        ]);
    }

    /**
     * Descarga el Excel original de una carga (versión) del informe.
     */
    public function downloadImport(Report $report, ReportImport $import): StreamedResponse
    {
        Gate::authorize('view', $report);

        abort_unless($import->report_id === $report->id, 404);
        abort_unless($import->file_path && Storage::disk('local')->exists($import->file_path), 404);

        return Storage::disk('local')->download($import->file_path, $import->original_name, [
            'Content-Type' => self::EXCEL_MIME_TYPE,
        ]);
    }

    private function loadReport(Report $report): Report
    {
        $relations = [
            'municipality.department',
            'items.photos',
            'imports.user',
            'imports.activityLogs',
        ];

        if (config('reports.ai.enabled')) {
            $relations[] = 'aiRuns.user';
            $relations[] = 'aiRuns.approver';
        }

        return $report->load($relations);
    }
}
