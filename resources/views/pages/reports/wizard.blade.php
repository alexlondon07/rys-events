<?php

use App\Actions\Reports\AddReportItemToReport;
use App\Actions\Reports\StoreItemPhotos;
use App\Actions\Reports\SyncItemDriveLinks;
use App\Models\ItemCatalog;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\ReportItemPhoto;
use App\Models\TextTemplate;
use App\Services\Photos\EvidenceLink;
use App\Services\Photos\PhotoOptimizer;
use App\Services\Text\TextTemplateRenderer;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Editar informe')] class extends Component {
    use WithFileUploads;

    private const REPORT_FIELDS = [
        'contract_number', 'report_date', 'period_start', 'period_end', 'subject',
        'contract_object', 'event_name', 'event_start', 'event_end', 'cover_url',
        'introduction', 'event_description', 'conclusion', 'signer_name',
    ];

    private const UNITS = ['días', 'camiones', 'agrupada', 'unidades', 'horas'];

    #[Locked]
    public int $reportId;

    public int $step = 1;

    public string $contract_number = '';

    public int|string|null $department_id = null;

    public int|string|null $municipality_id = null;

    public ?string $report_date = null;

    public ?string $period_start = null;

    public ?string $period_end = null;

    public string $subject = '';

    public string $contract_object = '';

    public string $event_name = '';

    public ?string $event_start = null;

    public ?string $event_end = null;

    public string $cover_url = '';

    public $cover = null;

    public ?string $existingCover = null;

    public string $introduction = '';

    public string $event_description = '';

    public string $conclusion = '';

    public string $signer_name = '';

    public string $status = 'draft';

    /** @var list<array<string, mixed>> */
    public array $items = [];

    /** @var array<int, list<mixed>> */
    public array $uploads = [];

    /** Cambia para reiniciar los selectores de plantilla al placeholder. */
    public int $templateReset = 0;

    /** @var list<array{id:int, name:string}> */
    public array $municipalityOptions = [];

    public function mount(Report $report): void
    {
        $this->reportId = $report->id;
        $report->load(['municipality.department', 'items.photos']);

        $this->contract_number = (string) $report->contract_number;
        $this->department_id = $report->municipality?->department_id;
        $this->municipality_id = $report->municipality_id;
        $this->report_date = $report->report_date?->format('Y-m-d');
        $this->period_start = $report->period_start?->format('Y-m-d');
        $this->period_end = $report->period_end?->format('Y-m-d');
        $this->subject = (string) $report->subject;
        $this->contract_object = (string) $report->contract_object;
        $this->event_name = (string) $report->event_name;
        $this->event_start = $report->event_start?->format('Y-m-d');
        $this->event_end = $report->event_end?->format('Y-m-d');
        $this->cover_url = (string) $report->cover_url;
        $this->existingCover = $report->coverImageUrl();
        $this->introduction = (string) $report->introduction;
        $this->event_description = (string) $report->event_description;
        $this->conclusion = (string) $report->conclusion;
        $this->signer_name = (string) $report->signer_name;
        $this->status = $report->status;
        $this->step = $report->inferredCurrentStep();
        $this->items = $report->items->map($this->itemToArray(...))->values()->all();

        $this->loadMunicipalities();
    }

    public function updated(string $name, mixed $value): void
    {
        if ($name === 'department_id') {
            $this->municipality_id = null;
            $this->loadMunicipalities();
            $this->persistReport();

            return;
        }

        if (str_starts_with($name, 'items.')) {
            $index = (int) explode('.', $name)[1];

            if (preg_match('/^items\.\d+\.photos\.(\d+)\.caption$/', $name, $matches)) {
                $this->persistPhotoCaption($index, (int) $matches[1]);

                return;
            }

            if (str_ends_with($name, '.drive_links')) {
                $this->syncDriveLinks($index);

                return;
            }

            if (str_ends_with($name, '.evidence_url')) {
                $this->syncEvidenceUrl($index);

                return;
            }

            $this->persistItem($index);

            return;
        }

        if (in_array($name, self::REPORT_FIELDS, true) || $name === 'municipality_id') {
            $this->persistReport();
        }
    }

    public function goToStep(int $step): void
    {
        $this->step = max(1, min(6, $step));
        unset($this->catalogItems);
    }

    public function applyTemplate(string $field, string $templateId): void
    {
        if ($templateId === '') {
            return;
        }

        $template = TextTemplate::query()->find($templateId);

        if (! $template) {
            return;
        }

        $renderer = app(TextTemplateRenderer::class);
        $text = $renderer->render($template->body_with_variables, $renderer->variablesFor($this->report()));

        match ($field) {
            'introduction' => $this->introduction = $text,
            'event_description' => $this->event_description = $text,
            'conclusion' => $this->conclusion = $text,
            default => null,
        };

        $this->persistReport();
        $this->templateReset++;
        Flux::toast(variant: 'success', text: 'Plantilla aplicada.');
    }

    public function importCatalogItem(int $catalogId, AddReportItemToReport $action): void
    {
        $type = $this->step === 4 ? 'technical' : 'artistic';
        $catalog = ItemCatalog::query()->where('active', true)->where('type', $type)->find($catalogId);

        if (! $catalog) {
            return;
        }

        $report = $this->report();
        $item = $action->handle($report, $type, [
            'category_label' => $catalog->category_label,
            'specification' => $catalog->specification,
            'narrative' => $catalog->default_narrative,
            'quantity' => $catalog->default_quantity,
            'unit' => $catalog->default_unit,
        ]);

        $report->touch();
        $this->loadItems();
        Flux::toast(variant: 'success', text: "Se agregó {$item->ref} desde el catálogo.");
    }

    public function addItem(string $type, AddReportItemToReport $action): void
    {
        $report = $this->report();
        $item = $action->handle($report, $type, [
            'category_label' => $type === 'artistic' ? 'Nuevo ítem artístico' : 'Nuevo ítem técnico',
        ]);

        $report->touch();
        $this->loadItems();
        Flux::toast(variant: 'success', text: "Se agregó {$item->ref}.");
    }

    public function removeItem(int $index): void
    {
        $item = $this->itemAt($index);

        foreach ($item->photos as $photo) {
            Storage::disk('public')->delete(array_filter([$photo->path, $photo->thumb_path]));
        }

        $item->delete();
        $this->report()->touch();
        $this->loadItems();
        Flux::toast(variant: 'success', text: 'Ítem eliminado.');
    }

    public function uploadPhotos(int $index, StoreItemPhotos $store): void
    {
        $files = array_values(array_filter($this->uploads[$index] ?? []));

        if ($files === []) {
            return;
        }

        $item = $this->itemAt($index);
        $limit = max(1, (int) config('reports.photos.max_per_item', 60));
        $maxSize = max(1, (int) config('reports.photos.max_size_kb', 10240));
        $mimes = implode(',', (array) config('reports.photos.mimes', ['jpg', 'jpeg', 'png', 'webp']));
        $remaining = max(0, $limit - $item->photos()->count());

        if ($remaining <= 0) {
            $this->uploads[$index] = [];
            Flux::toast(variant: 'danger', text: "Este ítem ya alcanzó el máximo de {$limit} fotos.");

            return;
        }

        $requested = count($files);
        $files = array_slice($files, 0, $remaining);

        try {
            $this->validate([
                "uploads.{$index}.*" => ['image', "mimes:{$mimes}", "max:{$maxSize}"],
            ], [
                "uploads.{$index}.*.image" => 'Solo se permiten imágenes.',
                "uploads.{$index}.*.mimes" => 'Formato no permitido. Use JPG, PNG o WEBP (el HEIC del iPhone debe convertirse).',
                "uploads.{$index}.*.max" => 'Cada imagen debe pesar menos de '.round($maxSize / 1024).' MB.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->uploads[$index] = [];
            Flux::toast(variant: 'danger', text: collect($exception->errors())->flatten()->first() ?? 'Archivo no válido.');

            return;
        }

        $stored = $store->handle($item, $files);

        $this->uploads[$index] = [];
        $this->report()->touch();
        $this->loadItems();

        if ($stored > 0) {
            Flux::toast(variant: 'success', text: "{$stored} foto(s) agregada(s).");
        }

        if ($requested > count($files)) {
            Flux::toast(variant: 'warning', text: "Solo se cargaron {$stored} de {$requested} fotos: el ítem admite hasta {$limit}.");
        } elseif ($stored < count($files)) {
            Flux::toast(variant: 'warning', text: 'Algunas fotos no se pudieron procesar. Verifique el formato e intente de nuevo.');
        }
    }

    public function removePhoto(int $index, int $photoId): void
    {
        $photo = $this->itemAt($index)->photos()->find($photoId);

        if (! $photo) {
            return;
        }

        Storage::disk('public')->delete(array_filter([$photo->path, $photo->thumb_path]));
        $photo->delete();
        $this->report()->touch();
        $this->loadItems();
    }

    public function moveItem(int $index, int $direction): void
    {
        $data = $this->items[$index] ?? null;

        if ($data === null) {
            return;
        }

        $list = collect($this->items)->where('type', $data['type'])->values();
        $position = $list->search(fn (array $item): bool => $item['id'] === $data['id']);
        $target = $position === false ? -1 : $position + ($direction < 0 ? -1 : 1);

        if ($position === false || $target < 0 || $target >= $list->count()) {
            return;
        }

        $first = ReportItem::query()->findOrFail($list[$position]['id']);
        $second = ReportItem::query()->findOrFail($list[$target]['id']);
        $firstOrder = $first->sort_order;
        $first->update(['sort_order' => $second->sort_order, 'updated_in_app_at' => now()]);
        $second->update(['sort_order' => $firstOrder, 'updated_in_app_at' => now()]);

        $this->report()->touch();
        $this->loadItems();
    }

    public function movePhoto(int $index, int $photoId, int $direction): void
    {
        $photos = collect($this->items[$index]['photos'] ?? []);
        $position = $photos->search(fn (array $photo): bool => $photo['id'] === $photoId);
        $target = $position === false ? -1 : $position + ($direction < 0 ? -1 : 1);

        if ($position === false || $target < 0 || $target >= $photos->count()) {
            return;
        }

        $first = ReportItemPhoto::query()->findOrFail($photos[$position]['id']);
        $second = ReportItemPhoto::query()->findOrFail($photos[$target]['id']);
        $firstOrder = $first->sort_order;
        $first->update(['sort_order' => $second->sort_order]);
        $second->update(['sort_order' => $firstOrder]);

        $this->report()->touch();
        $this->loadItems();
    }

    private function persistPhotoCaption(int $index, int $photoIndex): void
    {
        $photoData = $this->items[$index]['photos'][$photoIndex] ?? null;

        if ($photoData === null) {
            return;
        }

        ReportItemPhoto::query()->whereKey($photoData['id'])->update([
            'caption' => trim((string) $photoData['caption']) ?: null,
            'updated_at' => now(),
        ]);
    }

    public function syncDriveLinks(int $index): void
    {
        app(SyncItemDriveLinks::class)->handle(
            $this->itemAt($index),
            (string) ($this->items[$index]['drive_links'] ?? ''),
        );

        $this->report()->touch();
        $this->loadItems();
    }

    public function syncEvidenceUrl(int $index): void
    {
        $item = $this->itemAt($index);
        $link = EvidenceLink::make($this->items[$index]['evidence_url'] ?? null);

        $item->update([
            'evidence_url' => $link?->url,
            'drive_folder_id' => $link?->driveFolderId(),
            'updated_in_app_at' => now(),
        ]);
        $this->report()->touch();
        $this->loadItems();
    }

    public function finalize(): void
    {
        $blocking = collect($this->reviewIssues)->where('blocking', true);

        if ($blocking->isNotEmpty()) {
            Flux::toast(variant: 'danger', text: 'Complete los datos obligatorios antes de finalizar.');

            return;
        }

        $report = $this->report();
        $report->update(['status' => 'final', 'current_step' => 6, 'updated_in_app_at' => now()]);
        $this->status = 'final';

        Flux::toast(variant: 'success', text: 'Informe finalizado.');
    }

    public function reopen(): void
    {
        $report = $this->report();
        $report->update(['status' => 'draft', 'updated_in_app_at' => now()]);
        $this->status = 'draft';

        Flux::toast(variant: 'success', text: 'El informe volvió a borrador.');
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function artisticItems(): array
    {
        return array_values(array_filter($this->items, fn (array $item): bool => $item['type'] === 'artistic'));
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function technicalItems(): array
    {
        return array_values(array_filter($this->items, fn (array $item): bool => $item['type'] === 'technical'));
    }

    /** @return list<string> */
    #[Computed]
    public function unitOptions(): array
    {
        return self::UNITS;
    }

    /** @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, TextTemplate>> */
    #[Computed]
    public function templates()
    {
        return TextTemplate::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('key');
    }

    /** @return \Illuminate\Support\Collection<int, ItemCatalog> */
    #[Computed]
    public function catalogItems()
    {
        $type = $this->step === 4 ? 'technical' : 'artistic';

        return ItemCatalog::query()
            ->where('active', true)
            ->where('type', $type)
            ->orderBy('category_label')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, array{id:int, name:string}> */
    #[Computed]
    public function departments()
    {
        return \App\Models\Department::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($department): array => ['id' => $department->id, 'name' => $department->name]);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function reviewIssues(): array
    {
        $issues = [];

        if (trim($this->contract_number) === '') {
            $issues[] = ['level' => 'error', 'blocking' => true, 'message' => 'Falta el número de contrato.'];
        }

        if (! $this->municipality_id) {
            $issues[] = ['level' => 'error', 'blocking' => true, 'message' => 'Falta el municipio del contrato.'];
        }

        if (trim($this->event_name) === '') {
            $issues[] = ['level' => 'error', 'blocking' => true, 'message' => 'Falta el nombre del evento.'];
        }

        if (trim($this->conclusion) === '') {
            $issues[] = ['level' => 'error', 'blocking' => true, 'message' => 'Falta la conclusión del informe.'];
        }

        if (trim($this->signer_name) === '') {
            $issues[] = ['level' => 'error', 'blocking' => true, 'message' => 'Falta el firmante del informe.'];
        }

        $missingPhotos = collect($this->items)->filter(fn (array $item): bool => count($item['photos']) === 0)->count();
        $missingNarrative = collect($this->items)->filter(fn (array $item): bool => trim((string) $item['narrative']) === '')->count();

        if ($missingPhotos > 0) {
            $issues[] = ['level' => 'warning', 'blocking' => false, 'message' => "{$missingPhotos} ítems sin evidencia fotográfica."];
        }

        if ($missingNarrative > 0) {
            $issues[] = ['level' => 'warning', 'blocking' => false, 'message' => "{$missingNarrative} ítems sin actividad ejecutada."];
        }

        if ($this->period_start && $this->period_end && $this->event_start) {
            $start = CarbonImmutable::parse($this->period_start);
            $end = CarbonImmutable::parse($this->period_end);
            $eventStart = CarbonImmutable::parse($this->event_start);
            $eventEnd = $this->event_end ? CarbonImmutable::parse($this->event_end) : $eventStart;

            if (! $eventStart->betweenIncluded($start, $end) || ! $eventEnd->betweenIncluded($start, $end)) {
                $issues[] = ['level' => 'warning', 'blocking' => false, 'message' => 'Las fechas del evento están fuera del periodo.'];
            }
        }

        return $issues;
    }

    private function report(): Report
    {
        return Report::query()->findOrFail($this->reportId);
    }

    private function itemAt(int $index): ReportItem
    {
        $id = $this->items[$index]['id'] ?? null;

        return ReportItem::query()
            ->where('report_id', $this->reportId)
            ->with('photos')
            ->findOrFail($id);
    }

    private function loadItems(): void
    {
        $this->items = $this->report()->load('items.photos')->items->map($this->itemToArray(...))->values()->all();
        unset($this->artisticItems, $this->technicalItems, $this->reviewIssues);
    }

    private function loadMunicipalities(): void
    {
        $departmentId = $this->department_id ? (int) $this->department_id : null;

        $this->municipalityOptions = $departmentId
            ? Municipality::query()
                ->where('department_id', $departmentId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Municipality $municipality): array => ['id' => $municipality->id, 'name' => $municipality->name])
                ->all()
            : [];
    }

    public function uploadCover(PhotoOptimizer $optimizer): void
    {
        if (! $this->cover) {
            return;
        }

        $this->validate([
            'cover' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'cover.image' => 'La portada debe ser una imagen.',
            'cover.mimes' => 'Formato no permitido (use JPG, PNG o WEBP).',
            'cover.max' => 'La portada debe pesar menos de 5 MB.',
        ]);

        $report = $this->report();
        $path = "reports/{$report->id}/cover/".Str::uuid().'.jpg';

        try {
            $optimizer->optimize($this->cover->getRealPath(), Storage::disk('public')->path($path));
        } catch (\Throwable $exception) {
            report($exception);
            Flux::toast(variant: 'danger', text: 'No se pudo procesar la imagen de portada.');

            return;
        }

        if ($report->cover_path) {
            Storage::disk('public')->delete($report->cover_path);
        }

        $report->update(['cover_path' => $path, 'cover_url' => null, 'updated_in_app_at' => now()]);

        $this->reset('cover');
        $this->cover_url = '';
        $this->existingCover = $report->fresh()->coverImageUrl();

        Flux::toast(variant: 'success', text: 'Foto de portada actualizada.');
    }

    public function removeCover(): void
    {
        $report = $this->report();

        if ($report->cover_path) {
            Storage::disk('public')->delete($report->cover_path);
        }

        $report->update(['cover_path' => null, 'cover_url' => null, 'updated_in_app_at' => now()]);

        $this->reset('cover');
        $this->cover_url = '';
        $this->existingCover = null;

        Flux::toast(variant: 'success', text: 'Foto de portada eliminada.');
    }

    private function persistReport(): void
    {
        $report = $this->report();

        if ($this->contract_number !== $report->contract_number
            && Report::query()->where('contract_number', $this->contract_number)->whereKeyNot($report->id)->exists()) {
            $this->contract_number = (string) $report->contract_number;
            Flux::toast(variant: 'danger', text: 'Ese número de contrato ya existe.');

            return;
        }

        if (trim($this->cover_url) !== '' && filter_var(trim($this->cover_url), FILTER_VALIDATE_URL) === false) {
            $this->cover_url = (string) $report->cover_url;
            Flux::toast(variant: 'danger', text: 'El enlace de portada no es válido.');

            return;
        }

        $report->fill([
            'contract_number' => trim($this->contract_number),
            'municipality_id' => $this->municipality_id ? (int) $this->municipality_id : null,
            'report_date' => $this->report_date ?: null,
            'period_start' => $this->period_start ?: null,
            'period_end' => $this->period_end ?: null,
            'subject' => $this->subject ?: null,
            'contract_object' => $this->contract_object ?: null,
            'event_name' => $this->event_name ?: null,
            'event_start' => $this->event_start ?: null,
            'event_end' => $this->event_end ?: null,
            'cover_url' => $this->cover_url ?: null,
            'introduction' => $this->introduction ?: null,
            'event_description' => $this->event_description ?: null,
            'conclusion' => $this->conclusion ?: null,
            'signer_name' => $this->signer_name ?: null,
        ]);
        $report->updated_in_app_at = now();
        $report->save();
    }

    private function persistItem(int $index): void
    {
        $data = $this->items[$index] ?? null;

        if ($data === null || ! isset($data['id'])) {
            return;
        }

        $item = ReportItem::query()->where('report_id', $this->reportId)->find($data['id']);

        if (! $item) {
            return;
        }

        $item->fill([
            'category_label' => trim((string) $data['category_label']) ?: null,
            'artist_name' => trim((string) $data['artist_name']) ?: null,
            'specification' => trim((string) $data['specification']) ?: null,
            'narrative' => trim((string) $data['narrative']) ?: null,
            'quantity' => is_numeric($data['quantity'] ?? null) ? round((float) $data['quantity'], 3) : null,
            'unit' => trim((string) ($data['unit'] ?? '')) ?: null,
            'photo_layout' => in_array($data['photo_layout'] ?? null, ['single', 'pair', 'collage'], true)
                ? $data['photo_layout']
                : 'pair',
        ]);
        $item->updated_in_app_at = now();
        $item->save();
    }

    /** @return array<string, mixed> */
    private function itemToArray(ReportItem $item): array
    {
        return [
            'id' => $item->id,
            'ref' => $item->ref,
            'type' => $item->type,
            'category_label' => (string) $item->category_label,
            'artist_name' => (string) $item->artist_name,
            'specification' => (string) $item->specification,
            'narrative' => (string) $item->narrative,
            'quantity' => $item->quantity === null ? '' : (string) $item->quantity,
            'unit' => (string) $item->unit,
            'photo_layout' => (string) ($item->photo_layout ?: 'pair'),
            'drive_links' => $item->photos
                ->where('source', 'drive')
                ->pluck('drive_url')
                ->filter()
                ->implode("\n"),
            'evidence_url' => (string) ($item->evidence_url
                ?: ($item->drive_folder_id ? "https://drive.google.com/drive/folders/{$item->drive_folder_id}" : '')),
            'photos' => $item->photos
                ->map(fn ($photo): array => [
                    'id' => $photo->id,
                    'url' => $photo->thumbUrl(),
                    'caption' => (string) $photo->caption,
                ])
                ->values()
                ->all(),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 py-4">
    <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div>
            <a href="{{ route('reports.show', $reportId) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#7F5C12] hover:text-[#17150F]" wire:navigate>
                <flux:icon.chevron-left class="size-4" /> Volver a la revisión
            </a>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h1 class="font-display text-3xl font-bold tracking-tight text-[#17150F]">Editar informe</h1>
                <span @class([
                    'rounded-full px-3 py-1 text-xs font-semibold',
                    'bg-[#E7F3EC] text-[#2C7549]' => $status === 'final',
                    'bg-[#F6EEDB] text-[#7F5C12]' => $status !== 'final',
                ])>{{ $status === 'final' ? 'Finalizado' : 'Borrador' }}</span>
            </div>
            <p class="mt-2 text-[#5F584A]">Los cambios se guardan automáticamente al salir de cada campo.</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button :href="route('reports.preview', $reportId)" variant="ghost" icon="document-magnifying-glass">
                Vista previa
            </flux:button>
            @if ($status === 'final')
                <flux:button wire:click="reopen" variant="outline" icon="arrow-path">Reabrir borrador</flux:button>
            @else
                <flux:button wire:click="finalize" variant="primary" icon="check-badge">Finalizar informe</flux:button>
            @endif
        </div>
    </header>

    <nav class="flex overflow-x-auto rounded-xl border border-[#D3CBBB] bg-white sm:grid sm:grid-cols-3 sm:overflow-hidden lg:grid-cols-6">
        @foreach ([1 => 'Contrato', 2 => 'Evento', 3 => 'Artísticos', 4 => 'Técnico', 5 => 'Cierre', 6 => 'Revisión'] as $number => $label)
            <button
                type="button"
                wire:click="goToStep({{ $number }})"
                @class([
                    'flex shrink-0 items-center gap-2 whitespace-nowrap border-e border-[#E3DED3] px-4 py-3 text-left text-sm font-semibold transition last:border-e-0 sm:shrink',
                    'bg-[#17150F] text-white' => $step === $number,
                    'text-[#5F584A] hover:bg-[#F3F1EC]' => $step !== $number,
                ])
            >
                <span @class([
                    'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                    'bg-[#C9A043] text-[#17150F]' => $step === $number,
                    'bg-[#ECE8E0] text-[#5F584A]' => $step !== $number,
                ])>{{ $number }}</span>
                {{ $label }}
            </button>
        @endforeach
    </nav>

    <div class="flex items-center justify-between">
        <flux:button wire:click="goToStep({{ max(1, $step - 1) }})" variant="ghost" icon="chevron-left" :disabled="$step === 1">Anterior</flux:button>
        <flux:button wire:click="goToStep({{ min(6, $step + 1) }})" variant="ghost" icon-trailing="chevron-right" :disabled="$step === 6">Siguiente</flux:button>
    </div>

    @if ($step === 1)
        <section class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm">
            <h2 class="font-display text-lg font-bold">Contrato</h2>
            <p class="mt-1 text-sm text-[#5F584A]">Datos que identifican el informe.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <flux:input wire:model.live.debounce.800ms="contract_number" label="No. de contrato" type="text" required />
                <flux:select wire:model.live="department_id" label="Departamento">
                    <flux:select.option value="">Seleccione…</flux:select.option>
                    @foreach ($this->departments as $department)
                        <flux:select.option value="{{ $department['id'] }}">{{ $department['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="municipality_id" label="Municipio">
                    <flux:select.option value="">Seleccione…</flux:select.option>
                    @foreach ($municipalityOptions as $municipality)
                        <flux:select.option value="{{ $municipality['id'] }}">{{ $municipality['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.live.debounce.800ms="report_date" label="Fecha del informe" type="date" />
                <flux:input wire:model.live.debounce.800ms="period_start" label="Periodo desde" type="date" />
                <flux:input wire:model.live.debounce.800ms="period_end" label="Periodo hasta" type="date" />
                <div class="sm:col-span-2">
                    <flux:input wire:model.live.debounce.800ms="subject" label="Asunto" type="text" />
                </div>
                <div class="sm:col-span-2">
                    <flux:textarea wire:model.live.debounce.800ms="contract_object" label="Objeto del contrato" rows="5" />
                </div>
            </div>
        </section>
    @endif

    @if ($step === 2)
        <section class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm">
            <h2 class="font-display text-lg font-bold">Evento</h2>
            <p class="mt-1 text-sm text-[#5F584A]">Nombre, fechas y textos generales.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <flux:input wire:model.live.debounce.800ms="event_name" label="Nombre del evento" type="text" />
                </div>
                <flux:input wire:model.live.debounce.800ms="event_start" label="Fecha inicial" type="date" />
                <flux:input wire:model.live.debounce.800ms="event_end" label="Fecha final" type="date" />
                <div class="sm:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-[#17150F]">Introducción</span>
                        @if (($this->templates['introduction'] ?? collect())->isNotEmpty())
                            <select wire:key="plantilla-introduction-{{ $templateReset }}" wire:change="applyTemplate('introduction', $event.target.value)" class="rounded-lg border border-[#D3CBBB] bg-white px-3 py-1.5 text-sm font-semibold text-[#7F5C12]">
                                <option value="">Usar plantilla…</option>
                                @foreach ($this->templates['introduction'] as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <flux:textarea wire:model.live.debounce.800ms="introduction" rows="5" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-[#17150F]">Descripción del evento</span>
                        @if (($this->templates['description'] ?? collect())->isNotEmpty())
                            <select wire:key="plantilla-description-{{ $templateReset }}" wire:change="applyTemplate('event_description', $event.target.value)" class="rounded-lg border border-[#D3CBBB] bg-white px-3 py-1.5 text-sm font-semibold text-[#7F5C12]">
                                <option value="">Usar plantilla…</option>
                                @foreach ($this->templates['description'] as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <flux:textarea wire:model.live.debounce.800ms="event_description" rows="5" class="mt-2" />
                </div>
            </div>

            <div class="mt-6 rounded-lg border border-[#E3DED3] bg-[#FAF9F6] p-4">
                <p class="text-sm font-semibold text-[#17150F]">Foto de portada</p>
                <p class="mt-1 text-xs text-[#5F584A]">La foto del evento que va en la portada del informe. Súbala o pegue un enlace de Google Drive.</p>

                <div class="mt-3 grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                    <div class="flex h-32 items-center justify-center overflow-hidden rounded-lg border border-dashed border-[#D3CBBB] bg-white">
                        @if ($cover && $cover->isPreviewable())
                            <img src="{{ $cover->temporaryUrl() }}" alt="Portada nueva" class="h-full w-full object-cover" />
                        @elseif ($existingCover)
                            <img src="{{ $existingCover }}" alt="Portada actual" class="h-full w-full object-cover" referrerpolicy="no-referrer" />
                        @else
                            <span class="text-sm text-[#5F584A]">Sin portada</span>
                        @endif
                    </div>

                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[#D3CBBB] bg-white px-3 py-2 text-sm text-[#5F584A] transition hover:border-[#C9A043]">
                                <span class="rounded-md bg-[#17150F] px-3 py-1.5 text-xs font-semibold text-white">Elegir archivo</span>
                                <span class="truncate">{{ $cover ? $cover->getClientOriginalName() : 'Seleccionar imagen…' }}</span>
                                <input wire:model="cover" type="file" accept="image/*" class="sr-only" />
                            </label>
                            <flux:button size="sm" variant="primary" icon="arrow-up-tray" wire:click="uploadCover" wire:loading.attr="disabled" wire:target="uploadCover">Subir</flux:button>
                            @if ($existingCover || $cover)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeCover" wire:confirm="¿Quitar la foto de portada?">Quitar</flux:button>
                            @endif
                        </div>

                        <flux:input wire:model.live.debounce.800ms="cover_url" label="O enlace de Google Drive" type="text" placeholder="https://drive.google.com/file/d/..." />

                        @error('cover') <p class="text-xs font-semibold text-[#A8261D]">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($step === 3 || $step === 4)
        @php($type = $step === 3 ? 'artistic' : 'technical')
        @php($sectionItems = $step === 3 ? $this->artisticItems : $this->technicalItems)
        <section class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h2 class="font-display text-lg font-bold">{{ $step === 3 ? 'Programación artística' : 'Técnico y logística' }}</h2>
                    <p class="mt-1 text-sm text-[#5F584A]">{{ count($sectionItems) }} ítems. Edite y suba la evidencia fotográfica.</p>
                </div>
                <flux:button wire:click="addItem('{{ $type }}')" variant="primary" icon="plus">Agregar ítem</flux:button>
            </div>

            <details class="mt-5 rounded-lg border border-[#E3DED3] bg-[#FAF9F6] p-4">
                <summary class="cursor-pointer text-sm font-semibold text-[#17150F]">Importar del catálogo ({{ $this->catalogItems->count() }})</summary>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @forelse ($this->catalogItems as $catalogItem)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-[#E3DED3] bg-white p-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ $catalogItem->category_label }}</p>
                                @if ($catalogItem->specification)
                                    <p class="truncate text-xs text-[#5F584A]">{{ Str::limit($catalogItem->specification, 70) }}</p>
                                @endif
                            </div>
                            <flux:button size="sm" variant="primary" icon="plus" wire:click="importCatalogItem({{ $catalogItem->id }})">Agregar</flux:button>
                        </div>
                    @empty
                        <p class="text-sm text-[#5F584A]">No hay ítems activos en el catálogo para este tipo. Créelos en Biblioteca › Catálogo de ítems.</p>
                    @endforelse
                </div>
            </details>
        </section>

        @forelse ($sectionItems as $item)
            @php($index = collect($items)->search(fn ($candidate) => $candidate['id'] === $item['id']))
            <section wire:key="item-{{ $item['id'] }}" class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-[#EDE9E0] pb-4">
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-xs font-bold text-[#7F5C12]">{{ $item['ref'] }}</span>
                        <span class="text-sm font-semibold">{{ $item['artist_name'] ?: $item['category_label'] ?: 'Ítem sin nombre' }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <flux:button size="sm" variant="ghost" icon="chevron-up" wire:click="moveItem({{ $index }}, -1)" aria-label="Subir ítem" />
                        <flux:button size="sm" variant="ghost" icon="chevron-down" wire:click="moveItem({{ $index }}, 1)" aria-label="Bajar ítem" />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeItem({{ $index }})" wire:confirm="¿Eliminar el ítem {{ $item['ref'] }}?">Eliminar</flux:button>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model.live.debounce.800ms="items.{{ $index }}.artist_name" label="Artista o agrupación" type="text" />
                    <flux:input wire:model.live.debounce.800ms="items.{{ $index }}.category_label" label="Categoría" type="text" />
                    <div class="sm:col-span-2">
                        <flux:textarea wire:model.live.debounce.800ms="items.{{ $index }}.specification" label="Requerimiento del contrato" rows="3" />
                    </div>
                    <div class="sm:col-span-2">
                        <flux:textarea wire:model.live.debounce.800ms="items.{{ $index }}.narrative" label="Actividad ejecutada" rows="4" />
                    </div>
                    <flux:input wire:model.live.debounce.800ms="items.{{ $index }}.quantity" label="Cantidad" type="number" step="0.001" />
                    <flux:select wire:model.live="items.{{ $index }}.unit" label="Unidad">
                        <flux:select.option value="">Sin unidad</flux:select.option>
                        @foreach ($this->unitOptions as $unit)
                            <flux:select.option value="{{ $unit }}">{{ $unit }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="items.{{ $index }}.photo_layout" label="Distribución de fotos">
                        <flux:select.option value="single">1 por página</flux:select.option>
                        <flux:select.option value="pair">2 por página</flux:select.option>
                        <flux:select.option value="collage">Collage (hasta 4 por página)</flux:select.option>
                    </flux:select>
                </div>

                <div class="mt-6 border-t border-[#EDE9E0] pt-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-[#17150F]">Evidencia fotográfica <span class="font-normal text-[#5F584A]">({{ count($item['photos']) }})</span></p>
                            <p class="mt-1 text-xs text-[#5F584A]">JPG, PNG o WEBP · máx. {{ round((int) config('reports.photos.max_size_kb', 10240) / 1024) }} MB por foto · hasta {{ (int) config('reports.photos.max_per_item', 60) }} por ítem.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <input wire:model="uploads.{{ $index }}" type="file" accept="image/jpeg,image/png,image/webp" multiple class="block text-sm text-[#5F584A] file:me-3 file:rounded-md file:border-0 file:bg-[#17150F] file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white" />
                            <flux:button size="sm" variant="primary" icon="arrow-up-tray" wire:click="uploadPhotos({{ $index }})" wire:loading.attr="disabled" wire:target="uploadPhotos">Subir</flux:button>
                        </div>
                    </div>

                    @if (count($item['photos']) > 0)
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($item['photos'] as $photo)
                                <div wire:key="photo-{{ $photo['id'] }}" class="overflow-hidden rounded-lg border border-[#E3DED3] bg-white">
                                    <div class="relative">
                                        <img src="{{ $photo['url'] }}" alt="{{ $photo['caption'] }}" class="h-28 w-full object-cover" />
                                        <div class="absolute right-1.5 top-1.5 flex gap-1">
                                            <button type="button" wire:click="movePhoto({{ $index }}, {{ $photo['id'] }}, -1)" class="rounded-full bg-[#17150F]/80 p-1 text-white" aria-label="Mover antes">
                                                <flux:icon.chevron-up class="size-3.5" />
                                            </button>
                                            <button type="button" wire:click="movePhoto({{ $index }}, {{ $photo['id'] }}, 1)" class="rounded-full bg-[#17150F]/80 p-1 text-white" aria-label="Mover después">
                                                <flux:icon.chevron-down class="size-3.5" />
                                            </button>
                                            <button type="button" wire:click="removePhoto({{ $index }}, {{ $photo['id'] }})" wire:confirm="¿Eliminar esta foto?" class="rounded-full bg-[#17150F]/80 p-1 text-white" aria-label="Eliminar foto">
                                                <flux:icon.x-mark class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                    <input
                                        wire:model.live.debounce.800ms="items.{{ $index }}.photos.{{ $loop->index }}.caption"
                                        type="text"
                                        placeholder="Leyenda…"
                                        class="w-full border-0 border-t border-[#E3DED3] bg-white px-2 py-1.5 text-xs text-[#17150F] focus:outline-none focus:ring-0"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-3 rounded-lg border border-dashed border-[#D3CBBB] bg-[#FAF9F6] px-4 py-6 text-center text-sm text-[#5F584A]">Sin fotos todavía.</p>
                    @endif

                    <div class="mt-6 rounded-lg border border-[#E3DED3] bg-[#FAF9F6] p-4">
                        <p class="text-sm font-semibold text-[#17150F]">Evidencia por enlace</p>
                        <p class="mt-1 text-xs text-[#5F584A]">Pegue los enlaces de las fotos (uno por línea) y/o un enlace a la carpeta o galería. Para Drive, comparta la carpeta como “cualquiera con el enlace”; también sirve una imagen directa (JPG/PNG/WEBP) u otro proveedor.</p>
                        <div class="mt-3 grid gap-4 lg:grid-cols-2">
                            <flux:textarea wire:model.live.debounce.800ms="items.{{ $index }}.drive_links" label="Fotos de Drive (un enlace por línea)" rows="3" placeholder="https://drive.google.com/file/d/..." />
                            <flux:input wire:model.live.debounce.800ms="items.{{ $index }}.evidence_url" label="Carpeta, galería o imagen" type="text" placeholder="https://drive.google.com/drive/folders/… o https://…/foto.jpg" />
                        </div>
                        @php($evidence = \App\Services\Photos\EvidenceLink::make($item['evidence_url'] ?? null))
                        @if ($evidence?->type() === 'drive_folder')
                            <div class="mt-4 overflow-hidden rounded-lg border border-[#D3CBBB] bg-white">
                                <iframe src="{{ $evidence->embedUrl() }}" class="h-80 w-full" loading="lazy"></iframe>
                            </div>
                            <p class="mt-2 text-xs text-[#8A8274]">Si no se ve el contenido, la carpeta no está compartida como “cualquiera con el enlace”. <a href="{{ $evidence->openUrl() }}" target="_blank" rel="noopener" class="font-semibold text-[#7F5C12] underline">Abrir en Drive</a></p>
                        @elseif ($evidence?->imageUrl())
                            <img src="{{ $evidence->imageUrl() }}" alt="Vista previa de la evidencia" class="mt-4 max-h-80 w-full rounded-lg border border-[#D3CBBB] object-contain" referrerpolicy="no-referrer">
                        @elseif ($evidence)
                            <div class="mt-4 overflow-hidden rounded-lg border border-[#D3CBBB] bg-white">
                                <iframe src="{{ $evidence->embedUrl() }}" class="h-80 w-full" loading="lazy"></iframe>
                            </div>
                            <p class="mt-2 text-xs text-[#8A8274]">Si el proveedor bloquea la vista incrustada, use el enlace directo. <a href="{{ $evidence->openUrl() }}" target="_blank" rel="noopener" class="font-semibold text-[#7F5C12] underline">Abrir enlace</a></p>
                        @endif
                    </div>
                </div>
            </section>
        @empty
            <section class="rounded-xl border border-dashed border-[#D3CBBB] bg-white p-10 text-center text-sm text-[#5F584A]">
                No hay ítems en esta sección. Use “Agregar ítem”.
            </section>
        @endforelse
    @endif

    @if ($step === 5)
        <section class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm">
            <h2 class="font-display text-lg font-bold">Cierre</h2>
            <p class="mt-1 text-sm text-[#5F584A]">Conclusión y firmante del informe.</p>
            <div class="mt-5 grid gap-5">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-semibold text-[#17150F]">Conclusión</span>
                        @if (($this->templates['conclusion'] ?? collect())->isNotEmpty())
                            <select wire:key="plantilla-conclusion-{{ $templateReset }}" wire:change="applyTemplate('conclusion', $event.target.value)" class="rounded-lg border border-[#D3CBBB] bg-white px-3 py-1.5 text-sm font-semibold text-[#7F5C12]">
                                <option value="">Usar plantilla…</option>
                                @foreach ($this->templates['conclusion'] as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <flux:textarea wire:model.live.debounce.800ms="conclusion" rows="6" class="mt-2" />
                </div>
                <flux:input wire:model.live.debounce.800ms="signer_name" label="Firmante" type="text" />
            </div>
        </section>
    @endif

    @if ($step === 6)
        <section class="rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm">
            <h2 class="font-display text-lg font-bold">Revisión y PDF</h2>
            <p class="mt-1 text-sm text-[#5F584A]">Revise el checklist y genere el borrador.</p>

            <div class="mt-5 space-y-3">
                @forelse ($this->reviewIssues as $issue)
                    <div @class([
                        'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm',
                        'border-[#F1C4BE] bg-[#F9E3E0] text-[#A8261D]' => $issue['level'] === 'error',
                        'border-[#E8D6A8] bg-[#FBEEDA] text-[#8F520A]' => $issue['level'] === 'warning',
                    ])>
                        <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0" />
                        <span>{{ $issue['message'] }}{{ $issue['blocking'] ? ' (obligatorio)' : '' }}</span>
                    </div>
                @empty
                    <div class="flex items-center gap-3 rounded-lg border border-[#BBD8C5] bg-[#F1F8F3] px-4 py-3 text-sm font-semibold text-[#2C7549]">
                        <flux:icon.check-circle class="size-5" /> Todo listo para finalizar.
                    </div>
                @endforelse
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <flux:button :href="route('reports.preview', $reportId)" variant="primary" icon="document-magnifying-glass">Generar vista previa / PDF</flux:button>
                @if ($status !== 'final')
                    <flux:button wire:click="finalize" variant="outline" icon="check-badge">Finalizar informe</flux:button>
                @endif
            </div>
        </section>
    @endif

    <div class="flex items-center justify-between">
        <flux:button wire:click="goToStep({{ max(1, $step - 1) }})" variant="ghost" icon="chevron-left" :disabled="$step === 1">Anterior</flux:button>
        <flux:button wire:click="goToStep({{ min(6, $step + 1) }})" variant="ghost" icon-trailing="chevron-right" :disabled="$step === 6">Siguiente</flux:button>
    </div>
</div>
