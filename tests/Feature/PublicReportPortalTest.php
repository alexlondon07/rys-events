<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicReportPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $department = Department::create(['dane_code' => '05', 'name' => 'Antioquia', 'normalized_name' => 'antioquia']);
        $municipality = Municipality::create(['department_id' => $department->id, 'dane_code' => '05315', 'name' => 'Guadalupe', 'normalized_name' => 'guadalupe']);
        $this->report = Report::create([
            'user_id' => $this->user->id,
            'municipality_id' => $municipality->id,
            'contract_number' => 'PS-762026',
            'event_name' => 'Fiestas de Guadalupe',
            'status' => 'final',
        ]);
    }

    public function test_final_report_can_generate_a_revocable_public_portal_link(): void
    {
        $this->actingAs($this->user)
            ->post(route('reports.public.create', $this->report))
            ->assertRedirect();

        $token = $this->report->fresh()->public_share_token;

        $this->assertNotNull($token);
        $this->assertSame(64, strlen($token));
        $this->get(route('reports.public.show', $token))
            ->assertOk()
            ->assertSee('Portal de consulta')
            ->assertSee('PS-762026');
    }

    public function test_drafts_cannot_be_shared_publicly(): void
    {
        $this->report->update(['status' => 'draft']);

        $this->actingAs($this->user)
            ->post(route('reports.public.create', $this->report))
            ->assertStatus(422);

        $this->assertNull($this->report->fresh()->public_share_token);
    }

    public function test_revoked_portal_link_is_not_available(): void
    {
        $this->report->forceFill(['public_share_token' => str_repeat('a', 64)])->save();

        $this->actingAs($this->user)
            ->delete(route('reports.public.revoke', $this->report))
            ->assertRedirect();

        $this->get(route('reports.public.show', str_repeat('a', 64)))->assertNotFound();
    }
}
