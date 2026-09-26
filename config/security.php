<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Livewire y Alpine usan scripts inline y eval, así que la política permite
    | 'unsafe-inline' y 'unsafe-eval' en scripts. Por defecto se envía en modo
    | "report-only" (no bloquea); active CSP_ENFORCE=true cuando esté validada.
    |
    */

    'csp_enforce' => env('CSP_ENFORCE', false),

    // Opcional: sobrescribir la política completa (una sola línea).
    'csp_policy' => env('CSP_POLICY'),

];
