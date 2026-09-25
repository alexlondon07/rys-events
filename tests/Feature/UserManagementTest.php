<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_editors_cannot_access_the_users_module(): void
    {
        $editor = User::factory()->create();

        $this->actingAs($editor)->get(route('users.index'))->assertForbidden();
    }

    public function test_admins_can_see_the_users_list(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin RYS']);
        $editor = User::factory()->create(['name' => 'Editor Uno']);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Usuarios y roles')
            ->assertSee('Admin RYS')
            ->assertSee('Editor Uno');
    }

    public function test_an_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::users.index')
            ->call('toggleForm')
            ->set('name', 'Nueva Persona')
            ->set('email', 'nueva@rys.test')
            ->set('role', UserRole::Editor->value)
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('createUser')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'nueva@rys.test',
            'role' => UserRole::Editor->value,
        ]);
    }

    public function test_creating_a_user_requires_a_unique_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'repetido@rys.test']);

        Livewire::actingAs($admin)
            ->test('pages::users.index')
            ->call('toggleForm')
            ->set('name', 'Otra Persona')
            ->set('email', 'repetido@rys.test')
            ->set('role', UserRole::Editor->value)
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('createUser')
            ->assertHasErrors('email');
    }

    public function test_an_admin_can_change_another_users_role(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::users.index')
            ->call('updateRole', $editor->id, UserRole::Admin->value)
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Admin, $editor->fresh()->role);
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::users.index')
            ->call('updateRole', $admin->id, UserRole::Editor->value);

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
        $this->assertSame(UserRole::Editor, $editor->fresh()->role);
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::users.index')
            ->call('deleteUser', $admin->id);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_an_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::users.index')
            ->call('deleteUser', $editor->id);

        $this->assertDatabaseMissing('users', ['id' => $editor->id]);
    }

    public function test_an_admin_can_edit_a_users_details(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->create(['name' => 'Nombre viejo', 'email' => 'viejo@rys.test']);

        Livewire::actingAs($admin)
            ->test('pages::users.index')
            ->call('startEdit', $editor->id)
            ->set('editName', 'Nombre nuevo')
            ->set('editEmail', 'nuevo@rys.test')
            ->call('updateUser')
            ->assertHasNoErrors();

        $editor->refresh();
        $this->assertSame('Nombre nuevo', $editor->name);
        $this->assertSame('nuevo@rys.test', $editor->email);
    }

    public function test_an_admin_can_disable_and_enable_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->create();

        $component = Livewire::actingAs($admin)->test('pages::users.index');

        $component->call('toggleActive', $editor->id);
        $this->assertFalse($editor->fresh()->isActive());

        $component->call('toggleActive', $editor->id);
        $this->assertTrue($editor->fresh()->isActive());
    }

    public function test_an_admin_cannot_disable_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::users.index')
            ->call('toggleActive', $admin->id);

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_an_admin_can_disable_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::users.index')
            ->call('toggleActive', $otherAdmin->id);

        $this->assertTrue($admin->fresh()->isActive());
        $this->assertFalse($otherAdmin->fresh()->isActive());
    }

    public function test_a_disabled_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'inactivo@rys.test',
            'password' => 'password',
            'active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_disabled_user_session_is_terminated(): void
    {
        $user = User::factory()->create(['active' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
