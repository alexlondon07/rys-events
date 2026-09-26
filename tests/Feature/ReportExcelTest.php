<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\ReportImport;
use App\Models\ReportItem;
use App\Models\User;
use App\Services\ReportImport\TemplateReader;
use App\Services\Reports\ReportExcelExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportExcelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $department = Department::create(['dane_code' => '05', 'name' => 'Antioquia', 'normalized_name' => 'antioquia']);
        $municipality = Municipality::create([
            'department_id' => $department->id,
            'dane_code' => '05315',
            'name' => 'Guadalupe',
            'normalized_name' => 'guadalupe',
        ]);

        $this->report = Report::create([
            'contract_number' => 'PS-762026',
            'municipality_id' => $municipality->id,
            'event_name' => 'Fiestas',
            'status' => 'draft',
            'current_step' => 1,
        ]);

        ReportItem::create([
            'report_id' => $this->report->id,
            'ref' => 'ART-01',
            'type' => 'artistic',
            'category_label' => 'SERVICIOS ARTÍSTICOS',
            'artist_name' => 'Hebert Vargas',
            'narrative' => 'Se presentó.',
            'quantity' => 1,
            'unit' => 'agrupada',
            'photo_layout' => 'pair',
            'sort_order' => 1,
        ]);
    }

    public function test_the_report_can_be_downloaded_as_excel(): void
    {
        Storage::fake('local');

        $this->actingAs($this->user)
            ->get(route('reports.excel', $this->report))
            ->assertOk();
    }

    public function test_the_exported_excel_can_be_read_back(): void
    {
        Storage::fake('local');

        $path = app(ReportExcelExporter::class)->export($this->report);

        $source = app(TemplateReader::class)->read(Storage::disk('local')->path($path));

        $this->assertSame('rys-informe-plantilla:v1', $source['version']);
        $this->assertSame('PS-762026', $source['report']['numero_contrato']);
        $this->assertSame('Guadalupe', $source['report']['municipio']);
        $this->assertCount(1, $source['items']);
        $this->assertSame('ART-01', $source['items'][0]['ref']);
        $this->assertSame('2 por página', $source['items'][0]['distribucion_fotos']);
    }

    public function test_the_exported_excel_includes_a_history_sheet(): void
    {
        Storage::fake('local');

        ReportImport::create([
            'report_id' => $this->report->id,
            'user_id' => $this->user->id,
            'original_name' => 'ejemplo.xlsx',
            'file_path' => 'imports/ejemplo.xlsx',
            'status' => 'applied',
            'version' => 1,
            'photos_added' => 3,
            'summary' => ['result' => ['created' => 1, 'updated' => 0, 'unchanged' => 0]],
        ]);

        $path = app(ReportExcelExporter::class)->export($this->report->fresh());
        $sheets = app(TemplateReader::class)->readSheets(Storage::disk('local')->path($path));

        $this->assertArrayHasKey('Historial', $sheets);
        $this->assertSame('version', $sheets['Historial'][1][0]);
        $this->assertSame('v1', $sheets['Historial'][3][0]);
        $this->assertSame('1 nuevos · 0 actualizados · 0 sin cambios · 3 fotos', $sheets['Historial'][3][4]);
    }
}
