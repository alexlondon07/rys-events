<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga a los administradores a tener la verificación en dos pasos activa.
 *
 * Se aplica a las rutas de la aplicación (no a la configuración), para que el
 * admin pueda entrar a `settings/security` y configurarla.
 */
class RequireTwoFactorForAdmins
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isAdmin() && is_null($user->two_factor_confirmed_at)) {
            return redirect()
                ->route('security.edit')
                ->with('status', 'Por seguridad, active la verificación en dos pasos para continuar.');
        }

        return $next($request);
    }
}
