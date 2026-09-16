<?php

namespace Database\Factories;

use App\Models\GalleryGuest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<GalleryGuest>
 */
class GalleryGuestFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->firstName().' '.fake()->randomLetter().'.';

        return [
            'name' => $name,
            'name_normalized' => GalleryGuest::normalizeName($name),
            'pin_hash' => Hash::make('1234'),
            'created_ip' => fake()->ipv4(),
        ];
    }

    public function banned(): static
    {
        return $this->state(fn () => ['banned_at' => now()]);
    }
}
