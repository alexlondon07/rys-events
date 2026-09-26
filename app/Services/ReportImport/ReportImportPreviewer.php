<?php

namespace App\Services\ReportImport;

use App\Models\Department;
use App\Models\Report;
use App\Models\ReportItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class ReportImportPreviewer
{
    public const REPORT_FIELDS = [
        'fecha_informe' => ['report_date', 'Fecha del informe'],
        'periodo_desde' => ['period_start', 'Periodo desde'],
        'periodo_hasta' => ['period_end', 'Periodo hasta'],
        'objeto_contrato' => ['contract_object', 'Objeto del contrato'],
        'nombre_evento' => ['event_name', 'Nombre del evento'],
        'fecha_inicio_evento' => ['event_start', 'Fecha inicial del evento'],
        'fecha_fin_evento' => ['event_end', 'Fecha final del evento'],
        'imagen_portada' => ['cover_url', 'Imagen de portada'],
        'introduccion' => ['introduction', 'Introducción'],
        'descripcion_evento' => ['event_description', 'Descripción del evento'],
        'conclusion' => ['conclusion', 'Conclusión'],
        'firmante' => ['signer_name', 'Firmante'],
    ];

    public const ITEM_FIELDS = [
        'tipo' => ['type', 'Tipo'],
        'orden' => ['sort_order', 'Orden'],
        'categoria_pdf' => ['category_label', 'Categoría'],
        'artista' => ['artist_name', 'Artista o agrupación'],
        'requerimiento_contrato' => ['specification', 'Requerimiento del contrato'],
        'actividad_ejecutada' => ['narrative', 'Actividad ejecutada'],
        'agregar_textos_estandar' => ['add_standard_texts', 'Agregar textos estándar'],
        'cantidad' => ['quantity', 'Cantidad'],
        'unidad' => ['unit', 'Unidad'],
        'carpeta_drive' => ['drive_folder_id', 'Carpeta de Drive'],
        'distribucion_fotos' => ['photo_layout', 'Distribución de fotos'],
        'notas' => ['internal_notes', 'Notas internas'],
    ];

    public function __construct(
        private readonly TemplateReader $reader,
        private readonly ImportValueNormalizer $normalizer,
    ) {}

    /** @return array<string, mixed> */
    public function preview(string $path): array
    {
        $source = $this->reader->read($path);
        $issues = [];
        $contractNumber = trim((string) ($source['report']['numero_contrato'] ?? ''));

        if ($contractNumber === '') {
            $issues[] = $this->issue('error', 'Informe', null, 'El número de contrato es obligatorio.', true);
        }

        $report = $contractNumber !== ''
            ? Report::with(['municipality.department', 'items'])->where('contract_number', $contractNumber)->first()
            : null;

        if (! $report && $contractNumber !== ''
            && Report::withTrashed()->where('contract_number', $contractNumber)->whereNotNull('deleted_at')->exists()) {
            $issues[] = $this->issue('warning', 'Informe', null, 'Este contrato tiene un informe eliminado; al aplicar se reemplazará por el del Excel.');
        }

        $reportPayload = $this->reportPayload($source['report'], $report, $issues);
        $reportChanges = $this->changesForModel($report, $reportPayload, self::REPORT_FIELDS, 'report');

        if (array_key_exists('municipality_id', $reportPayload)) {
            $reportChanges = array_merge($reportChanges, $this->municipalityChange($report, $reportPayload));
        }

        $items = $this->previewItems($source['items'], $report, $issues);
        $knownRefs = array_values(array_unique(array_merge(
            array_map(static fn (array $item): string => (string) $item['ref'], $items),
            $report
                ? $report->items->pluck('ref')->map(static fn (mixed $ref): string => (string) $ref)->values()->all()
                : [],
        )));
        $photos = $this->previewPhotos($source['photos'], $knownRefs, $issues);
        $conflicts = collect($reportChanges)
            ->merge(collect($items)->pluck('changes')->flatten(1))
            ->where('conflict', true)
            ->values()
            ->all();

        $itemCounts = collect($items)->countBy('action');
        $errorCount = collect($issues)->where('severity', 'error')->count();
        $blockingErrorCount = collect($issues)->where('blocking', true)->count();

        return [
            'version' => $source['version'],
            'previewed_at' => now()->toIso8601String(),
            'report_id' => $report?->id,
            'report_expected_updated_at' => $report?->updated_at?->toIso8601String(),
            'contract_number' => $contractNumber,
            'report_action' => $report ? 'update' : 'create',
            'report_label' => $this->reportLabel($report, $source['report']),
            'report_payload' => $reportPayload,
            'report_changes' => $reportChanges,
            'items' => $items,
            'photos' => $photos,
            'conflicts' => $conflicts,
            'issues' => $issues,
            'counts' => [
                'new' => (int) ($itemCounts['create'] ?? 0),
                'changed' => (int) ($itemCounts['update'] ?? 0),
                'unchanged' => (int) ($itemCounts['unchanged'] ?? 0),
                'conflicts' => count($conflicts),
                'issues' => count($issues),
                'errors' => $errorCount,
                'photos' => count($photos),
            ],
            'can_apply' => $contractNumber !== '' && $blockingErrorCount === 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<array<string, mixed>>  $issues
     * @return array<string, mixed>
     */
    private function reportPayload(array $values, ?Report $report, array &$issues): array
    {
        $payload = [];

        foreach (self::REPORT_FIELDS as $excelKey => [$field]) {
            if (! $this->blank($values[$excelKey] ?? null)) {
                $payload[$field] = $values[$excelKey];
            }
        }

        $department = $values['departamento'] ?? null;
        $municipality = $values['municipio'] ?? null;

        if (! $this->blank($department) || ! $this->blank($municipality)) {
            if ($this->blank($department) || $this->blank($municipality)) {
                $issues[] = $this->issue('error', 'Informe', null, 'Departamento y municipio deben venir juntos.', true);
            } else {
                $departmentModel = Department::where('normalized_name', $this->normalizer->key((string) $department))->first();
                $municipalityModel = $departmentModel?->municipalities()
                    ->where('normalized_name', $this->normalizer->key((string) $municipality))
                    ->first();

                if (! $municipalityModel) {
                    $issues[] = $this->issue('error', 'Informe', null, "No se encontró {$municipality}, {$department} en el catálogo DANE.", true);
                } else {
                    $payload['municipality_id'] = $municipalityModel->id;
                    $payload['_municipality_label'] = "{$municipalityModel->name}, {$departmentModel->name}";
                }
            }
        } elseif (! $report) {
            $issues[] = $this->issue('error', 'Informe', null, 'Departamento y municipio son obligatorios para crear un informe.', true);
        }

        $this->validateDateOrder($payload, $issues);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $issues
     */
    private function validateDateOrder(array $payload, array &$issues): void
    {
        try {
            $periodStart = isset($payload['period_start']) ? CarbonImmutable::parse($payload['period_start']) : null;
            $periodEnd = isset($payload['period_end']) ? CarbonImmutable::parse($payload['period_end']) : null;
            $eventStart = isset($payload['event_start']) ? CarbonImmutable::parse($payload['event_start']) : null;
            $eventEnd = isset($payload['event_end']) ? CarbonImmutable::parse($payload['event_end']) : null;
        } catch (\Throwable) {
            $issues[] = $this->issue('error', 'Informe', null, 'Una de las fechas no tiene un formato válido.', true);

            return;
        }

        if ($periodStart && $periodEnd && $periodStart->gt($periodEnd)) {
            $issues[] = $this->issue('error', 'Informe', null, 'El periodo desde no puede ser posterior al periodo hasta.', true);
        }

        if ($eventStart && $eventEnd && $eventStart->gt($eventEnd)) {
            $issues[] = $this->issue('error', 'Informe', null, 'La fecha inicial del evento no puede ser posterior a la fecha final.', true);
        }

        if ($periodStart && $periodEnd && (($eventStart && ! $eventStart->betweenIncluded($periodStart, $periodEnd)) || ($eventEnd && ! $eventEnd->betweenIncluded($periodStart, $periodEnd)))) {
            $issues[] = $this->issue('warning', 'Informe', null, 'Las fechas del evento están fuera del periodo de seguimiento.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $issues
     * @return list<array<string, mixed>>
     */
    private function previewItems(array $rows, ?Report $report, array &$issues): array
    {
        $existing = $report?->items->keyBy('ref') ?? collect();
        $seen = [];
        $result = [];

        foreach ($rows as $row) {
            $rowNumber = (int) $row['_row'];
            $ref = trim((string) ($row['ref'] ?? ''));

            if ($ref === '') {
                $issues[] = $this->issue('error', 'Items', $rowNumber, 'La referencia es obligatoria.');

                continue;
            }

            if (! preg_match('/^[A-Za-z0-9._-]+$/', $ref)) {
                $issues[] = $this->issue('error', 'Items', $rowNumber, "La referencia {$ref} contiene caracteres no permitidos.");

                continue;
            }

            $ref = mb_strtoupper($ref);

            if (isset($seen[$ref])) {
                $issues[] = $this->issue('error', 'Items', $rowNumber, "La referencia {$ref} está repetida en el archivo.");

                continue;
            }

            $seen[$ref] = true;
            $item = $existing->get($ref);
            $payload = $this->itemPayload($row, $item, $issues);

            if (! $item && ! isset($payload['type'])) {
                $issues[] = $this->issue('error', 'Items', $rowNumber, "El tipo es obligatorio para crear {$ref}.");

                continue;
            }

            $changes = $this->changesForModel($item, $payload, self::ITEM_FIELDS, "item:{$ref}");
            $action = $item ? ($changes === [] ? 'unchanged' : 'update') : 'create';

            $result[] = [
                'ref' => $ref,
                'row' => $rowNumber,
                'name' => (string) ($payload['artist_name'] ?? $payload['category_label'] ?? ($item ? ($item->artist_name ?? $item->category_label) : null) ?? $ref),
                'action' => $action,
                'expected_updated_at' => $item?->updated_at?->toIso8601String(),
                'payload' => $payload,
                'changes' => $changes,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array<string, mixed>>  $issues
     * @return array<string, mixed>
     */
    private function itemPayload(array $row, ?ReportItem $item, array &$issues): array
    {
        $payload = [];
        $rowNumber = (int) $row['_row'];

        foreach (self::ITEM_FIELDS as $excelKey => [$field]) {
            $value = $row[$excelKey] ?? null;

            if ($this->blank($value)) {
                continue;
            }

            $normalized = match ($excelKey) {
                'tipo' => $this->normalizer->type($value),
                'orden' => filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]),
                'agregar_textos_estandar' => $this->normalizer->boolean($value),
                'cantidad' => is_numeric($value) ? round((float) $value, 3) : null,
                'distribucion_fotos' => $this->normalizer->layout($value),
                'carpeta_drive' => $this->normalizer->driveFolderId($value),
                default => $value,
            };

            if ($excelKey === 'unidad') {
                [$normalized, $changed] = $this->normalizer->unit($value);

                if ($changed) {
                    $issues[] = $this->issue('warning', 'Items', $rowNumber, "La unidad \"{$value}\" se normalizará como \"{$normalized}\".");
                }
            }

            if ($normalized === null || $normalized === false && $excelKey === 'orden') {
                $issues[] = $this->issue('error', 'Items', $rowNumber, "El valor de {$excelKey} no es válido.");

                continue;
            }

            $payload[$field] = $normalized;
        }

        if (isset($payload['drive_folder_id'])) {
            $payload['evidence_url'] = 'https://drive.google.com/drive/folders/'.$payload['drive_folder_id'];
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $knownRefs
     * @param  list<array<string, mixed>>  $issues
     * @return list<array<string, mixed>>
     */
    private function previewPhotos(array $rows, array $knownRefs, array &$issues): array
    {
        $result = [];
        $seen = [];

        foreach ($rows as $row) {
            $rowNumber = (int) $row['_row'];
            $ref = mb_strtoupper(trim((string) ($row['ref_item'] ?? '')));
            $url = trim((string) ($row['url_drive'] ?? ''));
            $fileId = $this->normalizer->driveFileId($url);

            if (! in_array($ref, $knownRefs, true)) {
                $issues[] = $this->issue('error', 'Fotos', $rowNumber, "La referencia {$ref} no existe en el informe ni en la hoja Items.");

                continue;
            }

            if (! $fileId) {
                $message = str_contains($url, '/folders/')
                    ? 'El enlace es de una carpeta, no de un archivo. La fila se omitirá.'
                    : 'El enlace de Google Drive no es válido.';
                $issues[] = $this->issue('error', 'Fotos', $rowNumber, $message);

                continue;
            }

            $dedupeKey = "{$ref}:{$fileId}";

            if (isset($seen[$dedupeKey])) {
                $issues[] = $this->issue('error', 'Fotos', $rowNumber, 'La misma foto está repetida para el ítem.');

                continue;
            }

            $seen[$dedupeKey] = true;
            $order = $row['orden'] ?? null;

            if (! $this->blank($order) && filter_var($order, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
                $issues[] = $this->issue('error', 'Fotos', $rowNumber, 'El orden de la foto debe ser un entero positivo.');

                continue;
            }

            $result[] = [
                'ref' => $ref,
                'row' => $rowNumber,
                'drive_file_id' => $fileId,
                'drive_url' => $url,
                'caption' => $this->blank($row['titulo'] ?? null) ? null : (string) $row['titulo'],
                'sort_order' => $this->blank($order) ? 0 : (int) $order,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array{0:string, 1:string}>  $mapping
     * @return list<array<string, mixed>>
     */
    private function changesForModel(Report|ReportItem|null $model, array $payload, array $mapping, string $prefix): array
    {
        $changes = [];
        $fieldLabels = collect($mapping)->mapWithKeys(fn ($definition) => [$definition[0] => $definition[1]]);

        foreach (Arr::except($payload, ['_municipality_label']) as $field => $after) {
            if ($field === 'municipality_id') {
                continue;
            }

            $before = $model?->{$field};

            if ($before instanceof \DateTimeInterface) {
                $before = $before->format('Y-m-d');
            }

            if ($this->same($before, $after)) {
                continue;
            }

            $conflict = $model && $model->updated_in_app_at && (! $model->imported_at || $model->updated_in_app_at->gt($model->imported_at));
            $changes[] = [
                'id' => "{$prefix}:{$field}",
                'field' => $field,
                'label' => $fieldLabels[$field] ?? $field,
                'before' => $this->display($before),
                'after' => $this->display($after),
                'conflict' => (bool) $conflict,
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function municipalityChange(?Report $report, array $payload): array
    {
        if ($report && (int) $report->municipality_id === (int) $payload['municipality_id']) {
            return [];
        }

        $conflict = $report && $report->updated_in_app_at && (! $report->imported_at || $report->updated_in_app_at->gt($report->imported_at));

        return [[
            'id' => 'report:municipality_id',
            'field' => 'municipality_id',
            'label' => 'Municipio',
            'before' => $report?->municipality ? "{$report->municipality->name}, {$report->municipality->department->name}" : '(vacío)',
            'after' => $payload['_municipality_label'],
            'conflict' => (bool) $conflict,
        ]];
    }

    /** @param array<string, mixed> $values */
    private function reportLabel(?Report $report, array $values): string
    {
        $municipality = $report && $report->municipality
            ? $report->municipality->name
            : ($values['municipio'] ?? 'Municipio pendiente');
        $department = $report && $report->municipality
            ? $report->municipality->department->name
            : ($values['departamento'] ?? 'Departamento pendiente');

        return "{$municipality}, {$department}";
    }

    /** @return array{severity:string, sheet:string, row:?int, message:string, blocking:bool} */
    private function issue(string $severity, string $sheet, ?int $row, string $message, bool $blocking = false): array
    {
        return compact('severity', 'sheet', 'row', 'message', 'blocking');
    }

    private function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function same(mixed $before, mixed $after): bool
    {
        if (is_bool($before) || is_bool($after)) {
            return (bool) $before === (bool) $after;
        }

        if (is_numeric($before) && is_numeric($after)) {
            return (float) $before === (float) $after;
        }

        return (string) ($before ?? '') === (string) ($after ?? '');
    }

    private function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(vacío)';
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        return (string) $value;
    }
}
