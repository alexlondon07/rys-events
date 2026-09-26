<?php

namespace Tests\Feature;

use App\Jobs\SyncItemDrivePhotos;
use App\Jobs\SyncReportDrivePhotos;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\ReportItemPhoto;
use App\Models\User;
use App\Services\Drive\DriveClient;
use App\Services\Photos\PhotoOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeDriveClient;
use Tests\TestCase;

class DriveSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Report $report;

    private ReportItem $item;

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

        $this->item = ReportItem::create([
            'report_id' => $this->report->id,
            'ref' => 'ART-01',
            'type' => 'artistic',
            'sort_order' => 1,
        ]);
    }

    public function test_it_downloads_pending_drive_photos_to_local_storage(): void
    {
        Storage::fake('public');

        $photo = $this->drivePhoto('FILE1');
        $client = (new FakeDriveClient)->withImage('FILE1');

        (new SyncItemDrivePhotos($this->item))->handle($client, app(PhotoOptimizer::class));

        $photo->refresh();
        $this->assertSame('synced', $photo->sync_status);
        $this->assertNotNull($photo->path);
        $this->assertNotNull($photo->thumb_path);
        $this->assertNull($photo->sync_error);
        Storage::disk('public')->assertExists($photo->path);
        Storage::disk('public')->assertExists($photo->thumb_path);
    }

    public function test_it_imports_new_images_from_a_drive_folder_incrementally(): void
    {
        Storage::fake('public');

        $this->item->update([
            'evidence_url' => 'https://drive.google.com/drive/folders/FOLDER1',
            'drive_folder_id' => 'FOLDER1',
        ]);

        $client = new FakeDriveClient;
        $client->folders['FOLDER1'] = [
            ['id' => 'FILE9', 'name' => 'evidencia.jpg', 'mime_type' => 'image/jpeg'],
        ];
        $client->withImage('FILE9');

        (new SyncItemDrivePhotos($this->item))->handle($client, app(PhotoOptimizer::class));

        $photo = $this->item->photos()->where('drive_file_id', 'FILE9')->first();

        $this->assertNotNull($photo);
        $this->assertSame('evidencia.jpg', $photo->original_name);
        $this->assertSame('synced', $photo->sync_status);

        // Una segunda pasada no duplica.
        (new SyncItemDrivePhotos($this->item))->handle($client, app(PhotoOptimizer::class));

        $this->assertSame(1, $this->item->photos()->where('drive_file_id', 'FILE9')->count());
    }

    public function test_it_records_a_permission_error_without_stopping(): void
    {
        Storage::fake('public');

        $photo = $this->drivePhoto('FILE2');
        $client = (new FakeDriveClient)->withImage('FILE2');
        $client->failDownloads = ['FILE2'];

        (new SyncItemDrivePhotos($this->item))->handle($client, app(PhotoOptimizer::class));

        $photo->refresh();
        $this->assertSame('failed', $photo->sync_status);
        $this->assertStringContainsString('Sin permiso', (string) $photo->sync_error);
        $this->assertNull($photo->path);
    }

    public function test_it_does_nothing_when_drive_is_not_configured(): void
    {
        Storage::fake('public');

        $photo = $this->drivePhoto('FILE3');
        $client = new FakeDriveClient(configured: false);

        (new SyncItemDrivePhotos($this->item))->handle($client, app(PhotoOptimizer::class));

        $this->assertSame('pending', $photo->fresh()->sync_status);
    }

    public function test_the_report_sync_queues_one_job_per_item(): void
    {
        Queue::fake();

        ReportItem::create([
            'report_id' => $this->report->id,
            'ref' => 'ART-02',
            'type' => 'artistic',
            'sort_order' => 2,
        ]);

        (new SyncReportDrivePhotos($this->report))->handle();

        Queue::assertPushed(SyncItemDrivePhotos::class, 2);
    }

    public function test_the_sync_route_queues_the_report_sync(): void
    {
        Queue::fake();
        $this->app->instance(DriveClient::class, new FakeDriveClient);

        $this->actingAs($this->user)
            ->from(route('reports.show', $this->report))
            ->post(route('reports.drive.sync', $this->report))
            ->assertRedirect(route('reports.show', $this->report));

        Queue::assertPushed(SyncReportDrivePhotos::class);
    }

    public function test_the_sync_route_warns_when_drive_is_not_configured(): void
    {
        Queue::fake();
        $this->app->instance(DriveClient::class, new FakeDriveClient(configured: false));

        $this->actingAs($this->user)
            ->from(route('reports.show', $this->report))
            ->post(route('reports.drive.sync', $this->report))
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    private function drivePhoto(string $fileId): ReportItemPhoto
    {
        return $this->item->photos()->create([
            'source' => 'drive',
            'drive_file_id' => $fileId,
            'drive_url' => "https://drive.google.com/file/d/{$fileId}/view",
            'layout' => 'pair',
            'sort_order' => 1,
            'sync_status' => 'pending',
        ]);
    }
}
