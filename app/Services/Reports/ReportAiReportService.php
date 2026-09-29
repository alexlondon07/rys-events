<?php

namespace App\Services\Reports;

use App\Models\Report;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Integra el agente de informes con la API de OpenAI.
 *
 * El servicio solo produce un borrador estructurado. La aplicación decide
 * cuándo guardar cambios, después de la aprobación del usuario.
 */
final class ReportAiReportService
{
    public const PROMPT_VERSION = 'v2';

    /**
     * @return array{result: array<string, mixed>, input_tokens: int|null, output_tokens: int|null, cost_usd: float|null}
     */
    public function generate(Report $report): array
    {
        $apiKey = (string) config('reports.ai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('El agente IA no tiene configurada la clave de API.');
        }

        $sourceData = $this->sourceData($report);
        $itemCount = count($report->items);

        $response = $this->client($apiKey)->post('/chat/completions', [
            'model' => (string) config('reports.ai.model'),
            'max_completion_tokens' => $this->outputTokenBudget($itemCount),
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'rys_report_draft',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => "Construye el borrador usando exclusivamente estos datos:\n\n".
                        json_encode($sourceData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('El servicio IA no respondió correctamente. Intente nuevamente.');
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('El servicio IA devolvió un resultado vacío.');
        }

        try {
            $result = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('El servicio IA devolvió un formato no válido.', previous: $exception);
        }

        $usage = $response->json('usage', []);
        $inputTokens = isset($usage['prompt_tokens'])
            ? (int) $usage['prompt_tokens']
            : (isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null);
        $outputTokens = isset($usage['completion_tokens'])
            ? (int) $usage['completion_tokens']
            : (isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null);

        return [
            'result' => $this->normalizeResult($result),
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $this->calculateCost($inputTokens, $outputTokens),
        ];
    }

    public function sourceHash(Report $report): string
    {
        return hash('sha256', json_encode($this->sourceData($report), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function sourceData(Report $report): array
    {
        $report->loadMissing(['municipality.department', 'items.photos']);

        return [
            'report' => [
                'contract_number' => $report->contract_number,
                'report_date' => $report->report_date?->toDateString(),
                'period_start' => $report->period_start?->toDateString(),
                'period_end' => $report->period_end?->toDateString(),
                'subject' => $this->limit($report->subject),
                'contract_object' => $this->limit($report->contract_object, 2400),
                'event_name' => $this->limit($report->event_name),
                'event_start' => $report->event_start?->toDateString(),
                'event_end' => $report->event_end?->toDateString(),
                'introduction' => $this->limit($report->introduction, 1800),
                'event_description' => $this->limit($report->event_description, 3500),
                'conclusion' => $this->limit($report->conclusion, 1800),
                'municipality' => $report->municipality?->name,
                'department' => $report->municipality?->department?->name,
            ],
            'items' => $report->items->map(fn ($item): array => [
                'ref' => $item->ref,
                'type' => $item->type,
                'category' => $this->limit($item->category_label),
                'specification' => $this->limit($item->specification, 1500),
                'artist_name' => $this->limit($item->artist_name),
                'narrative' => $this->limit($item->narrative, 1800),
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'photo_count' => $item->photos->count(),
                'evidence_registered' => filled($item->evidence_url) || filled($item->drive_folder_id),
            ])->values()->all(),
        ];
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('reports.ai.base_url'), '/'))
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('reports.ai.timeout', 120));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Eres el revisor y redactor técnico de informes de Grupo RYS. Escribe en español colombiano formal, institucional y directo.

Objetivo:
Mejorar los textos del informe y señalar únicamente pendientes que afecten su claridad o consistencia.

Reglas de Negocio:
1. Revisa únicamente con los datos recibidos. Prohibido inventar o inferir hechos, nombres, fechas, cantidades, resultados o actividades no presentes en la entrada.
2. Corrige ortografía y redacción sin alterar el sentido original. Conserva las referencias numéricas/alfanuméricas de los ítems exactamente como se entregan.
3. No interpretes una especificación contractual como prueba de ejecución. La cantidad de fotos indica registro de evidencia; no interpretes el contenido visual de imágenes si no fueron adjuntadas.
4. Incluye en `quality_issues` únicamente omisiones o inconsistencias concretas y accionables del texto original. No agregues recomendaciones genéricas ni reportes faltantes que no apliquen al contexto.

Límites de Longitud:
- Resumen general: máximo 60 palabras.
- Introducción y Conclusión: máximo 80 palabras cada una.
- Descripción: máximo 140 palabras.
- Narrativa revisada por ítem: máximo 60 palabras.
- Hallazgo y Recomendación por ítem: una sola frase breve por cada uno. Si los datos no permiten generar un hallazgo o recomendación útil, indícalo explícitamente y asigna "no_aplica" a la prioridad.

Instrucciones de Salida:
- Devuelve la totalidad de los ítems recibidos.
- Responde EXCLUSIVAMENTE con el objeto JSON válido. No incluyas explicaciones, ni textos adicionales, ni marcadores Markdown de bloque de código.
PROMPT;
    }

    private function outputTokenBudget(int $itemCount): int
    {
        $configuredLimit = max(800, (int) config('reports.ai.max_output_tokens', 3500));
        $budgetForReport = 1200 + (max(0, $itemCount) * 180);

        return min($configuredLimit, $budgetForReport);
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['executive_summary', 'report', 'quality_issues', 'items'],
            'properties' => [
                'executive_summary' => ['type' => 'string'],
                'report' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['introduction', 'event_description', 'conclusion'],
                    'properties' => [
                        'introduction' => ['type' => 'string'],
                        'event_description' => ['type' => 'string'],
                        'conclusion' => ['type' => 'string'],
                    ],
                ],
                'quality_issues' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['code', 'priority', 'message', 'source_ref'],
                        'properties' => [
                            'code' => ['type' => 'string'],
                            'priority' => ['type' => 'string', 'enum' => ['alta', 'media', 'baja']],
                            'message' => ['type' => 'string'],
                            'source_ref' => ['type' => 'string'],
                        ],
                    ],
                ],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['ref', 'revised_narrative', 'finding', 'recommendation', 'priority'],
                        'properties' => [
                            'ref' => ['type' => 'string'],
                            'revised_narrative' => ['type' => 'string'],
                            'finding' => ['type' => 'string'],
                            'recommendation' => ['type' => 'string'],
                            'priority' => ['type' => 'string', 'enum' => ['alta', 'media', 'baja', 'no_aplica']],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function normalizeResult(array $result): array
    {
        return [
            'executive_summary' => $this->limit((string) ($result['executive_summary'] ?? ''), 700),
            'report' => [
                'introduction' => $this->limit((string) ($result['report']['introduction'] ?? ''), 1200),
                'event_description' => $this->limit((string) ($result['report']['event_description'] ?? ''), 2200),
                'conclusion' => $this->limit((string) ($result['report']['conclusion'] ?? ''), 1200),
            ],
            'quality_issues' => collect((array) ($result['quality_issues'] ?? []))->map(fn ($issue): array => [
                'code' => $this->limit((string) ($issue['code'] ?? 'REVISION'), 80),
                'priority' => in_array($issue['priority'] ?? null, ['alta', 'media', 'baja'], true) ? $issue['priority'] : 'media',
                'message' => $this->limit((string) ($issue['message'] ?? ''), 300),
                'source_ref' => $this->limit((string) ($issue['source_ref'] ?? 'informe'), 80),
            ])->values()->all(),
            'items' => collect((array) ($result['items'] ?? []))->map(fn ($item): array => [
                'ref' => $this->limit((string) ($item['ref'] ?? ''), 80),
                'revised_narrative' => $this->limit((string) ($item['revised_narrative'] ?? ''), 1000),
                'finding' => $this->limit((string) ($item['finding'] ?? ''), 300),
                'recommendation' => $this->limit((string) ($item['recommendation'] ?? ''), 300),
                'priority' => in_array($item['priority'] ?? null, ['alta', 'media', 'baja', 'no_aplica'], true) ? $item['priority'] : 'media',
            ])->filter(fn (array $item): bool => $item['ref'] !== '')->values()->all(),
        ];
    }

    private function calculateCost(?int $inputTokens, ?int $outputTokens): ?float
    {
        if ($inputTokens === null && $outputTokens === null) {
            return null;
        }

        return round(
            (($inputTokens ?? 0) / 1_000_000 * (float) config('reports.ai.input_cost_usd', 0.75))
            + (($outputTokens ?? 0) / 1_000_000 * (float) config('reports.ai.output_cost_usd', 4.50)),
            6,
        );
    }

    private function limit(?string $value, int $length = 2000): ?string
    {
        return $value === null ? null : Str::limit(trim($value), $length, '…');
    }
}
