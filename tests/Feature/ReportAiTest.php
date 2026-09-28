<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportAiDraft;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\ReportAiRun;
use App\Models\ReportItem;
use App\Models\User;
use App\Services\Reports\ReportAiReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportAiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('reports.ai.enabled', true);
        config()->set('reports.ai.api_key', 'test-key');
        config()->set('reports.ai.model', 'gpt-5.4-mini');

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $department = Department::create([
            'dane_code' => '05',
            'name' => 'Antioquia',
            'normalized_name' => 'antioquia',
        ]);
        $municipality = Municipality::create([
            'department_id' => $department->id,
            'dane_code' => '05315',
            'name' => 'Guadalupe',
            'normalized_name' => 'guadalupe',
        ]);
        $this->report = Report::create([
            'contract_number' => 'PS-IA-001',
            'municipality_id' => $municipality->id,
            'event_name' => 'Evento de prueba',
            'status' => 'draft',
            'current_step' => 1,
        ]);
    }

    public function test_the_ai_draft_is_queued_without_changing_the_report(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->from(route('reports.show', $this->report))
            ->post(route('reports.ai.generate', $this->report))
            ->assertRedirect(route('reports.show', $this->report));

        Queue::assertPushed(GenerateReportAiDraft::class);
        $this->assertDatabaseHas('report_ai_runs', [
            'report_id' => $this->report->id,
            'status' => 'queued',
            'model' => 'gpt-5.4-mini',
        ]);
        $this->assertNull($this->report->fresh()->introduction);
    }

    public function test_the_report_screen_shows_the_ai_panel_only_when_enabled(): void
    {
        $this->actingAs($this->user)
            ->get(route('reports.show', $this->report))
            ->assertOk()
            ->assertSee('Agente IA de informes')
            ->assertSee('Generar borrador IA');

        config()->set('reports.ai.enabled', false);

        $this->actingAs($this->user)
            ->get(route('reports.show', $this->report))
            ->assertOk()
            ->assertDontSee('Agente IA de informes');
    }

    public function test_the_job_saves_a_structured_draft_and_usage_cost(): void
    {
        Http::fake([
            '*chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'executive_summary' => 'Resumen de prueba.',
                            'report' => [
                                'introduction' => 'Introducción corregida.',
                                'event_description' => 'Descripción corregida.',
                                'conclusion' => 'Conclusión corregida.',
                            ],
                            'quality_issues' => [],
                            'items' => [],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 1000,
                    'completion_tokens' => 500,
                ],
            ], 200),
        ]);

        $run = ReportAiRun::create([
            'report_id' => $this->report->id,
            'user_id' => $this->user->id,
            'status' => 'queued',
            'model' => 'gpt-5.4-mini',
            'prompt_version' => 'v1',
            'source_hash' => app(ReportAiReportService::class)->sourceHash($this->report),
        ]);

        (new GenerateReportAiDraft($run))->handle(app(ReportAiReportService::class));

        $run->refresh();
        $this->assertSame('ready', $run->status);
        $this->assertSame('Resumen de prueba.', $run->result['executive_summary']);
        $this->assertSame(1000, $run->input_tokens);
        $this->assertSame(500, $run->output_tokens);
        $this->assertSame(0.003, (float) $run->cost_usd);
        $this->assertNull($this->report->fresh()->introduction);
    }

    public function test_approval_applies_only_the_report_and_item_texts(): void
    {
        $item = ReportItem::create([
            'report_id' => $this->report->id,
            'ref' => 'IT-001',
            'type' => 'technical',
            'category_label' => 'Sonido',
            'narrative' => 'Texto original.',
            'sort_order' => 1,
        ]);
        $run = ReportAiRun::create([
            'report_id' => $this->report->id,
            'user_id' => $this->user->id,
            'status' => 'ready',
            'model' => 'gpt-5.4-mini',
            'prompt_version' => 'v1',
            'source_hash' => app(ReportAiReportService::class)->sourceHash($this->report),
            'result' => [
                'executive_summary' => 'Resumen.',
                'report' => [
                    'introduction' => 'Introducción aprobada.',
                    'event_description' => 'Descripción aprobada.',
                    'conclusion' => 'Conclusión aprobada.',
                ],
                'quality_issues' => [],
                'items' => [[
                    'ref' => 'IT-001',
                    'revised_narrative' => 'Texto técnico aprobado.',
                    'finding' => 'Sin observaciones.',
                    'recommendation' => 'Conservar evidencia.',
                    'priority' => 'no_aplica',
                ]],
            ],
        ]);

        $this->actingAs($this->user)
            ->from(route('reports.show', $this->report))
            ->post(route('reports.ai.approve', [$this->report, $run]))
            ->assertRedirect(route('reports.show', $this->report));

        $this->assertSame('approved', $run->fresh()->status);
        $this->assertSame('Introducción aprobada.', $this->report->fresh()->introduction);
        $this->assertSame('Texto técnico aprobado.', $item->fresh()->narrative);
        $this->assertDatabaseHas('report_activity_logs', [
            'report_id' => $this->report->id,
            'source' => 'app',
            'field' => 'introduction',
        ]);
    }
}
