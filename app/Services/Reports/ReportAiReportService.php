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
    public const PROMPT_VERSION = 'v1';

    /**
     * @return array{result: array<string, mixed>, input_tokens: int|null, output_tokens: int|null, cost_usd: float|null}
     */
    public function generate(Report $report): array
    {
        $apiKey = (string) config('reports.ai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('El agente IA no tiene configurada la clave de API.');
        }

        $response = $this->client($apiKey)->post('/chat/completions', [
            'model' => (string) config('reports.ai.model'),
            'max_completion_tokens' => (int) config('reports.ai.max_output_tokens'),
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
                        json_encode($this->sourceData($report), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
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
                'contract_object' => $this->limit($report->contract_object, 5000),
                'event_name' => $this->limit($report->event_name),
                'event_start' => $report->event_start?->toDateString(),
                'event_end' => $report->event_end?->toDateString(),
                'introduction' => $this->limit($report->introduction, 5000),
                'event_description' => $this->limit($report->event_description, 7000),
                'conclusion' => $this->limit($report->conclusion, 5000),
                'municipality' => $report->municipality?->name,
                'department' => $report->municipality?->department?->name,
            ],
            'items' => $report->items->map(fn ($item): array => [
                'ref' => $item->ref,
                'type' => $item->type,
                'category' => $this->limit($item->category_label),
                'specification' => $this->limit($item->specification, 4000),
                'artist_name' => $this->limit($item->artist_name),
                'narrative' => $this->limit($item->narrative, 5000),
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'photo_count' => $item->photos->count(),
                'photo_captions' => $item->photos->pluck('caption')->filter()->map(
                    fn ($caption): string => $this->limit($caption, 500),
                )->values()->all(),
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
Eres el agente técnico de informes del sistema RYS. Redacta un borrador profesional en español colombiano.

Reglas obligatorias:
- Usa únicamente los datos recibidos. No inventes fechas, cantidades, nombres, actividades, resultados ni evidencias.
- Si falta información, dilo en quality_issues con prioridad "alta" o "media".
- Conserva las referencias de los ítems exactamente como llegan.
- Corrige ortografía, claridad y tono técnico sin cambiar el significado.
- No declares que una actividad ocurrió solo porque existe una especificación o una fotografía.
- Las fotos solo prueban que existe evidencia registrada; no describas su contenido porque no se enviaron imágenes.
- Devuelve exclusivamente el JSON solicitado.
PROMPT;
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
            'executive_summary' => $this->limit((string) ($result['executive_summary'] ?? ''), 8000),
            'report' => [
                'introduction' => $this->limit((string) ($result['report']['introduction'] ?? ''), 10000),
                'event_description' => $this->limit((string) ($result['report']['event_description'] ?? ''), 14000),
                'conclusion' => $this->limit((string) ($result['report']['conclusion'] ?? ''), 10000),
            ],
            'quality_issues' => collect((array) ($result['quality_issues'] ?? []))->map(fn ($issue): array => [
                'code' => $this->limit((string) ($issue['code'] ?? 'REVISION'), 80),
                'priority' => in_array($issue['priority'] ?? null, ['alta', 'media', 'baja'], true) ? $issue['priority'] : 'media',
                'message' => $this->limit((string) ($issue['message'] ?? ''), 1000),
                'source_ref' => $this->limit((string) ($issue['source_ref'] ?? 'informe'), 80),
            ])->values()->all(),
            'items' => collect((array) ($result['items'] ?? []))->map(fn ($item): array => [
                'ref' => $this->limit((string) ($item['ref'] ?? ''), 80),
                'revised_narrative' => $this->limit((string) ($item['revised_narrative'] ?? ''), 10000),
                'finding' => $this->limit((string) ($item['finding'] ?? ''), 2000),
                'recommendation' => $this->limit((string) ($item['recommendation'] ?? ''), 2000),
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
