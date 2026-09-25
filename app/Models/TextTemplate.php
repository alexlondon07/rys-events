<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'name', 'body_with_variables', 'active'])]
class TextTemplate extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return array<string, string> */
    public static function keyOptions(): array
    {
        return [
            'introduction' => 'Introducción',
            'description' => 'Descripción del evento',
            'coordination' => 'Coordinación previa',
            'hospitality' => 'Hospitalidad',
            'conclusion' => 'Conclusión',
            'other' => 'Otro',
        ];
    }
}
