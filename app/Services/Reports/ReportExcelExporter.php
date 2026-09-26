<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\ReportItem;
use App\Services\ReportImport\TemplateReader;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Exporta un informe a la plantilla oficial de Excel para poder completarlo
 * fuera del sistema y volver a subirlo (la carga es idempotente por contrato
 * y referencia).
 *
 * La estructura replica la plantilla v1 que lee {@see TemplateReader}:
 * fila 1 con claves técnicas, fila 2 con títulos y fila 3 con ayuda; los datos
 * empiezan en la fila 4 (los ítems y fotos).
 */
class ReportExcelExporter
{
    private const VERSION = 'rys-informe-plantilla:v1';

    public function export(Report $report): string
    {
        $report->load(['municipality.department', 'items.photos', 'imports.user']);

        $path = "reports/{$report->id}/informe-{$report->contract_number}.xlsx";
        $absolute = Storage::disk('local')->path($path);

        if (! is_dir($directory = dirname($absolute))) {
            mkdir($directory, 0775, true);
        }

        $writer = new Writer;
        $writer->openToFile($absolute);

        $this->writeReportSheet($writer, $report);
        $writer->addNewSheetAndMakeItCurrent();
        $this->writeItemsSheet($writer, $report);
        $writer->addNewSheetAndMakeItCurrent();
        $this->writePhotosSheet($writer, $report);
        $writer->addNewSheetAndMakeItCurrent();
        $this->writeHistorySheet($writer, $report);
        $writer->addNewSheetAndMakeItCurrent();
        $writer->getCurrentSheet()->setName('_meta');
        $writer->addRow($this->row([self::VERSION]));

        $writer->close();

        return $path;
    }

    private function writeReportSheet(Writer $writer, Report $report): void
    {
        $writer->getCurrentSheet()->setName('Informe');

        $writer->addRow($this->row(['clave', 'Campo', 'Valor', 'Ayuda']));

        $rows = [
            ['numero_contrato', 'No. de contrato', $report->contract_number],
            ['departamento', 'Departamento', $report->municipality?->department?->name],
            ['municipio', 'Municipio', $report->municipality?->name],
            ['fecha_informe', 'Fecha del informe', $report->report_date?->format('Y-m-d')],
            ['periodo_desde', 'Periodo desde', $report->period_start?->format('Y-m-d')],
            ['periodo_hasta', 'Periodo hasta', $report->period_end?->format('Y-m-d')],
            ['objeto_contrato', 'Objeto del contrato', $report->contract_object],
            ['nombre_evento', 'Nombre del evento', $report->event_name],
            ['fecha_inicio_evento', 'Fecha inicial del evento', $report->event_start?->format('Y-m-d')],
            ['fecha_fin_evento', 'Fecha final del evento', $report->event_end?->format('Y-m-d')],
            ['imagen_portada', 'Imagen de portada', $report->cover_url],
            ['introduccion', 'Introducción', $report->introduction],
            ['descripcion_evento', 'Descripción del evento', $report->event_description],
            ['conclusion', 'Conclusión', $report->conclusion],
            ['firmante', 'Firmante', $report->signer_name],
        ];

        foreach ($rows as [$key, $label, $value]) {
            $writer->addRow($this->row([$key, $label, $value]));
        }
    }

    private function writeItemsSheet(Writer $writer, Report $report): void
    {
        $writer->getCurrentSheet()->setName('Items');

        $writer->addRow($this->row([
            'ref', 'tipo', 'orden', 'categoria_pdf', 'artista', 'requerimiento_contrato',
            'actividad_ejecutada', 'agregar_textos_estandar', 'cantidad', 'unidad',
            'carpeta_drive', 'distribucion_fotos', 'notas',
        ]));
        $writer->addRow($this->row([
            'Referencia', 'Tipo', 'Orden', 'Categoría (columna 1 del PDF)', 'Artista o agrupación',
            'Lo que pedía el contrato', 'Actividad ejecutada', 'Agregar coordinación y hospitalidad',
            'Cantidad', 'Unidad', 'Carpeta de fotos en Google Drive', 'Distribución de fotos', 'Notas internas',
        ]));
        $writer->addRow($this->row([
            'Obligatorio y único. No lo cambie después de la primera carga: con él se actualiza el ítem.',
        ]));

        foreach ($report->items as $index => $item) {
            $writer->addRow($this->row([
                $item->ref,
                $item->type === 'artistic' ? 'Artístico' : 'Técnico',
                $index + 1,
                $item->category_label,
                $item->artist_name,
                $item->specification,
                $item->narrative,
                $item->add_standard_texts ? 'Sí' : 'No',
                $item->quantity === null ? null : (float) $item->quantity,
                $item->unit,
                $this->folderUrl($item),
                $this->layoutLabel($item),
                $item->internal_notes,
            ]));
        }
    }

    private function writePhotosSheet(Writer $writer, Report $report): void
    {
        $writer->getCurrentSheet()->setName('Fotos');

        $writer->addRow($this->row(['ref_item', 'url_drive', 'titulo', 'orden']));
        $writer->addRow($this->row(['Referencia del ítem', 'Enlace de la foto en Google Drive', 'Título de la foto en el PDF', 'Orden']));
        $writer->addRow($this->row([
            'Debe existir en la hoja Items.',
        ]));

        foreach ($report->items as $item) {
            foreach ($item->photos as $photo) {
                if ($photo->source !== 'drive' || ! $photo->drive_url) {
                    continue;
                }

                $writer->addRow($this->row([
                    $item->ref,
                    $photo->drive_url,
                    $photo->caption,
                    $photo->sort_order,
                ]));
            }
        }
    }

    private function folderUrl(ReportItem $item): ?string
    {
        return $item->evidence_url
            ?: ($item->drive_folder_id ? "https://drive.google.com/drive/folders/{$item->drive_folder_id}" : null);
    }

    /**
     * Hoja informativa con la versión de plantilla y el resumen de las últimas
     * cargas. No la lee el importador: es para que quede el rastro en el archivo.
     */
    private function writeHistorySheet(Writer $writer, Report $report): void
    {
        $writer->getCurrentSheet()->setName('Historial');

        $writer->addRow($this->row(['version', 'fecha', 'usuario', 'estado', 'resumen']));
        $writer->addRow($this->row(['Versión', 'Fecha', 'Usuario', 'Estado', 'Resumen']));

        foreach ($report->imports as $import) {
            $writer->addRow($this->row([
                $import->version ? 'v'.$import->version : 'Borrador',
                $import->created_at?->format('Y-m-d H:i'),
                $import->user?->name,
                match ($import->status) {
                    'applied' => 'Aplicada',
                    'failed' => 'Fallida',
                    default => 'En revisión',
                },
                $import->status === 'applied' ? $import->summaryLine() : '—',
            ]));
        }
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function row(array $values): Row
    {
        return Row::fromValues(array_values(array_map(static fn (mixed $value): string|int|float|bool => $value ?? '', $values)));
    }

    private function layoutLabel(ReportItem $item): ?string
    {
        return match ($item->photo_layout) {
            'single' => '1 por página',
            'collage' => 'Collage 2x2',
            'pair' => '2 por página',
            default => null,
        };
    }
}
