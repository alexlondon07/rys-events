<?php

namespace App\Services\ReportImport;

use DateTimeInterface;
use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Reader;

class TemplateReader
{
    public const VERSION = 'rys-informe-plantilla:v1';

    private const ITEM_HEADERS = [
        'ref', 'tipo', 'orden', 'categoria_pdf', 'artista', 'requerimiento_contrato',
        'actividad_ejecutada', 'agregar_textos_estandar', 'cantidad', 'unidad',
        'carpeta_drive', 'distribucion_fotos', 'notas',
    ];

    private const PHOTO_HEADERS = ['ref_item', 'url_drive', 'titulo', 'orden'];

    /**
     * @return array{version:string, report:array<string, mixed>, items:list<array<string, mixed>>, photos:list<array<string, mixed>>}
     */
    public function read(string $path): array
    {
        $sheets = $this->readSheets($path);

        foreach (['Informe', 'Items', '_meta'] as $requiredSheet) {
            if (! array_key_exists($requiredSheet, $sheets)) {
                throw new InvalidArgumentException("Falta la hoja obligatoria {$requiredSheet}.");
            }
        }

        $version = (string) ($sheets['_meta'][1][0] ?? '');

        if ($version !== self::VERSION) {
            throw new InvalidArgumentException('La versión de la plantilla no es compatible. Descargue la plantilla v1 desde el sistema.');
        }

        return [
            'version' => $version,
            'report' => $this->readReport($sheets['Informe']),
            'items' => $this->readTable($sheets['Items'], self::ITEM_HEADERS, 200, 'Items'),
            'photos' => isset($sheets['Fotos'])
                ? $this->readTable($sheets['Fotos'], self::PHOTO_HEADERS, 1000, 'Fotos')
                : [],
        ];
    }

    /**
     * Devuelve las hojas crudas del archivo, sin validar versión ni columnas.
     * Lo usa el visor del Excel para mostrar la rejilla real tal como la ve
     * la persona (incluidas la fila de claves y las filas de ayuda).
     *
     * @return array<string, array<int, list<mixed>>> nombre de hoja => [número de fila => celdas]
     */
    public function readSheets(string $path): array
    {
        $reader = new Reader;
        $opened = false;
        $sheets = [];

        try {
            $reader->open($path);
            $opened = true;

            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];

                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $rows[(int) $rowNumber] = array_map($this->cleanCell(...), $row->toArray());
                }

                $sheets[$sheet->getName()] = $rows;
            }
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('No se pudo leer el archivo Excel. Verifique que sea un .xlsx válido.', previous: $exception);
        } finally {
            if ($opened) {
                $reader->close();
            }
        }

        return $sheets;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function readReport(array $rows): array
    {
        $report = [];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber === 1) {
                continue;
            }

            $key = trim((string) ($row[0] ?? ''));

            if ($key !== '') {
                $report[$key] = $row[2] ?? null;
            }
        }

        return $report;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  list<string>  $expectedHeaders
     * @return list<array<string, mixed>>
     */
    private function readTable(array $rows, array $expectedHeaders, int $maximumRows, string $sheetName): array
    {
        $headers = array_map(static fn ($value): string => trim((string) $value), $rows[1] ?? []);

        foreach ($expectedHeaders as $header) {
            if (! in_array($header, $headers, true)) {
                throw new InvalidArgumentException("La hoja {$sheetName} no contiene la columna técnica {$header} en la fila 1.");
            }
        }

        $result = [];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber <= 3 || $this->rowIsEmpty($row)) {
                continue;
            }

            if (count($result) >= $maximumRows) {
                throw new InvalidArgumentException("La hoja {$sheetName} supera el máximo de {$maximumRows} registros.");
            }

            $record = ['_row' => $rowNumber];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $record[$header] = $row[$index] ?? null;
                }
            }

            $result[] = $record;
        }

        return $result;
    }

    private function cleanCell(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            return trim(str_replace(["\r\n", "\r"], "\n", $value));
        }

        return $value;
    }

    /** @param array<int, mixed> $row */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }
}
