<?php

namespace Database\Factories;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        // La cérémonie et le vin d'honneur sont les moments les plus suivis ;
        // le repas et le brunch du lendemain le sont un peu moins.
        $attendance = array_values(array_filter([
            'ceremony',
            fake()->boolean(90) ? 'cocktail' : null,
            fake()->boolean(80) ? 'dinner' : null,
            fake()->boolean(30) ? 'brunch' : null,
        ]));

        return [
            'name' => fake()->name(),
            'role' => fake()->optional(0.25)->randomElement(['witness', 'groomsman', 'bridesmaid']),
            'confirmed' => fake()->optional(0.8)->boolean(75),
            'attendance' => $attendance,
            'seating_table_id' => null,
        ];
    }
}
