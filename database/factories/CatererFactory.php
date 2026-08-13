<?php

namespace Database\Factories;

use App\Models\Caterer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Caterer>
 */
class CatererFactory extends Factory
{
    protected $model = Caterer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'price_per_person' => fake()->numberBetween(40, 180),
            'phone' => fake()->optional(0.8)->phoneNumber(),
            'website' => fake()->optional(0.6)->url(),
            'service_type' => fake()->optional(0.8)->randomElement(Caterer::SERVICE_TYPES),
            'note' => fake()->optional(0.5)->sentence(15),
            'quote_status' => fake()->optional(0.6)->randomElement(Caterer::QUOTE_STATUSES),
        ];
    }
}
