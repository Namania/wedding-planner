<?php

namespace Database\Factories;

use App\Models\Florist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Florist>
 */
class FloristFactory extends Factory
{
    protected $model = Florist::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'price' => fake()->numberBetween(300, 3000),
            'phone' => fake()->optional(0.8)->phoneNumber(),
            'website' => fake()->optional(0.6)->url(),
            'style' => fake()->optional(0.8)->randomElement(Florist::STYLES),
            'note' => fake()->optional(0.5)->sentence(15),
            'quote_status' => fake()->optional(0.6)->randomElement(Florist::QUOTE_STATUSES),
        ];
    }
}
