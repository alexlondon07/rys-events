<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Evidencia fotográfica
    |--------------------------------------------------------------------------
    |
    | Límites aplicados al subir fotos desde el asistente. Los formatos que no
    | soporta GD (por ejemplo HEIC) se rechazan con un mensaje claro en vez de
    | fallar en silencio al optimizar.
    |
    */

    'photos' => [
        'max_per_item' => (int) env('REPORTS_PHOTOS_MAX_PER_ITEM', 7),
        'max_size_kb' => (int) env('REPORTS_PHOTOS_MAX_SIZE_KB', 10240),
        'max_dimension' => (int) env('REPORTS_PHOTOS_MAX_DIMENSION', 1500),
        'quality' => (int) env('REPORTS_PHOTOS_QUALITY', 70),
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'cover_max_size_kb' => (int) env('REPORTS_COVER_MAX_SIZE_KB', 5120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Collage
    |--------------------------------------------------------------------------
    |
    | Cuando la distribución del ítem es "collage", las fotos de cada página se
    | unen en una sola imagen 2x2 con GD. Cada celda se recorta al centro para
    | cubrir la proporción indicada y el resultado se cachea en disco.
    |
    */

    'collage' => [
        'columns' => 2,
        'cell_width' => 800,
        'cell_height' => 600,
        'gap' => 8,
        'quality' => 82,
    ],

    /*
    |--------------------------------------------------------------------------
    | Agente IA de informes
    |--------------------------------------------------------------------------
    |
    | Permanece apagado por defecto. El agente genera un borrador; nunca
    | modifica ni publica un informe sin aprobación explícita del usuario.
    |
    */

    'ai' => [
        'enabled' => (bool) env('AI_REPORTS_ENABLED', false),
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_REPORTS_MODEL', 'gpt-5.4-mini'),
        'timeout' => (int) env('OPENAI_REPORTS_TIMEOUT', 120),
        'max_output_tokens' => (int) env('OPENAI_REPORTS_MAX_OUTPUT_TOKENS', 3500),
        'prompt_version' => env('OPENAI_REPORTS_PROMPT_VERSION', 'v2'),
        'input_cost_usd' => (float) env('OPENAI_REPORTS_INPUT_COST_USD', 0.75),
        'output_cost_usd' => (float) env('OPENAI_REPORTS_OUTPUT_COST_USD', 4.50),
    ],

];
