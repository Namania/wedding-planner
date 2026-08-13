<?php

namespace Database\Factories;

use App\Models\SeatingTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatingTable>
 */
class SeatingTableFactory extends Factory
{
    protected $model = SeatingTable::class;

    public function definition(): array
    {
        return [
            'name' => 'Table ' . fake()->unique()->numberBetween(1, 30),
            'capacity' => fake()->numberBetween(6, 10),
        ];
    }
}
