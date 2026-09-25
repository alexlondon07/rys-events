<?php

namespace Tests\Feature;

use App\Actions\Reports\StoreItemPhotos;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\User;
use App\Services\Photos\CollageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CollageBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Report $report;

    private ReportItem $item;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->item = ReportItem::create([
            'report_id' => $this->report->id,
            'ref' => 'ART-01',
            'type' => 'artistic',
            'artist_name' => 'Hebert Vargas',
            'sort_order' => 1,
            'photo_layout' => 'collage',
        ]);
    }

    public function test_it_composes_local_photos_into_a_single_image(): void
    {
        Storage::fake('public');
        $this->addUploads(4);

        $path = app(CollageBuilder::class)->build($this->item, $this->item->photos()->get());

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $image = imagecreatefromjpeg(Storage::disk('public')->path($path));

        $this->assertNotFalse($image);
        $this->assertSame(1608, imagesx($image));
        $this->assertSame(1208, imagesy($image));
    }

    public function test_it_reuses_the_cached_collage(): void
    {
        Storage::fake('public');
        $this->addUploads(2);

        $builder = app(CollageBuilder::class);
        $first = $builder->build($this->item, $this->item->photos()->get());
        $second = $builder->build($this->item, $this->item->photos()->get());

        $this->assertSame($first, $second);
    }

    public function test_it_returns_null_when_a_photo_is_not_local(): void
    {
        Storage::fake('public');
        $this->addUploads(2);

        $this->item->photos()->create([
            'source' => 'drive',
            'drive_file_id' => 'ABC123',
            'layout' => 'collage',
            'sort_order' => 3,
        ]);

        $this->assertNull(
            app(CollageBuilder::class)->build($this->item, $this->item->photos()->get()),
        );
    }

    public function test_the_preview_embeds_the_composed_collage(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->addUploads(4);

        $path = app(CollageBuilder::class)->build($this->item, $this->item->photos()->get());

        $this->assertNotNull($path);

        $this->actingAs($user)
            ->get(route('reports.preview', $this->report))
            ->assertOk()
            ->assertSee($path, false);
    }

    private function addUploads(int $count): void
    {
        $files = [];

        for ($i = 1; $i <= $count; $i++) {
            $files[] = UploadedFile::fake()->image("foto{$i}.jpg", 1200, 900);
        }

        app(StoreItemPhotos::class)->handle($this->item, $files);
    }
}
