<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ItemCatalog;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\TextTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_library_pages_require_authentication(): void
    {
        $this->get(route('templates.index'))->assertRedirect(route('login'));
        $this->get(route('catalog.index'))->assertRedirect(route('login'));
    }

    public function test_a_text_template_can_be_created_and_edited(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::templates.index')
            ->call('create')
            ->set('key', 'introduction')
            ->set('name', 'Introducción estándar')
            ->set('body', 'Evento en {municipio} durante {dias} días.')
            ->call('save')
            ->assertHasNoErrors();

        $template = TextTemplate::where('name', 'Introducción estándar')->firstOrFail();
        $this->assertSame('introduction', $template->key);

        Livewire::actingAs($this->user)
            ->test('pages::templates.index')
            ->call('startEdit', $template->id)
            ->set('name', 'Introducción corregida')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Introducción corregida', $template->fresh()->name);
    }

    public function test_a_catalog_item_can_be_created(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::catalog.index')
            ->call('create')
            ->set('type', 'technical')
            ->set('category_label', 'PLANTA ELÉCTRICA')
            ->set('specification', 'Suministro de planta eléctrica.')
            ->set('default_unit', 'días')
            ->set('default_quantity', '1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('item_catalog', [
            'type' => 'technical',
            'category_label' => 'PLANTA ELÉCTRICA',
        ]);
    }

    public function test_the_wizard_applies_a_text_template_with_variables(): void
    {
        [$report] = $this->makeReport();

        $template = TextTemplate::create([
            'key' => 'conclusion',
            'name' => 'Conclusión',
            'body_with_variables' => 'Evento {evento} en {municipio} ({departamento}) del contrato {contrato}.',
            'active' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $report])
            ->call('applyTemplate', 'conclusion', (string) $template->id)
            ->assertHasNoErrors()
            ->assertSet('templateReset', 1);

        $this->assertSame(
            'Evento Fiestas en Guadalupe (Antioquia) del contrato PS-762026.',
            $report->fresh()->conclusion,
        );
    }

    public function test_the_wizard_imports_an_item_from_the_catalog(): void
    {
        [$report] = $this->makeReport();

        $catalog = ItemCatalog::create([
            'type' => 'technical',
            'category_label' => 'ILUMINACIÓN ESCÉNICA',
            'specification' => 'Montaje de iluminación.',
            'default_narrative' => 'Se instaló la iluminación.',
            'default_unit' => 'días',
            'default_quantity' => 2,
            'active' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::reports.wizard', ['report' => $report])
            ->call('goToStep', 4)
            ->call('importCatalogItem', $catalog->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('report_items', [
            'report_id' => $report->id,
            'type' => 'technical',
            'category_label' => 'ILUMINACIÓN ESCÉNICA',
            'narrative' => 'Se instaló la iluminación.',
        ]);
    }

    /** @return array{0: Report, 1: User} */
    private function makeReport(): array
    {
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
            'event_name' => 'Fiestas',
            'status' => 'draft',
            'current_step' => 1,
        ]);

        return [$report, $this->user];
    }
}
