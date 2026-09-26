<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

/**
 * Reglas de acceso a los informes.
 *
 * El sistema es una herramienta interna de equipo: cualquier usuario activo
 * puede ver y editar los informes (varias personas cargan fotos del evento).
 * Las acciones destructivas (eliminar) quedan para un administrador o para
 * quien creó el informe.
 */
class ReportPolicy
{
    public function view(User $user, Report $report): bool
    {
        return $user->isActive();
    }

    public function update(User $user, Report $report): bool
    {
        return $user->isActive();
    }

    public function delete(User $user, Report $report): bool
    {
        return $user->isAdmin() || $report->user_id === $user->id;
    }

    public function restore(User $user, Report $report): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Report $report): bool
    {
        return $user->isAdmin();
    }
}
