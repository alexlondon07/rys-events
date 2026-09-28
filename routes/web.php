<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('informes/publico/{token}', [ReportController::class, 'publicShow'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('reports.public.show');

Route::middleware(['auth'])->group(function () {
    Route::livewire('dashboard', 'pages::reports.index')->name('dashboard');

    Route::livewire('informes/importar', 'pages::reports.import')
        ->name('reports.import');

    Route::livewire('informes/visor-excel', 'pages::reports.excel-viewer')
        ->name('reports.excel.viewer');

    Route::get('informes/plantilla', function () {
        return response()->download(
            base_path('plantillas/plantilla_informe_rys.xlsx'),
            'plantilla_informe_rys.xlsx',
        );
    })->name('reports.template');

    Route::livewire('informes/{report}/editar', 'pages::reports.wizard')
        ->name('reports.edit');

    Route::livewire('biblioteca/plantillas', 'pages::templates.index')
        ->name('templates.index');

    Route::livewire('biblioteca/catalogo', 'pages::catalog.index')
        ->name('catalog.index');

    Route::livewire('manual', 'pages::manual')
        ->name('manual');

    Route::get('informes/{report}', [ReportController::class, 'show'])
        ->name('reports.show');

    Route::get('informes/{report}/vista-previa', [ReportController::class, 'preview'])
        ->name('reports.preview');

    Route::get('informes/{report}/cargas/{import}/descargar', [ReportController::class, 'downloadImport'])
        ->name('reports.imports.download');

    Route::post('informes/{report}/pdf', [ReportController::class, 'generatePdf'])
        ->middleware('throttle:6,1')
        ->name('reports.pdf.generate');

    Route::post('informes/{report}/ia/borrador', [ReportController::class, 'generateAiDraft'])
        ->middleware('throttle:6,1')
        ->name('reports.ai.generate');

    Route::get('informes/{report}/ia/estado', [ReportController::class, 'aiStatus'])
        ->name('reports.ai.status');

    Route::post('informes/{report}/ia/{run}/aprobar', [ReportController::class, 'approveAiDraft'])
        ->middleware('throttle:12,1')
        ->name('reports.ai.approve');

    Route::get('informes/{report}/pdf/estado', [ReportController::class, 'pdfStatus'])
        ->name('reports.pdf.status');

    Route::post('informes/{report}/drive', [ReportController::class, 'syncDrive'])
        ->middleware('throttle:6,1')
        ->name('reports.drive.sync');

    Route::post('informes/{report}/compartir', [ReportController::class, 'createPublicShare'])
        ->name('reports.public.create');

    Route::delete('informes/{report}/compartir', [ReportController::class, 'revokePublicShare'])
        ->name('reports.public.revoke');

    Route::get('informes/{report}/pdf', [ReportController::class, 'downloadPdf'])
        ->name('reports.pdf.download');

    Route::get('informes/{report}/excel', [ReportController::class, 'downloadExcel'])
        ->name('reports.excel');

    Route::middleware('admin')->group(function () {
        Route::livewire('empresa', 'pages::company.edit')->name('company.edit');
        Route::livewire('usuarios', 'pages::users.index')->name('users.index');
    });

});

// Render para el PDF: URL firmada (Chrome no tiene sesión).
Route::get('informes/{report}/pdf-render', [ReportController::class, 'pdfRender'])
    ->middleware('signed')
    ->name('reports.pdf.render');

require __DIR__.'/settings.php';
