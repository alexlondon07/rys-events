<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
