<?php

namespace Database\Factories;

use App\Models\Simulation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Simulation>
 */
class SimulationFactory extends Factory
{
    protected $model = Simulation::class;

    public function definition(): array
    {
        return [
            'name' => 'Simulation ' . fake()->unique()->word(),
            'is_active' => false,
            'venue_id' => null,
            'caterer_id' => null,
            'florist_id' => null,
        ];
    }
}
