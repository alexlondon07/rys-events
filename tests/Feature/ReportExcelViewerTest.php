<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\ReportImport;
use App\Models\User;
use App\Services\ReportImport\ReportExcelGrid;
use App\Services\ReportImport\ReportImportApplier;
use App\Services\ReportImport\ReportImportPreviewer;
use App\Services\ReportImport\TemplateReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportExcelViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $department = Department::create([
            'dane_code' => '05',
            'name' => 'Antioquia',
            'normalized_name' => 'antioquia',
        ]);
        Municipality::create([
            'department_id' => $department->id,
            'dane_code' => '05315',
            'name' => 'Guadalupe',
            'normalized_name' => 'guadalupe',
        ]);
    }

    public function test_the_viewer_requires_authentication(): void
    {
        $this->get(route('reports.excel.viewer'))->assertRedirect(route('login'));
        $this->actingAs($this->user)->get(route('reports.excel.viewer'))->assertOk();
    }

    public function test_the_viewer_shows_the_real_grid_of_a_loaded_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/ejemplo.xlsx', file_get_contents($this->examplePath()));

        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());
        $import = $this->makeImport($preview, 'imports/ejemplo.xlsx');
        app(ReportImportApplier::class)->apply($import, []);
        $report = $import->fresh()->report;

        $this->actingAs($this->user)
            ->get(route('reports.excel.viewer', ['informe' => $report->id, 'carga' => $import->id]))
            ->assertOk()
            ->assertSee('Visor del Excel')
            ->assertSee('ART-07')
            ->assertSee('Hebert Vargas')
            ->assertSee('Qué significa cada columna')
            ->assertSee('Distribución de fotos');
    }

    public function test_the_viewer_marks_rows_that_are_unchanged(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/ejemplo.xlsx', file_get_contents($this->examplePath()));

        $first = $this->makeImport(app(ReportImportPreviewer::class)->preview($this->examplePath()), 'imports/ejemplo.xlsx');
        app(ReportImportApplier::class)->apply($first, []);
        $report = $first->fresh()->report;

        $second = $this->makeImport(app(ReportImportPreviewer::class)->preview($this->examplePath()), 'imports/ejemplo.xlsx', $report->id);

        $this->actingAs($this->user)
            ->get(route('reports.excel.viewer', ['informe' => $report->id, 'carga' => $second->id]))
            ->assertOk()
            ->assertSee('Sin cambios');
    }

    public function test_the_viewer_warns_when_the_file_is_missing(): void
    {
        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());
        $import = $this->makeImport($preview, 'imports/no-existe.xlsx');
        app(ReportImportApplier::class)->apply($import, []);
        $report = $import->fresh()->report;

        $this->actingAs($this->user)
            ->get(route('reports.excel.viewer', ['informe' => $report->id, 'carga' => $import->id]))
            ->assertOk()
            ->assertSee('No se pudo leer el archivo');
    }

    public function test_the_grid_service_maps_columns_and_row_statuses(): void
    {
        $sheets = app(TemplateReader::class)->readSheets($this->examplePath());
        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());

        $grid = new ReportExcelGrid($sheets, $preview);

        $this->assertSame(['Informe', 'Items', 'Fotos', '_meta'], $grid->sheetNames());

        $art01 = collect($grid->rows('Items'))->firstWhere('number', 4);

        $this->assertNotNull($art01);
        $this->assertSame('ART-01', $art01['cells'][0]);
        $this->assertSame('create', $art01['status']['code']);
        $this->assertSame('data', $art01['kind']);

        $guide = collect($grid->guide('Items'));

        $this->assertSame('ref', $guide->first()['key']);
        $this->assertSame('Referencia', $guide->first()['title']);
        $this->assertSame('artist_name', $guide->firstWhere('key', 'artista')['field']);
        $this->assertSame('photo_layout', $guide->firstWhere('key', 'distribucion_fotos')['field']);

        $informe = collect($grid->guide('Informe'));
        $this->assertSame('numero_contrato', $informe->first()['key']);
        $this->assertSame('contract_object', $informe->firstWhere('key', 'objeto_contrato')['field']);
    }

    public function test_the_grid_service_marks_unchanged_items_after_an_import(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/ejemplo.xlsx', file_get_contents($this->examplePath()));

        $first = $this->makeImport(app(ReportImportPreviewer::class)->preview($this->examplePath()), 'imports/ejemplo.xlsx');
        app(ReportImportApplier::class)->apply($first, []);

        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());
        $grid = new ReportExcelGrid(app(TemplateReader::class)->readSheets($this->examplePath()), $preview);

        $art01 = collect($grid->rows('Items'))->firstWhere('number', 4);

        $this->assertSame('unchanged', $art01['status']['code']);
        $this->assertSame([], $preview['report_changes']);
    }

    /** @param array<string, mixed> $preview */
    private function makeImport(array $preview, string $filePath, ?int $reportId = null): ReportImport
    {
        return ReportImport::create([
            'report_id' => $reportId ?? $preview['report_id'],
            'user_id' => $this->user->id,
            'original_name' => basename($this->examplePath()),
            'file_path' => $filePath,
            'status' => 'previewing',
            'summary' => ['preview' => $preview],
            'errors' => $preview['issues'],
        ]);
    }

    private function examplePath(): string
    {
        return base_path('plantillas/ejemplo_guadalupe_PS-762026.xlsx');
    }
}
