<?php

namespace Database\Factories;

use App\Models\Outfit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outfit>
 */
class OutfitFactory extends Factory
{
    protected $model = Outfit::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'price' => fake()->numberBetween(100, 3000),
            'spouse' => fake()->randomElement(Outfit::SPOUSES),
            'phone' => fake()->optional(0.8)->phoneNumber(),
            'website' => fake()->optional(0.6)->url(),
            'image_path' => null,
            'note' => fake()->optional(0.5)->sentence(15),
            'quote_status' => fake()->optional(0.6)->randomElement(Outfit::QUOTE_STATUSES),
        ];
    }
}
