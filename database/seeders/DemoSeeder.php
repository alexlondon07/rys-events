<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Report;
use App\Models\ReportImport;
use App\Services\ReportImport\ReportImportApplier;
use App\Services\ReportImport\ReportImportPreviewer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Prepara datos de demostración para presentar el sistema:
 * datos de la empresa, el informe de ejemplo (importado del Excel real)
 * y una portada. Ejecutar con:
 *
 *     php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->company();
        $report = $this->demoReport();

        if ($report) {
            $this->cover($report);
            $this->placeholderPhotos($report);
        }
    }

    private function company(): void
    {
        CompanySetting::current()->update([
            'name' => 'Grupo RYS S.A.S.',
            'nit' => '901.234.567-8',
            'legal_rep_name' => 'Alexander Andrés Londoño Espejo',
            'legal_rep_title' => 'Representante Legal',
            'contact_email' => 'contacto@grupo-rys.com',
            'contact_phone' => '+57 300 000 0000',
        ]);
    }

    private function demoReport(): ?Report
    {
        $source = base_path('plantillas/ejemplo_guadalupe_PS-762026.xlsx');

        if (! is_file($source)) {
            return null;
        }

        $stored = 'report-imports/demo/ejemplo_guadalupe_PS-762026.xlsx';
        Storage::disk('local')->put($stored, (string) file_get_contents($source));

        $preview = app(ReportImportPreviewer::class)->preview(Storage::disk('local')->path($stored));

        $import = ReportImport::query()->create([
            'user_id' => null,
            'original_name' => basename($source),
            'file_path' => $stored,
            'status' => 'previewing',
            'file_hash' => hash_file('sha256', $source) ?: null,
            'summary' => ['preview' => $preview],
            'errors' => $preview['issues'],
        ]);

        app(ReportImportApplier::class)->apply($import, []);

        return Report::query()->where('contract_number', 'PS-762026')->first();
    }

    private function cover(Report $report): void
    {
        $source = public_path('images/reports/guadalupe-cover-photo.png');

        if (! is_file($source)) {
            return;
        }

        $path = "reports/{$report->id}/cover/portada.png";
        Storage::disk('public')->put($path, (string) file_get_contents($source));

        $report->update(['cover_path' => $path]);
    }

    /**
     * Genera imágenes de marcador de posición para que se vean las páginas
     * de evidencia fotográfica en la demostración.
     */
    private function placeholderPhotos(Report $report): void
    {
        $items = $report->items()->where('type', 'artistic')->take(3)->get();

        foreach ($items as $item) {
            if ($item->photos()->exists()) {
                continue;
            }

            foreach ([1, 2] as $position) {
                $uuid = (string) Str::uuid();
                $directory = "reports/{$report->id}/items/{$item->id}";
                $path = "{$directory}/{$uuid}.jpg";
                $thumbPath = "{$directory}/thumbs/{$uuid}.jpg";

                Storage::disk('public')->put($path, $this->placeholderImage(1200, 800, $item->ref.' · evidencia '.$position));
                Storage::disk('public')->put($thumbPath, $this->placeholderImage(400, 267, $item->ref));

                $item->photos()->create([
                    'source' => 'upload',
                    'path' => $path,
                    'thumb_path' => $thumbPath,
                    'original_name' => "evidencia-{$item->ref}-{$position}.jpg",
                    'caption' => $item->artist_name ?: $item->category_label,
                    'layout' => $item->photo_layout ?? 'pair',
                    'sort_order' => $position,
                    'width' => 1200,
                    'height' => 800,
                ]);
            }
        }
    }

    private function placeholderImage(int $width, int $height, string $label): string
    {
        $width = max(1, $width);
        $height = max(1, $height);

        $image = imagecreatetruecolor($width, $height);
        $background = $this->allocate($image, 20, 19, 16);
        $gold = $this->allocate($image, 226, 194, 116);
        $white = $this->allocate($image, 255, 255, 255);

        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagerectangle($image, 24, 24, $width - 24, $height - 24, $gold);
        imagestring($image, 5, 48, (int) ($height / 2) - 30, 'GRUPO RYS', $gold);
        imagestring($image, 4, 48, (int) ($height / 2) + 4, mb_substr($label, 0, 48), $white);

        ob_start();
        imagejpeg($image, null, 82);
        $content = (string) ob_get_clean();
        imagedestroy($image);

        return $content;
    }

    private function allocate(\GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, $this->channel($red), $this->channel($green), $this->channel($blue));

        if ($color === false) {
            throw new \RuntimeException('No se pudo crear el color del marcador de posición.');
        }

        return $color;
    }

    /** @return int<0, 255> */
    private function channel(int $value): int
    {
        return (int) min(255, max(0, $value));
    }
}
