<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\User;
use App\Services\Photos\EvidenceLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityTest extends TestCase
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
            'user_id' => $this->user->id,
            'contract_number' => 'PS-762026',
            'municipality_id' => $municipality->id,
            'event_name' => 'Fiestas',
            'status' => 'draft',
            'current_step' => 1,
        ]);
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->assertFalse(Route::has('register'));
    }

    public function test_delete_is_restricted_to_admin_or_owner(): void
    {
        $owner = $this->user;
        $otherEditor = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);

        $this->assertTrue(Gate::forUser($owner)->allows('delete', $this->report));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $this->report));
        $this->assertFalse(Gate::forUser($otherEditor)->allows('delete', $this->report));
    }

    public function test_any_active_user_can_view_and_update(): void
    {
        $editor = User::factory()->create(['email_verified_at' => now()]);

        $this->assertTrue(Gate::forUser($editor)->allows('view', $this->report));
        $this->assertTrue(Gate::forUser($editor)->allows('update', $this->report));

        $inactive = User::factory()->create(['email_verified_at' => now(), 'active' => false]);

        $this->assertFalse(Gate::forUser($inactive)->allows('view', $this->report));
        $this->assertFalse(Gate::forUser($inactive)->allows('update', $this->report));
    }

    public function test_evidence_link_blocks_private_and_reserved_hosts(): void
    {
        $this->assertFalse(EvidenceLink::make('http://127.0.0.1/secret')->isServerSafe());
        $this->assertFalse(EvidenceLink::make('http://169.254.169.254/latest/meta-data/')->isServerSafe());
        $this->assertFalse(EvidenceLink::make('http://10.0.0.5/admin')->isServerSafe());
        $this->assertFalse(EvidenceLink::make('http://192.168.1.10/')->isServerSafe());
        $this->assertFalse(EvidenceLink::make('ftp://example.com/file.jpg')->isServerSafe());

        $this->assertTrue(EvidenceLink::make('https://8.8.8.8/foto.jpg')->isServerSafe());
        $this->assertTrue(EvidenceLink::make('data:image/png;base64,AAAA')->isServerSafe());
    }

    public function test_the_preview_does_not_embed_a_private_evidence_url(): void
    {
        ReportItem::create([
            'report_id' => $this->report->id,
            'ref' => 'ART-01',
            'type' => 'artistic',
            'sort_order' => 1,
            'evidence_url' => 'http://127.0.0.1:9/internal',
        ]);

        $this->actingAs($this->user)
            ->get(route('reports.preview', $this->report))
            ->assertOk()
            ->assertDontSee('<iframe src="http://127.0.0.1', false)
            ->assertSee('no se incrusta');
    }

    public function test_hsts_header_is_sent_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $response = (new SecurityHeaders)->handle(
            Request::create('https://example.com/'),
            fn () => response('ok'),
        );

        $this->assertTrue($response->headers->has('Strict-Transport-Security'));
    }
}
