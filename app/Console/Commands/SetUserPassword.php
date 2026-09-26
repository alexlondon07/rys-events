<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Cambia la contraseña de un usuario desde la consola.
 *
 * Sirve como recuperación cuando no hay proveedor de correo configurado o
 * cuando el único administrador quedó bloqueado.
 */
class SetUserPassword extends Command
{
    protected $signature = 'user:password {email} {--password=}';

    protected $description = 'Cambia la contraseña de un usuario (recuperación sin correo)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No existe un usuario con el correo {$email}.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('Nueva contraseña');

        if (! is_string($password) || strlen($password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        if (! $this->option('password') && $this->secret('Repita la contraseña') !== $password) {
            $this->error('Las contraseñas no coinciden.');

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        $this->info("Contraseña actualizada para {$user->email}.");

        return self::SUCCESS;
    }
}
