<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manual_requires_authentication(): void
    {
        $this->get(route('manual'))->assertRedirect(route('login'));
    }

    public function test_the_manual_renders_the_content_and_index(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('manual'))
            ->assertOk()
            ->assertSee('Manual de uso')
            ->assertSee('Cargar desde Excel')
            ->assertSee('id="cargar-desde-excel"', false)
            ->assertSee('manual/01-ingreso.png', false)
            ->assertDontSee('Manual no disponible');
    }
}
