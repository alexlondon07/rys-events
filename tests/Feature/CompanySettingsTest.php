<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('company.edit'))->assertRedirect(route('login'));
    }

    public function test_editors_cannot_manage_company_settings(): void
    {
        $editor = User::factory()->create();

        $this->actingAs($editor)->get(route('company.edit'))->assertForbidden();
    }

    public function test_admins_can_save_the_company_settings(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::company.edit')
            ->set('name', 'Grupo RYS S.A.S')
            ->set('nit', '900123456-7')
            ->set('legal_rep_name', 'Alexander Londoño')
            ->set('legal_rep_title', 'Representante legal')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('company_settings', [
            'name' => 'Grupo RYS S.A.S',
            'nit' => '900123456-7',
            'legal_rep_name' => 'Alexander Londoño',
            'legal_rep_title' => 'Representante legal',
        ]);
    }

    public function test_admins_can_upload_a_logo_and_signature(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::company.edit')
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->set('signature', UploadedFile::fake()->image('firma.png'))
            ->call('save')
            ->assertHasNoErrors();

        $settings = CompanySetting::current();

        $this->assertNotNull($settings->logo_path);
        $this->assertNotNull($settings->signature_path);
        Storage::disk('public')->assertExists($settings->logo_path);
        Storage::disk('public')->assertExists($settings->signature_path);
    }

    public function test_admins_can_remove_the_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::company.edit')
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('save')
            ->call('removeLogo')
            ->assertHasNoErrors();

        $this->assertNull(CompanySetting::current()->logo_path);
    }

    public function test_an_invalid_file_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::company.edit')
            ->set('logo', UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasErrors('logo');
    }
}
