<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Municipality $municipality;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $department = Department::create(['dane_code' => '05', 'name' => 'Antioquia', 'normalized_name' => 'antioquia']);
        $this->municipality = Municipality::create([
            'department_id' => $department->id,
            'dane_code' => '05315',
            'name' => 'Guadalupe',
            'normalized_name' => 'guadalupe',
        ]);
    }

    public function test_the_index_lists_reports(): void
    {
        $this->makeReport('PS-762026', 'draft');
        $this->makeReport('PS-999999', 'final');

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('PS-762026')
            ->assertSee('PS-999999');
    }

    public function test_the_search_filters_reports(): void
    {
        $this->makeReport('PS-762026', 'draft', 'Fiestas de Guadalupe');
        $this->makeReport('PS-111111', 'draft', 'Otro evento');

        Livewire::actingAs($this->user)
            ->test('pages::reports.index')
            ->set('search', '762026')
            ->assertSee('PS-762026')
            ->assertDontSee('PS-111111');
    }

    public function test_the_status_filter_works(): void
    {
        $this->makeReport('PS-762026', 'draft');
        $this->makeReport('PS-111111', 'final');

        Livewire::actingAs($this->user)
            ->test('pages::reports.index')
            ->set('status', 'final')
            ->assertSee('PS-111111')
            ->assertDontSee('PS-762026');
    }

    public function test_reports_are_paginated(): void
    {
        foreach (range(1, 12) as $index) {
            $this->makeReport('PS-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT), 'draft');
        }

        Livewire::actingAs($this->user)
            ->test('pages::reports.index')
            ->set('sort', 'contract')
            ->assertSee('PS-000010')
            ->assertDontSee('PS-000012');
    }

    public function test_a_report_can_be_created(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::reports.index')
            ->call('openCreate')
            ->set('newContract', 'PS-123456')
            ->set('newMunicipality', $this->municipality->id)
            ->call('createReport')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('reports', [
            'contract_number' => 'PS-123456',
            'municipality_id' => $this->municipality->id,
            'status' => 'draft',
        ]);
    }

    public function test_creating_a_report_requires_a_unique_contract(): void
    {
        $this->makeReport('PS-123456', 'draft');

        Livewire::actingAs($this->user)
            ->test('pages::reports.index')
            ->call('openCreate')
            ->set('newContract', 'PS-123456')
            ->set('newMunicipality', $this->municipality->id)
            ->call('createReport')
            ->assertHasErrors('newContract');
    }

    public function test_a_report_can_be_duplicated_with_its_items(): void
    {
        $report = $this->makeReport('PS-100000', 'draft');
        ReportItem::create([
            'report_id' => $report->id,
            'ref' => 'ART-01',
            'type' => 'artistic',
            'artist_name' => 'Artista',
            'sort_order' => 1,
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::reports.index')
            ->call('duplicateReport', $report->id);

        $copy = Report::query()->where('contract_number', 'like', 'PS-100000 (copia)%')->firstOrFail();

        $this->assertSame('draft', $copy->status);
        $this->assertSame(1, $copy->items()->count());
        $this->assertSame('ART-01', $copy->items()->first()->ref);
    }

    public function test_a_report_can_be_deleted(): void
    {
        $report = $this->makeReport('PS-200000', 'draft');

        Livewire::actingAs($this->user)
            ->test('pages::reports.index')
            ->call('deleteReport', $report->id);

        $this->assertSoftDeleted('reports', ['id' => $report->id]);
    }

    private function makeReport(string $contract, string $status, string $event = 'Evento'): Report
    {
        return Report::create([
            'contract_number' => $contract,
            'municipality_id' => $this->municipality->id,
            'event_name' => $event,
            'status' => $status,
            'current_step' => 1,
        ]);
    }
}
