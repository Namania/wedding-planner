<?php

namespace Database\Factories;

use App\Models\Animation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Animation>
 */
class AnimationFactory extends Factory
{
    protected $model = Animation::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'price' => fake()->numberBetween(200, 3000),
            'phone' => fake()->optional(0.8)->phoneNumber(),
            'website' => fake()->optional(0.6)->url(),
            'type' => fake()->optional(0.8)->randomElement(Animation::TYPES),
            'note' => fake()->optional(0.5)->sentence(15),
            'quote_status' => fake()->optional(0.6)->randomElement(Animation::QUOTE_STATUSES),
        ];
    }
}
