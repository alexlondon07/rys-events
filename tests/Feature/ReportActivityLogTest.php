<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\ReportImport;
use App\Models\User;
use App\Services\ReportImport\ReportImportApplier;
use App\Services\ReportImport\ReportImportPreviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportActivityLogTest extends TestCase
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

    public function test_first_import_records_activity_for_the_report_and_every_item(): void
    {
        $this->actingAs($this->user);

        $this->applyExample();

        $report = Report::where('contract_number', 'PS-762026')->firstOrFail();

        $this->assertSame(24, $report->activityLogs()->count());
        $this->assertDatabaseHas('report_activity_logs', [
            'report_id' => $report->id,
            'action' => 'created',
            'source' => 'excel',
            'user_id' => $this->user->id,
        ]);
        $this->assertDatabaseHas('report_activity_logs', [
            'report_id' => $report->id,
            'item_ref' => 'ART-07',
            'action' => 'created',
            'source' => 'excel',
        ]);
    }

    public function test_the_report_view_can_filter_activity_by_item(): void
    {
        $this->actingAs($this->user);
        $this->applyExample();

        $report = Report::where('contract_number', 'PS-762026')->firstOrFail();

        $unfiltered = $this->get(route('reports.show', $report));
        $unfiltered->assertOk();
        $this->assertSame(24, $unfiltered->viewData('activityCount'));

        $filtered = $this->get(route('reports.show', ['report' => $report, 'item' => 'ART-07']));
        $filtered->assertOk()
            ->assertSee('Filtrando por')
            ->assertSee('Ver todo');

        $this->assertSame('ART-07', $filtered->viewData('selectedItem'));
        $this->assertSame(1, $filtered->viewData('activityCount'));
    }

    public function test_reimport_without_changes_does_not_add_activity(): void
    {
        $this->actingAs($this->user);

        $this->applyExample();
        $report = Report::where('contract_number', 'PS-762026')->firstOrFail();
        $before = $report->activityLogs()->count();

        $this->applyExample();

        $this->assertSame($before, $report->activityLogs()->count());
    }

    public function test_app_edit_is_recorded_as_an_application_change(): void
    {
        $this->actingAs($this->user);

        $this->applyExample();
        $item = Report::where('contract_number', 'PS-762026')->firstOrFail()
            ->items()->where('ref', 'ART-07')->firstOrFail();
        $originalNarrative = $item->narrative;

        $item->update(['narrative' => 'Texto editado en la aplicación']);

        $this->assertDatabaseHas('report_activity_logs', [
            'report_id' => $item->report_id,
            'report_item_id' => $item->id,
            'item_ref' => 'ART-07',
            'field' => 'narrative',
            'label' => 'Actividad ejecutada',
            'old_value' => $originalNarrative,
            'new_value' => 'Texto editado en la aplicación',
            'action' => 'updated',
            'source' => 'app',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_import_change_is_recorded_as_an_excel_change(): void
    {
        $this->actingAs($this->user);

        $this->applyExample();
        $item = Report::where('contract_number', 'PS-762026')->firstOrFail()
            ->items()->where('ref', 'ART-07')->firstOrFail();
        $item->update([
            'narrative' => 'Texto editado en la aplicación',
            'updated_in_app_at' => now()->addSecond(),
        ]);

        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());
        $resolutions = collect($preview['conflicts'])->pluck('id')->mapWithKeys(fn (string $id) => [$id => 'excel'])->all();
        app(ReportImportApplier::class)->apply($this->makeImport($preview), $resolutions);

        $this->assertDatabaseHas('report_activity_logs', [
            'report_item_id' => $item->id,
            'field' => 'narrative',
            'source' => 'excel',
        ]);
    }

    private function applyExample(): void
    {
        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());
        app(ReportImportApplier::class)->apply($this->makeImport($preview), []);
    }

    /** @param array<string, mixed> $preview */
    private function makeImport(array $preview): ReportImport
    {
        return ReportImport::create([
            'report_id' => $preview['report_id'],
            'user_id' => $this->user->id,
            'original_name' => basename($this->examplePath()),
            'file_path' => 'tests/example.xlsx',
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
