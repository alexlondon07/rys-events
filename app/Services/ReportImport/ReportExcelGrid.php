<?php

namespace App\Services\ReportImport;

/**
 * Arma el visor del Excel de una carga: la rejilla real por hoja, el estado de
 * cada fila (nuevo, cambia, sin cambios, conflicto, error o aviso) y la
 * explicación de cada columna.
 *
 * No consulta la base de datos: recibe las hojas crudas del archivo y la vista
 * previa ya calculada por {@see ReportImportPreviewer}. Así la pantalla puede
 * mostrar el Excel "tal cual" y, al mismo tiempo, decir qué va a pasar con cada
 * fila.
 */
class ReportExcelGrid
{
    public const SHEET_ORDER = ['Informe', 'Items', 'Fotos', '_meta'];

    /** @var array<string, array{label:string, tone:string}> */
    private const STATUSES = [
        'create' => ['label' => 'Nuevo', 'tone' => 'success'],
        'update' => ['label' => 'Cambia', 'tone' => 'gold'],
        'unchanged' => ['label' => 'Sin cambios', 'tone' => 'muted'],
        'conflict' => ['label' => 'Conflicto', 'tone' => 'warning'],
        'warning' => ['label' => 'Aviso', 'tone' => 'warning'],
        'error' => ['label' => 'Error', 'tone' => 'danger'],
    ];

    /**
     * @param  array<string, array<int, list<mixed>>>  $sheets
     * @param  array<string, mixed>  $preview
     */
    public function __construct(
        private readonly array $sheets,
        private readonly array $preview = [],
    ) {}

    /** @return list<string> */
    public function sheetNames(): array
    {
        return array_values(array_filter(
            self::SHEET_ORDER,
            fn (string $sheet): bool => array_key_exists($sheet, $this->sheets),
        ));
    }

    public function hasSheet(string $sheet): bool
    {
        return array_key_exists($sheet, $this->sheets);
    }

    /**
     * @return list<array{code:string, label:string, tone:string}>
     */
    public function legend(): array
    {
        return array_map(
            fn (string $code): array => ['code' => $code] + self::STATUSES[$code],
            array_keys(self::STATUSES),
        );
    }

    /**
     * Filas de una hoja con su tipo y estado.
     *
     * `kind` es `keys` (fila 1, claves técnicas), `labels` (fila 2, títulos para
     * las personas), `help` (fila 3, ayuda) o `data` (los datos).
     *
     * @return list<array{number:int, kind:string, cells:list<string>, status:?array{code:string, label:string, tone:string}}>
     */
    public function rows(string $sheet): array
    {
        $rows = $this->sheets[$sheet] ?? [];
        $statuses = $this->statusesFor($sheet);
        $result = [];

        foreach ($rows as $number => $cells) {
            $status = $statuses[$number] ?? null;

            $result[] = [
                'number' => $number,
                'kind' => $this->kind($sheet, $number),
                'cells' => array_map($this->stringify(...), $cells),
                'status' => $status ? ['code' => $status] + self::STATUSES[$status] : null,
            ];
        }

        return $result;
    }

    /**
     * Explicación de cada columna (o de cada campo, en la hoja Informe).
     *
     * @return list<array{key:string, title:string, help:string, example:string, field:?string}>
     */
    public function guide(string $sheet): array
    {
        if ($sheet === '_meta') {
            $example = (string) ($this->sheets['_meta'][1][0] ?? '');

            return [[
                'key' => 'A1',
                'title' => 'Versión de plantilla',
                'help' => 'Identifica el formato del archivo. El sistema solo acepta la versión '.TemplateReader::VERSION.'.',
                'example' => $example,
                'field' => null,
            ]];
        }

        if ($sheet === 'Informe') {
            return $this->informeGuide();
        }

        return $this->tableGuide($sheet);
    }

    /** @return list<array{key:string, title:string, help:string, example:string, field:?string}> */
    private function informeGuide(): array
    {
        $fields = array_map(
            static fn (array $definition): string => $definition[0],
            ReportImportPreviewer::REPORT_FIELDS,
        );
        $result = [];

        foreach ($this->sheets['Informe'] ?? [] as $number => $cells) {
            if ($number < 2) {
                continue;
            }

            $key = trim((string) ($cells[0] ?? ''));

            if ($key === '') {
                continue;
            }

            $result[] = [
                'key' => $key,
                'title' => (string) ($cells[1] ?? $key),
                'help' => (string) ($cells[3] ?? ''),
                'example' => $this->stringify($cells[2] ?? ''),
                'field' => $fields[$key] ?? null,
            ];
        }

        return $result;
    }

