<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Château', 'Domaine', 'Manoir', 'Ferme', 'Orangerie'])
            .' '.fake()->lastName();

        return [
            'name' => $name,
            'website' => fake()->optional(0.7)->url(),
            'maps_url' => 'https://maps.google.com/?q='.str_replace(' ', '+', $name),
            'price' => fake()->numberBetween(1500, 15000),
            'note' => fake()->optional(0.5)->sentence(15),
            'quote_status' => fake()->optional(0.6)->randomElement(Venue::QUOTE_STATUSES),
        ];
    }
}
