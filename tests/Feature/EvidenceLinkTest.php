<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\User;
use App\Services\Photos\EvidenceLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvidenceLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_classifies_the_supported_links(): void
    {
        $this->assertSame('drive_folder', EvidenceLink::make('https://drive.google.com/drive/folders/ABC123')->type());
        $this->assertSame('drive_folder', EvidenceLink::make('https://drive.google.com/drive/u/1/folders/ABC123')->type());
        $this->assertSame('drive_file', EvidenceLink::make('https://drive.google.com/file/d/ABC123/view')->type());
        $this->assertSame('drive_file', EvidenceLink::make('https://drive.google.com/open?id=ABC123')->type());
        $this->assertSame('image', EvidenceLink::make('https://cdn.example.com/foto.JPG')->type());
        $this->assertSame('image', EvidenceLink::make('https://example.com/foto.png?size=large')->type());
        $this->assertSame('other', EvidenceLink::make('https://example.com/galeria')->type());
        $this->assertSame('other', EvidenceLink::make('https://example.com/view?id=ABC123')->type());
        $this->assertNull(EvidenceLink::make('   '));
    }

    public function test_it_builds_embed_and_image_urls(): void
    {
        $folder = EvidenceLink::make('https://drive.google.com/drive/folders/FOLDER123');
        $this->assertSame('https://drive.google.com/embeddedfolderview?id=FOLDER123#grid', $folder->embedUrl());
        $this->assertSame('https://drive.google.com/drive/folders/FOLDER123', $folder->openUrl());

        $file = EvidenceLink::make('https://drive.google.com/file/d/FILE123/view');
        $this->assertSame('https://drive.google.com/thumbnail?id=FILE123&sz=w1600', $file->imageUrl());
        $this->assertNull($file->embedUrl());

        $image = EvidenceLink::make('https://example.com/foto.jpg');
        $this->assertSame('https://example.com/foto.jpg', $image->imageUrl());
        $this->assertNull($image->embedUrl());

        $other = EvidenceLink::make('https://example.com/galeria');
        $this->assertNull($other->imageUrl());
        $this->assertSame('https://example.com/galeria', $other->embedUrl());
    }

    public function test_the_preview_embeds_an_image_evidence_link(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $department = Department::create(['dane_code' => '05', 'name' => 'Antioquia', 'normalized_name' => 'antioquia']);
        $municipality = Municipality::create([
            'department_id' => $department->id,
            'dane_code' => '05315',
            'name' => 'Guadalupe',
            'normalized_name' => 'guadalupe',
        ]);
        $report = Report::create([
            'contract_number' => 'PS-762026',
            'municipality_id' => $municipality->id,
            'event_name' => 'Festival de Trova',
            'status' => 'draft',
            'current_step' => 1,
        ]);
        ReportItem::create([
            'report_id' => $report->id,
            'ref' => 'ART-01',
            'type' => 'artistic',
            'sort_order' => 1,
            'evidence_url' => 'https://cdn.example.com/evidencia.jpg',
        ]);

        $this->actingAs($user)
            ->get(route('reports.preview', $report))
            ->assertOk()
            ->assertSee('https://cdn.example.com/evidencia.jpg', false)
            ->assertSee('Evidencia (Imagen)', false);
    }
}
