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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReportImportTest extends TestCase
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

    public function test_example_template_creates_a_valid_preview_and_applies_it(): void
    {
        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());

        $this->assertTrue($preview['can_apply']);
        $this->assertSame('PS-762026', $preview['contract_number']);
        $this->assertSame(23, $preview['counts']['new']);
        $this->assertSame(0, $preview['counts']['errors']);
        $this->assertSame('2026-07-24', $preview['report_payload']['report_date']);

        $import = ReportImport::create([
            'user_id' => $this->user->id,
            'original_name' => basename($this->examplePath()),
            'file_path' => 'tests/example.xlsx',
            'status' => 'previewing',
            'summary' => ['preview' => $preview],
            'errors' => $preview['issues'],
        ]);

        $result = app(ReportImportApplier::class)->apply($import, []);

        $this->assertSame(23, $result['created']);
        $this->assertDatabaseHas('reports', [
            'contract_number' => 'PS-762026',
            'status' => 'draft',
        ]);
        $this->assertDatabaseCount('report_items', 23);
        $this->assertDatabaseHas('report_items', [
            'ref' => 'ART-07',
            'artist_name' => 'Hebert Vargas',
        ]);
        $this->assertSame('applied', $import->fresh()->status);
    }

    public function test_reimport_is_idempotent_and_does_not_duplicate_items(): void
    {
        $this->applyExample();
        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());

        $this->assertSame(0, $preview['counts']['new']);
        $this->assertSame(0, $preview['counts']['changed']);
        $this->assertSame(23, $preview['counts']['unchanged']);
        $this->assertSame([], $preview['report_changes']);

        $import = $this->makeImport($preview);
        app(ReportImportApplier::class)->apply($import, []);

        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('report_items', 23);
    }

    public function test_app_edit_creates_a_conflict_and_can_be_preserved(): void
    {
        $this->applyExample();
        $item = Report::where('contract_number', 'PS-762026')->firstOrFail()
            ->items()->where('ref', 'ART-07')->firstOrFail();
        $item->update([
            'specification' => 'Versión editada en la aplicación',
            'updated_in_app_at' => now()->addSecond(),
        ]);

        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());
        $conflictIds = collect($preview['conflicts'])->pluck('id')->all();

        $this->assertContains('item:ART-07:specification', $conflictIds);

        $resolutions = collect($conflictIds)->mapWithKeys(fn (string $id) => [$id => 'excel'])->all();
        $resolutions['item:ART-07:specification'] = 'app';
        app(ReportImportApplier::class)->apply($this->makeImport($preview), $resolutions);

        $this->assertSame('Versión editada en la aplicación', $item->fresh()->specification);
    }

    public function test_import_page_requires_authentication(): void
    {
        $this->get(route('reports.import'))->assertRedirect(route('login'));
        $this->actingAs($this->user)->get(route('reports.import'))->assertOk();
    }

    public function test_livewire_upload_preview_and_apply_flow(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent(
            basename($this->examplePath()),
            file_get_contents($this->examplePath()),
        );

        Livewire::actingAs($this->user)
            ->test('pages::reports.import')
            ->set('file', $file)
            ->call('generatePreview')
            ->assertSet('preview.contract_number', 'PS-762026')
            ->assertSet('preview.counts.new', 23)
            ->assertSet('preview.can_apply', true)
            ->call('applyImport')
            ->assertSet('result.created', 23)
            ->assertSet('preview', []);

        $this->assertDatabaseHas('reports', ['contract_number' => 'PS-762026']);
        $this->assertDatabaseCount('report_items', 23);
        $this->assertSame('applied', ReportImport::firstOrFail()->status);
    }

    public function test_imported_report_can_be_reviewed_and_previewed(): void
    {
        $this->applyExample();
        $report = Report::where('contract_number', 'PS-762026')->firstOrFail();

        $this->assertSame(5, $report->inferredCurrentStep());

        $this->actingAs($this->user)
            ->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee('Editar informe')
            ->assertSee('Hebert Vargas')
            ->assertSee('23 ítems');

        $this->actingAs($this->user)
            ->get(route('reports.preview', $report))
            ->assertOk()
            ->assertSee('INFORME DE')
            ->assertSee('guadalupe-cover-photo.png')
            ->assertSee('Hebert Vargas')
            ->assertSee('Imprimir o guardar como PDF');
    }

    public function test_imports_are_versioned_per_report(): void
    {
        $this->actingAs($this->user);

        $this->applyExample();

        $second = app(ReportImportPreviewer::class)->preview($this->examplePath());
        app(ReportImportApplier::class)->apply($this->makeImport($second), []);

        $versions = ReportImport::where('status', 'applied')->orderBy('version')->pluck('version')->all();

        $this->assertSame([1, 2], $versions);
    }

    public function test_a_previous_import_file_can_be_downloaded(): void
    {
        $this->actingAs($this->user);

        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());
        $import = $this->makeImport($preview);

        Storage::fake('local');
        Storage::disk('local')->put($import->file_path, 'contenido');

        app(ReportImportApplier::class)->apply($import, []);

        $this->get(route('reports.imports.download', [$import->fresh()->report, $import]))
            ->assertOk();
    }

    public function test_reimporting_a_deleted_contract_creates_a_fresh_report(): void
    {
        $this->applyExample();

        Report::where('contract_number', 'PS-762026')->firstOrFail()->delete();

        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());

        $this->assertSame('create', $preview['report_action']);
        $this->assertTrue(
            collect($preview['issues'])->contains(fn (array $issue): bool => str_contains($issue['message'], 'informe eliminado')),
        );

        $result = app(ReportImportApplier::class)->apply($this->makeImport($preview), []);

        $this->assertSame(23, $result['created']);
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', ['contract_number' => 'PS-762026', 'deleted_at' => null]);
        $this->assertDatabaseCount('report_items', 23);
    }

    public function test_generating_a_preview_discards_the_previous_pending_one(): void
    {
        Storage::fake('local');

        $preview = function (): void {
            Livewire::actingAs($this->user)
                ->test('pages::reports.import')
                ->set('file', UploadedFile::fake()->createWithContent('e.xlsx', file_get_contents($this->examplePath())))
                ->call('generatePreview');
        };

        $preview();
        $this->assertSame(1, ReportImport::where('status', 'previewing')->count());

        $preview();
        $this->assertSame(1, ReportImport::where('status', 'previewing')->count());
    }

    public function test_a_pending_preview_can_be_reopened_and_discarded(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->user)
            ->test('pages::reports.import')
            ->set('file', UploadedFile::fake()->createWithContent('e.xlsx', file_get_contents($this->examplePath())))
            ->call('generatePreview')
            ->assertSet('preview.contract_number', 'PS-762026');

        $import = ReportImport::where('status', 'previewing')->firstOrFail();

        Livewire::actingAs($this->user)
            ->test('pages::reports.import')
            ->call('loadPreview', $import->id)
            ->assertSet('preview.contract_number', 'PS-762026');

        Livewire::actingAs($this->user)
            ->test('pages::reports.import')
            ->call('prepareDiscard', $import->id)
            ->call('discardImport');

        $this->assertSame(0, ReportImport::where('status', 'previewing')->count());
    }

    public function test_the_history_hides_imports_of_deleted_reports(): void
    {
        $this->applyExample();

        Livewire::actingAs($this->user)
            ->test('pages::reports.import')
            ->assertSee('ejemplo_guadalupe_PS-762026.xlsx');

        Report::where('contract_number', 'PS-762026')->firstOrFail()->delete();

        Livewire::actingAs($this->user)
            ->test('pages::reports.import')
            ->assertDontSee('ejemplo_guadalupe_PS-762026.xlsx');
    }

    /** @return array<string, mixed> */
    private function applyExample(): array
    {
        $preview = app(ReportImportPreviewer::class)->preview($this->examplePath());

        return app(ReportImportApplier::class)->apply($this->makeImport($preview), []);
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
