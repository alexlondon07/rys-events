<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Municipality;
use App\Services\ReportImport\ImportValueNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SyncDivipola extends Command
{
    protected $signature = 'rys:sync-divipola';

    protected $description = 'Sincroniza departamentos y municipios desde el servicio oficial DIVIPOLA 2025 del DANE';

    public function handle(ImportValueNormalizer $normalizer): int
    {
        $url = 'https://geoportal.dane.gov.co/mparcgis/rest/services/Hosted/Serv_Mpio_MGN_2025/FeatureServer/317/query';
        $response = Http::timeout(60)->retry(3, 500)->get($url, [
            'where' => '1=1',
            'outFields' => 'dpto_ccdgo,dpto_cnmbre,mpio_cdpmp,mpio_cnmbre',
            'returnGeometry' => 'false',
            'orderByFields' => 'mpio_cdpmp',
            'f' => 'json',
        ])->throw()->json();

        $features = $response['features'] ?? null;

        if (! is_array($features) || count($features) < 1100) {
            throw new RuntimeException('El servicio DANE no devolvió el catálogo completo de municipios.');
        }

        DB::transaction(function () use ($features, $normalizer): void {
            foreach ($features as $feature) {
                $attributes = $feature['attributes'];
                $department = Department::updateOrCreate(
                    ['dane_code' => str_pad((string) $attributes['dpto_ccdgo'], 2, '0', STR_PAD_LEFT)],
                    [
                        'name' => $this->title((string) $attributes['dpto_cnmbre']),
                        'normalized_name' => $normalizer->key((string) $attributes['dpto_cnmbre']),
                    ],
                );

                Municipality::updateOrCreate(
                    ['dane_code' => str_pad((string) $attributes['mpio_cdpmp'], 5, '0', STR_PAD_LEFT)],
                    [
                        'department_id' => $department->id,
                        'name' => $this->title((string) $attributes['mpio_cnmbre']),
                        'normalized_name' => $normalizer->key((string) $attributes['mpio_cnmbre']),
                    ],
                );
            }
        });

        $this->info(sprintf(
            'DIVIPOLA sincronizada: %d departamentos y %d municipios.',
            Department::count(),
            Municipality::count(),
        ));

        return self::SUCCESS;
    }

    private function title(string $value): string
    {
        return mb_convert_case(mb_strtolower(trim($value)), MB_CASE_TITLE, 'UTF-8');
    }
}
