<?php

namespace App\Services\Reports;

use App\Models\Report;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Spatie\Browsershot\Browsershot;

/**
 * Genera el PDF del informe con la plantilla visual de RYS.
 *
 * Navega a una URL firmada (en vez de pasar HTML) para que Chrome resuelva
 * correctamente los assets y las imágenes desde el servidor web, ya que la
 * aplicación se sirve bajo `public/index.php` y las rutas de assets dependen
 * del dominio de la petición.
 */
class ReportPdfGenerator
{
    public function generate(Report $report): string
    {
        $url = URL::temporarySignedRoute(
            'reports.pdf.render',
            now()->addMinutes(15),
            ['report' => $report->id],
        );

        $path = "reports/{$report->id}/informe-{$report->contract_number}.pdf";
        $absolute = Storage::disk('local')->path($path);

        if (! is_dir($directory = dirname($absolute))) {
            mkdir($directory, 0775, true);
        }

        Browsershot::url($url)
            ->setChromePath($this->chromePath())
            ->format('A4')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->noSandbox()
            ->timeout(180)
            ->savePdf($absolute);

        $report->update([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
            'updated_in_app_at' => now(),
        ]);

        return $path;
    }

    private function chromePath(): string
    {
        $configured = config('laravel-pdf.browsershot.chrome_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        foreach ([
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No se encontró Chrome/Chromium para generar el PDF.');
    }
}
