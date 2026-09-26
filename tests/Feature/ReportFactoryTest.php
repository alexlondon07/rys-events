<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_report_and_item_factories_create_valid_records(): void
    {
        $report = Report::factory()->create();
        $item = ReportItem::factory()->for($report)->create();
        $technical = ReportItem::factory()->for($report)->technical()->create(['ref' => 'TEC-01']);

        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'draft']);
        $this->assertSame($report->id, $item->report_id);
        $this->assertSame('artistic', $item->type);
        $this->assertSame('technical', $technical->type);
        $this->assertNull($technical->artist_name);
        $this->assertSame(2, $report->items()->count());
    }

    public function test_the_report_factory_can_target_a_municipality_and_be_final(): void
    {
        $report = Report::factory()->final()->create();

        $this->assertSame('final', $report->status);
    }
}
