<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
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