    /** @return list<array{key:string, title:string, help:string, example:string, field:?string}> */
    private function tableGuide(string $sheet): array
    {
        $rows = $this->sheets[$sheet] ?? [];
        $keys = $rows[1] ?? [];
        $titles = $rows[2] ?? [];
        $help = $rows[3] ?? [];
        $firstData = [];

        foreach ($rows as $number => $cells) {
            if ($number >= 4 && ! $this->rowIsEmpty($cells)) {
                $firstData = $cells;

                break;
            }
        }

        $fields = $sheet === 'Items'
            ? array_map(static fn (array $definition): string => $definition[0], ReportImportPreviewer::ITEM_FIELDS)
            : [];

        $result = [];

        foreach ($keys as $index => $key) {
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            $result[] = [
                'key' => $key,
                'title' => (string) ($titles[$index] ?? $key),
                'help' => (string) ($help[$index] ?? ''),
                'example' => $this->stringify($firstData[$index] ?? ''),
                'field' => $fields[$key] ?? null,
            ];
        }

        return $result;
    }

    /** @return array<int, string> */
    private function statusesFor(string $sheet): array
    {
        $statuses = [];

        foreach ($this->preview['issues'] ?? [] as $issue) {
            if (($issue['sheet'] ?? null) !== $sheet || empty($issue['row'])) {
                continue;
            }

            $statuses[(int) $issue['row']] = ($issue['severity'] ?? '') === 'error' ? 'error' : 'warning';
        }

        if ($sheet === 'Items') {
            $this->itemStatuses($statuses);
        } elseif ($sheet === 'Fotos') {
            $this->photoStatuses($statuses);
        } elseif ($sheet === 'Informe') {
            $this->reportStatuses($statuses);
        }

        return array_filter($statuses, static fn (?string $status): bool => $status !== null);
    }

    /** @param array<int, string> $statuses */
    private function itemStatuses(array &$statuses): void
    {
        foreach ($this->preview['items'] ?? [] as $item) {
            $row = (int) ($item['row'] ?? 0);

            if ($row === 0 || isset($statuses[$row])) {
                continue;
            }

            $conflict = false;

            foreach ($item['changes'] ?? [] as $change) {
                if (! empty($change['conflict'])) {
                    $conflict = true;

                    break;
                }
            }

            $statuses[$row] = match ($item['action'] ?? null) {
                'create' => 'create',
                'update' => $conflict ? 'conflict' : 'update',
                'unchanged' => 'unchanged',
                default => 'unchanged',
            };
        }
    }

    /** @param array<int, string> $statuses */
    private function photoStatuses(array &$statuses): void
    {
        foreach ($this->preview['photos'] ?? [] as $photo) {
            $row = (int) ($photo['row'] ?? 0);

            if ($row !== 0 && ! isset($statuses[$row])) {
                $statuses[$row] = 'create';
            }
        }
    }

    /** @param array<int, string> $statuses */
    private function reportStatuses(array &$statuses): void
    {
        $byKey = [];

        foreach ($this->preview['report_changes'] ?? [] as $change) {
            $code = ! empty($change['conflict']) ? 'conflict' : 'update';

            foreach ($this->excelKeysFor((string) ($change['field'] ?? '')) as $key) {
                $byKey[$key] = $code;
            }
        }

        $isCreate = ($this->preview['report_action'] ?? null) === 'create';

        foreach ($this->sheets['Informe'] ?? [] as $number => $cells) {
            if ($number < 2) {
                continue;
            }

            $key = trim((string) ($cells[0] ?? ''));

            if ($key === '' || isset($statuses[$number])) {
                continue;
            }

            if (isset($byKey[$key])) {
                $statuses[$number] = $isCreate ? 'create' : $byKey[$key];
            }
        }
    }

    /**
     * Claves de la hoja Informe asociadas a un campo de la aplicación.
     *
     * @return list<string>
     */
    private function excelKeysFor(string $field): array
    {
        if ($field === 'municipality_id') {
            return ['departamento', 'municipio'];
        }

        foreach (ReportImportPreviewer::REPORT_FIELDS as $excelKey => [$mapped]) {
            if ($mapped === $field) {
                return [$excelKey];
            }
        }

        return [];
    }

    private function kind(string $sheet, int $number): string
    {
        if ($sheet === 'Informe' || $sheet === '_meta') {
            return $number === 1 && $sheet === 'Informe' ? 'labels' : 'data';
        }

        return match ($number) {
            1 => 'keys',
            2 => 'labels',
            3 => 'help',
            default => 'data',
        };
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        return (string) $value;
    }

    /** @param list<mixed> $cells */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && $cell !== '') {
                return false;
            }
        }

        return true;
    }
}
