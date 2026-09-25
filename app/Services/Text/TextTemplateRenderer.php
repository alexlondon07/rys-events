<?php

namespace App\Services\Text;

use App\Models\Report;
use App\Models\ReportItem;
use App\Models\TextTemplate;

class TextTemplateRenderer
{
    public const VARIABLES = [
        'municipio', 'departamento', 'evento', 'fecha_inicio',
        'fecha_fin', 'dias', 'artista', 'contrato',
    ];

    /**
     * @param  array<string, string|int|null>  $values
     */
    public function render(string $body, array $values): string
    {
        $replacements = [];

        foreach (self::VARIABLES as $variable) {
            $replacements['{'.$variable.'}'] = (string) ($values[$variable] ?? '');
        }

        return strtr($body, $replacements);
    }

    /** @return array<string, string> */
    public function variablesFor(Report $report, ?ReportItem $item = null): array
    {
        $days = null;

        if ($report->event_start && $report->event_end) {
            $days = (string) ($report->event_start->diffInDays($report->event_end) + 1);
        }

        return [
            'municipio' => (string) optional($report->municipality)->name,
            'departamento' => (string) optional(optional($report->municipality)->department)->name,
            'evento' => (string) ($report->event_name ?? ''),
            'fecha_inicio' => $report->event_start?->format('d/m/Y') ?? '',
            'fecha_fin' => $report->event_end?->format('d/m/Y') ?? '',
            'dias' => $days ?? '',
            'artista' => (string) (optional($item)->artist_name ?? optional($item)->category_label ?? ''),
            'contrato' => (string) ($report->contract_number ?? ''),
        ];
    }

    /**
     * Párrafos estándar de coordinación y hospitalidad, ya con variables
     * reemplazadas. Se usan cuando el Excel marca "agregar textos estándar".
     */
    public function standardTexts(Report $report, ?ReportItem $item = null): string
    {
        $variables = $this->variablesFor($report, $item);

        return TextTemplate::query()
            ->whereIn('key', ['coordination', 'hospitality'])
            ->where('active', true)
            ->get()
            ->sortBy(fn (TextTemplate $template): int => $template->key === 'coordination' ? 0 : 1)
            ->map(fn (TextTemplate $template): string => $this->render($template->body_with_variables, $variables))
            ->implode("\n\n");
    }
}
