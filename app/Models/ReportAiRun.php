<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ejecución del agente IA de un informe.
 *
 * El resultado se conserva como borrador auditable hasta que un usuario lo
 * aprueba. Nunca se guarda la API key ni el prompt completo en esta tabla.
 *
 * @property int $id
 * @property int $report_id
 * @property int|null $user_id
 * @property int|null $approved_by
 * @property string $status
 * @property string $model
 * @property string $prompt_version
 * @property string $source_hash
 * @property array<string, mixed>|null $result
 * @property string|null $error
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $cost_usd
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $approved_at
 */
#[Fillable([
    'report_id', 'user_id', 'approved_by', 'status', 'model', 'prompt_version',
    'source_hash', 'result', 'error', 'input_tokens', 'output_tokens',
    'cost_usd', 'started_at', 'completed_at', 'approved_at',
])]
class ReportAiRun extends Model
{
    /** @var list<string> */
    public const STATUSES = ['queued', 'processing', 'ready', 'approved', 'failed'];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'cost_usd' => 'decimal:6',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
