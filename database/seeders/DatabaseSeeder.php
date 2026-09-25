<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::firstOrNew(['email' => 'alexlondon07@gmail.com']);

        $admin->name ??= 'Alexander Andrés Londoño Espejo';
        $admin->role = UserRole::Admin;
        $admin->active = true;

        if (! $admin->exists) {
            $admin->email_verified_at = now();
            $admin->password = 'password';
        }

        $admin->save();

        $this->call(LibrarySeeder::class);
    }
}
