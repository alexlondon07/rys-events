<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportPdf;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\ReportPdfGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ReportPdfTest extends TestCase
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

        CompanySetting::current();
    }

    public function test_the_render_view_requires_a_valid_signature(): void
    {
        $this->get(route('reports.pdf.render', $this->report))->assertForbidden();

        $signed = URL::temporarySignedRoute('reports.pdf.render', now()->addMinutes(5), ['report' => $this->report->id]);

        $this->get($signed)->assertOk()->assertSee('INFORME DE');
    }

    public function test_downloading_a_missing_pdf_returns_not_found(): void
    {
        Storage::fake('local');

        $this->actingAs($this->user)
            ->get(route('reports.pdf.download', $this->report))
            ->assertNotFound();
    }

    public function test_a_generated_pdf_can_be_downloaded(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('reports/1/informe.pdf', '%PDF-1.4 contenido');
        $this->report->update(['pdf_path' => 'reports/1/informe.pdf']);

        $this->actingAs($this->user)
            ->get(route('reports.pdf.download', $this->report))
            ->assertOk();
    }

    public function test_the_watermark_is_only_enabled_for_drafts(): void
    {
        $signed = URL::temporarySignedRoute('reports.pdf.render', now()->addMinutes(5), ['report' => $this->report->id]);

        $this->get($signed)
            ->assertOk()
            ->assertSee('text-[#17150F] report-is-draft', false);

        $this->report->update(['status' => 'final']);

        $this->get($signed)
            ->assertOk()
            ->assertDontSee('text-[#17150F] report-is-draft', false);
    }

    public function test_generating_the_pdf_queues_a_job(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->from(route('reports.show', $this->report))
            ->post(route('reports.pdf.generate', $this->report))
            ->assertRedirect(route('reports.show', $this->report));

        Queue::assertPushed(GenerateReportPdf::class);
        $this->assertSame('queued', $this->report->fresh()->pdf_status);
    }

    public function test_the_pdf_is_not_queued_twice_while_generating(): void
    {
        Queue::fake();
        $this->report->update(['pdf_status' => 'processing']);

        $this->actingAs($this->user)
            ->from(route('reports.show', $this->report))
            ->post(route('reports.pdf.generate', $this->report));

        Queue::assertNothingPushed();
        $this->assertSame('processing', $this->report->fresh()->pdf_status);
    }

    public function test_the_job_marks_the_report_as_ready(): void
    {
        Storage::fake('local');

        $generator = \Mockery::mock(ReportPdfGenerator::class);
        $generator->shouldReceive('generate')->once()->andReturn('reports/1/informe.pdf');

        (new GenerateReportPdf($this->report))->handle($generator);

        $this->report->refresh();
        $this->assertSame('ready', $this->report->pdf_status);
        $this->assertSame('reports/1/informe.pdf', $this->report->pdf_path);
        $this->assertNull($this->report->pdf_error);
    }

    public function test_the_job_marks_the_report_as_failed(): void
    {
        $generator = \Mockery::mock(ReportPdfGenerator::class);
        $generator->shouldReceive('generate')->once()->andThrow(new \RuntimeException('No se encontró Chrome'));

        (new GenerateReportPdf($this->report))->handle($generator);

        $this->report->refresh();
        $this->assertSame('failed', $this->report->pdf_status);
        $this->assertSame('No se encontró Chrome', $this->report->pdf_error);
    }

    public function test_the_status_endpoint_reports_the_generation_state(): void
    {
        $this->report->update(['pdf_status' => 'processing']);

        $this->actingAs($this->user)
            ->getJson(route('reports.pdf.status', $this->report))
            ->assertOk()
            ->assertJson(['status' => 'processing', 'ready' => false]);

        $this->report->update(['pdf_status' => 'ready', 'pdf_path' => 'reports/1/informe.pdf']);

        $this->actingAs($this->user)
            ->getJson(route('reports.pdf.status', $this->report))
            ->assertOk()
            ->assertJson(['status' => 'ready', 'ready' => true]);
    }
}
