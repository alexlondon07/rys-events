<?php

namespace App\Actions\Reports;

use App\Models\Report;
use App\Models\ReportItem;

/**
 * Crea un ítem nuevo dentro de un informe.
 *
 * Es la única fuente de verdad para calcular la referencia (`ART-01`, `TEC-01`…)
 * y el orden. La usan tanto el asistente ("Agregar ítem") como la importación
 * desde el catálogo, evitando duplicar esa lógica.
 */
class AddReportItemToReport
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Report $report, string $type, array $attributes = []): ReportItem
    {
        return ReportItem::query()->create([
            'report_id' => $report->id,
            'ref' => $this->nextRef($report, $type),
            'type' => $type,
            'sort_order' => $this->nextSortOrder($report),
            ...$attributes,
        ]);
    }

    /**
     * Genera la siguiente referencia libre para el tipo indicado.
     */
    public function nextRef(Report $report, string $type): string
    {
        $prefix = $type === 'artistic' ? 'ART' : 'TEC';
        $sequence = ReportItem::query()
            ->where('report_id', $report->id)
            ->where('type', $type)
            ->count() + 1;

        do {
            $ref = $prefix.'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
            $sequence++;
        } while (ReportItem::query()->where('report_id', $report->id)->where('ref', $ref)->exists());

        return $ref;
    }

    private function nextSortOrder(Report $report): int
    {
        return (int) ReportItem::query()->where('report_id', $report->id)->max('sort_order') + 1;
    }
}
