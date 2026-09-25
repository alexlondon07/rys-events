<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type', 'category_label', 'specification', 'default_narrative',
    'default_unit', 'default_quantity', 'active',
])]
class ItemCatalog extends Model
{
    protected $table = 'item_catalog';

    protected function casts(): array
    {
        return [
            'default_quantity' => 'decimal:3',
            'active' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            'artistic' => 'Artístico',
            'technical' => 'Técnico',
        ];
    }
}
