<?php

namespace App\Services\ReportActivity;

use App\Models\Report;
use App\Models\ReportActivityLog;
use App\Models\ReportImport;
use App\Models\ReportItem;
use App\Services\ReportImport\ReportImportPreviewer;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra en report_activity_logs qué cambió, cuándo, quién y desde dónde.
 *
 * - Origen "app": lo escriben los observers cuando se edita en la aplicación.
 * - Origen "excel": lo escribe el importador con los campos que trae la plantilla.
 */
class ReportActivityLogger
{
    public const IGNORED_FIELDS = [
        'id', 'user_id', 'report_id', 'updated_at', 'updated_in_app_at', 'imported_at',
        'created_at', 'deleted_at', 'pdf_path', 'pdf_generated_at', 'drive_synced_at',
    ];

    private const EXTRA_LABELS = [
        'ref' => 'Referencia',
        'contract_number' => 'No. de contrato',
        'subject' => 'Asunto',
        'status' => 'Estado',
        'current_step' => 'Paso actual',
        'municipality_id' => 'Municipio',
    ];

    /**
     * Campos modificados de un modelo, con su valor anterior y nuevo.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function capture(Model $model): array
    {
        $dirty = $model->getDirty();
        $dirty = $dirty !== [] ? $dirty : $model->getChanges();
        $changes = [];

        foreach ($dirty as $field => $after) {
            if (in_array($field, self::IGNORED_FIELDS, true)) {
                continue;
            }

            $changes[$field] = [$model->getOriginal($field), $after];
        }

        return $changes;
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     * @return int número de entradas escritas
     */
    public function log(
        Report $report,
        array $changes,
        string $source,
        ?ReportItem $item = null,
        ?ReportImport $import = null,
        int|string|null $userId = null,
    ): int {
        $rows = [];

        foreach ($changes as $field => [$before, $after]) {
            if ($this->value($before) === $this->value($after)) {
                continue;
            }

            $rows[] = $this->row($report, $item, $import, $userId, 'updated', $field, $before, $after, $source);
        }

        if ($rows === []) {
            return 0;
        }

        ReportActivityLog::query()->insert($rows);

        return count($rows);
    }

    public function logCreated(
        Report $report,
        ?ReportItem $item,
        string $source,
        ?ReportImport $import = null,
        int|string|null $userId = null,
    ): void {
        $name = $this->name($report, $item);

        ReportActivityLog::query()->insert([
            $this->row($report, $item, $import, $userId, 'created', null, null, $name, $source),
        ]);
    }

    public function logDeleted(
        Report $report,
        ?ReportItem $item,
        string $source,
        ?ReportImport $import = null,
        int|string|null $userId = null,
    ): void {
        $name = $this->name($report, $item);

        ReportActivityLog::query()->insert([
            $this->row($report, $item, $import, $userId, 'deleted', null, $name, null, $source, detachItem: true),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(
        Report $report,
        ?ReportItem $item,
        ?ReportImport $import,
        int|string|null $userId,
        string $action,
        ?string $field,
        mixed $before,
        mixed $after,
        string $source,
        bool $detachItem = false,
    ): array {
        return [
            'report_id' => $report->id,
            'report_item_id' => $detachItem ? null : $item?->id,
            'item_ref' => $item?->ref,
            'report_import_id' => $import?->id,
            'user_id' => $userId,
            'action' => $action,
            'field' => $field,
            'label' => $field ? $this->label($field, $item !== null) : null,
            'old_value' => $this->value($before),
            'new_value' => $this->value($after),
            'source' => $source,
            'created_at' => now(),
        ];
    }

    private function name(Report $report, ?ReportItem $item): string
    {
        if (! $item) {
            return "Informe {$report->contract_number}";
        }

        return $item->artist_name ?: $item->category_label ?: $item->ref;
    }

    private function label(string $field, bool $isItem): string
    {
        if (isset(self::EXTRA_LABELS[$field])) {
            return self::EXTRA_LABELS[$field];
        }

        $map = $isItem ? ReportImportPreviewer::ITEM_FIELDS : ReportImportPreviewer::REPORT_FIELDS;

        foreach ($map as [$name, $label]) {
            if ($name === $field) {
                return $label;
            }
        }

        return $field;
    }

    private function value(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: null;
        }

        return (string) $value;
    }
}
