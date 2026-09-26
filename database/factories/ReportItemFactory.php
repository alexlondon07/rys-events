<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportItem>
 */
class ReportItemFactory extends Factory
{
    protected $model = ReportItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'ref' => 'ART-'.fake()->unique()->numerify('###'),
            'type' => 'artistic',
            'artist_name' => fake()->name(),
            'narrative' => fake()->paragraph(),
            'sort_order' => 1,
            'photo_layout' => 'pair',
        ];
    }

    public function technical(): static
    {
        return $this->state(fn (): array => [
            'type' => 'technical',
            'artist_name' => null,
            'category_label' => 'Sonido, microfonería y distribución eléctrica',
        ]);
    }
}
