<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReportWizardTest extends TestCase
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
            'event_name' => 'Festival de Trova',
            'status' => 'draft',
            'current_step' => 1,
        ]);

        ReportItem::create([
            'report_id' => $this->report->id,
            'ref' => 'ART-01',
            'type' => 'artistic',
            'artist_name' => 'Hebert Vargas',
            'narrative' => 'Presentación principal.',
            'sort_order' => 1,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('reports.edit', $this->report))->assertRedirect(route('login'));
    }

    public function test_the_wizard_loads_with_the_report_data(): void
    {
        $this->actingAs($this->user)
            ->get(route('reports.edit', $this->report))
            ->assertOk()
            ->assertSee('Editar informe')
            ->assertSee('Hebert Vargas');
    }

    public function test_editing_a_report_field_autosaves_it(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->set('conclusion', 'El evento se cumplió satisfactoriamente.')
            ->set('signer_name', 'Luis Rivera')
            ->assertHasNoErrors();

        $this->assertSame('El evento se cumplió satisfactoriamente.', $this->report->fresh()->conclusion);
        $this->assertNotNull($this->report->fresh()->updated_in_app_at);
    }

    public function test_editing_an_item_autosaves_it(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->set('items.0.narrative', 'Texto editado desde el asistente.')
            ->assertHasNoErrors();

        $this->assertSame(
            'Texto editado desde el asistente.',
            ReportItem::where('ref', 'ART-01')->firstOrFail()->narrative,
        );
    }

    public function test_an_item_can_be_added_and_removed(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->call('addItem', 'technical');

        $this->assertDatabaseHas('report_items', [
            'report_id' => $this->report->id,
            'type' => 'technical',
            'ref' => 'TEC-01',
        ]);

        $component->call('removeItem', 0);

        $this->assertDatabaseMissing('report_items', ['ref' => 'ART-01']);
    }

    public function test_photos_can_be_uploaded_to_an_item(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->set('uploads.0', [UploadedFile::fake()->image('evidencia.jpg', 800, 600)])
            ->call('uploadPhotos', 0)
            ->assertHasNoErrors();

        $photo = ReportItem::where('ref', 'ART-01')->firstOrFail()->photos()->firstOrFail();

        $this->assertNotNull($photo->path);
        $this->assertNotNull($photo->thumb_path);
        Storage::disk('public')->assertExists($photo->path);
        Storage::disk('public')->assertExists($photo->thumb_path);
    }

    public function test_drive_links_create_photos(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->set('items.0.drive_links', "https://drive.google.com/file/d/ABC123/view\nhttps://drive.google.com/file/d/DEF456/view")
            ->assertHasNoErrors();

        $photos = ReportItem::where('ref', 'ART-01')->firstOrFail()->photos;

        $this->assertCount(2, $photos);
        $this->assertSame('drive', $photos->first()->source);
        $this->assertSame('ABC123', $photos->first()->drive_file_id);
    }

    public function test_drive_folder_link_is_stored(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->set('items.0.drive_folder_id', 'https://drive.google.com/drive/folders/FOLDER123')
            ->assertHasNoErrors();

        $this->assertSame('FOLDER123', ReportItem::where('ref', 'ART-01')->firstOrFail()->drive_folder_id);
    }

    public function test_items_can_be_reordered(): void
    {
        ReportItem::create([
            'report_id' => $this->report->id,
            'ref' => 'ART-02',
            'type' => 'artistic',
            'artist_name' => 'Segundo',
            'sort_order' => 2,
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->call('moveItem', 1, -1)
            ->assertHasNoErrors();

        $this->assertSame('ART-02', $this->report->fresh()->items()->orderBy('sort_order')->first()->ref);
    }

    public function test_photo_captions_can_be_edited(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->set('uploads.0', [UploadedFile::fake()->image('evidencia.jpg', 800, 600)])
            ->call('uploadPhotos', 0)
            ->set('items.0.photos.0.caption', 'Leyenda de prueba')
            ->assertHasNoErrors();

        $photo = ReportItem::where('ref', 'ART-01')->firstOrFail()->photos()->firstOrFail();

        $this->assertSame('Leyenda de prueba', $photo->fresh()->caption);
    }

    public function test_photos_can_be_reordered(): void
    {
        Storage::fake('public');

        $component = Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->set('uploads.0', [UploadedFile::fake()->image('uno.jpg', 800, 600)])
            ->call('uploadPhotos', 0)
            ->set('uploads.0', [UploadedFile::fake()->image('dos.jpg', 800, 600)])
            ->call('uploadPhotos', 0);

        $photos = ReportItem::where('ref', 'ART-01')->firstOrFail()->photos()->orderBy('sort_order')->get();
        $second = $photos->last();

        $component->call('movePhoto', 0, $second->id, -1);

        $this->assertSame($second->id, ReportItem::where('ref', 'ART-01')->firstOrFail()->photos()->orderBy('sort_order')->first()->id);
    }

    public function test_cover_image_can_be_uploaded(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->set('cover', UploadedFile::fake()->image('portada.jpg', 1200, 800))
            ->call('uploadCover')
            ->assertHasNoErrors();

        $this->report->refresh();

        $this->assertNotNull($this->report->cover_path);
        Storage::disk('public')->assertExists($this->report->cover_path);
    }

    public function test_cover_image_url_resolves_a_drive_link(): void
    {
        $this->report->update(['cover_url' => 'https://drive.google.com/file/d/ABC123/view']);

        $this->assertSame(
            'https://drive.google.com/thumbnail?id=ABC123&sz=w1600',
            $this->report->fresh()->coverImageUrl(),
        );
    }

    public function test_non_image_photo_uploads_are_rejected(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->set('uploads.0', [UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf')])
            ->call('uploadPhotos', 0);

        $this->assertSame(0, ReportItem::where('ref', 'ART-01')->firstOrFail()->photos()->count());
    }

    public function test_non_image_cover_is_rejected(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->set('cover', UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'))
            ->call('uploadCover')
            ->assertHasErrors('cover');

        $this->assertNull($this->report->fresh()->cover_path);
    }

    public function test_finalizing_requires_conclusion_and_signer(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->call('finalize');

        $this->assertSame('draft', $this->report->fresh()->status);

        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $this->report])
            ->set('conclusion', 'Cierre del informe.')
            ->set('signer_name', 'Luis Rivera')
            ->call('finalize');

        $this->assertSame('final', $this->report->fresh()->status);
    }
}
