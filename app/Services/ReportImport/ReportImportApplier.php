<?php

namespace App\Services\ReportImport;

use App\Models\Report;
use App\Models\ReportImport;
use App\Models\ReportItem;
use App\Services\ReportActivity\ReportActivityLogger;
use App\Support\ActivityLogContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReportImportApplier
{
    /**
     * @param  array<string, string>  $resolutions
     * @return array<string, mixed>
     */
    public function apply(ReportImport $import, array $resolutions): array
    {
        if ($import->status !== 'previewing') {
            throw new RuntimeException('Esta carga ya fue aplicada o ya no está disponible.');
        }

        $preview = $import->summary['preview'] ?? null;

        if (! is_array($preview) || ! ($preview['can_apply'] ?? false)) {
            throw new RuntimeException('La vista previa contiene errores y no se puede aplicar.');
        }

        foreach ($preview['conflicts'] as $conflict) {
            if (! in_array($resolutions[$conflict['id']] ?? null, ['app', 'excel'], true)) {
                throw new RuntimeException('Debe elegir una versión para cada conflicto.');
            }
        }

        return DB::transaction(function () use ($import, $preview, $resolutions): array {
            $lockedImport = ReportImport::query()->lockForUpdate()->findOrFail($import->id);

            if ($lockedImport->status !== 'previewing') {
                throw new RuntimeException('Esta carga ya fue aplicada por otro proceso.');
            }

            return ActivityLogContext::withoutRecording(
                fn (): array => $this->applyInsideTransaction($lockedImport, $preview, $resolutions),
            );
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array<string, string>  $resolutions
     * @return array<string, mixed>
     */
    private function applyInsideTransaction(ReportImport $lockedImport, array $preview, array $resolutions): array
    {
        $logger = app(ReportActivityLogger::class);
        $userId = $lockedImport->user_id;

        $existingReport = Report::query()
            ->where('contract_number', $preview['contract_number'])
            ->lockForUpdate()
            ->first();
        $this->assertFresh($existingReport?->updated_at?->toIso8601String(), $preview['report_expected_updated_at'], 'El informe');

        $isNewReport = ! $existingReport;
        $report = $existingReport ?? new Report([
            'contract_number' => $preview['contract_number'],
            'status' => 'draft',
            'current_step' => 1,
        ]);

        $reportPayload = Arr::except($preview['report_payload'], ['_municipality_label']);
        $reportPayload = $this->withoutAppResolutions($reportPayload, 'report', $resolutions);
        $report->fill($reportPayload);
        $report->user_id ??= $lockedImport->user_id;
        $report->subject ??= "Informe de actividades No {$preview['contract_number']}";
        $report->imported_at = now();
        $reportChanges = $logger->capture($report);
        $report->save();

        if ($isNewReport) {
            $logger->logCreated($report, null, 'excel', $lockedImport, $userId);
        } else {
            $logger->log($report, $reportChanges, 'excel', null, $lockedImport, $userId);
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $itemsByRef = [];

        foreach ($preview['items'] as $itemPreview) {
            $item = ReportItem::query()
                ->where('report_id', $report->id)
                ->where('ref', $itemPreview['ref'])
                ->lockForUpdate()
                ->first();

            $this->assertFresh($item?->updated_at?->toIso8601String(), $itemPreview['expected_updated_at'], "El ítem {$itemPreview['ref']}");
            $isNew = ! $item;
            $item ??= new ReportItem(['report_id' => $report->id, 'ref' => $itemPreview['ref']]);
            $payload = $this->withoutAppResolutions($itemPreview['payload'], "item:{$itemPreview['ref']}", $resolutions);
            $item->fill($payload);
            $item->imported_at = now();
            $itemChanges = $logger->capture($item);
            $item->save();
            $itemsByRef[$item->ref] = $item;

            if ($isNew) {
                $logger->logCreated($report, $item, 'excel', $lockedImport, $userId);
                $created++;
            } elseif ($itemPreview['action'] === 'update') {
                $logger->log($report, $itemChanges, 'excel', $item, $lockedImport, $userId);
                $updated++;
            } else {
                $unchanged++;
            }
        }

        $photosCreated = 0;

        foreach ($preview['photos'] as $photo) {
            $item = $itemsByRef[$photo['ref']] ?? ReportItem::query()
                ->where('report_id', $report->id)
                ->where('ref', $photo['ref'])
                ->firstOrFail();

            $record = $item->photos()->firstOrCreate(
                ['drive_file_id' => $photo['drive_file_id']],
                [
                    'source' => 'drive',
                    'drive_url' => $photo['drive_url'],
                    'caption' => $photo['caption'],
                    'layout' => $item->photo_layout ?? 'pair',
                    'sort_order' => $photo['sort_order'],
                    'sync_status' => 'pending',
                ],
            );

            $photosCreated += $record->wasRecentlyCreated ? 1 : 0;
        }

        $version = (int) ReportImport::query()
            ->where('report_id', $report->id)
            ->where('status', 'applied')
            ->max('version') + 1;

        $result = [
            'report_id' => $report->id,
            'contract_number' => $report->contract_number,
            'version' => $version,
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'photos_pending' => $photosCreated,
            'applied_at' => now()->toIso8601String(),
        ];

        $lockedImport->update([
            'report_id' => $report->id,
            'status' => 'applied',
            'version' => $version,
            'photos_added' => $photosCreated,
            'summary' => array_merge($lockedImport->summary ?? [], ['result' => $result]),
            'applied_at' => now(),
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $resolutions
     * @return array<string, mixed>
     */
    private function withoutAppResolutions(array $payload, string $prefix, array $resolutions): array
    {
        foreach (array_keys($payload) as $field) {
            if (($resolutions["{$prefix}:{$field}"] ?? null) === 'app') {
                unset($payload[$field]);
            }
        }

        return $payload;
    }

    private function assertFresh(?string $current, ?string $expected, string $label): void
    {
        if ($current !== $expected) {
            throw new RuntimeException("{$label} cambió después de generar la vista previa. Vuelva a revisar el archivo.");
        }
    }
}
