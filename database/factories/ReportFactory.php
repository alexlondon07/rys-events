<?php

namespace Database\Factories;

use App\Models\Municipality;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_number' => 'PS-'.fake()->unique()->numberBetween(100000, 999999),
            'event_name' => fake()->sentence(3),
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'current_step' => 1,
        ];
    }

    public function forMunicipality(Municipality $municipality): static
    {
        return $this->state(fn (): array => ['municipality_id' => $municipality->id]);
    }

    public function final(): static
    {
        return $this->state(fn (): array => ['status' => 'final']);
    }
}
