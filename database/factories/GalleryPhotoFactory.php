<?php

namespace Database\Factories;

use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GalleryPhoto>
 */
class GalleryPhotoFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::uuid()->toString();

        return [
            'user_id' => User::factory()->guest(),
            'path' => "gallery/2028/01/{$name}.jpg",
            'thumb_path' => "gallery/2028/01/{$name}_thumb.jpg",
            'width' => 2560,
            'height' => 1707,
            'size_bytes' => fake()->numberBetween(200_000, 2_000_000),
            'caption' => fake()->boolean(30) ? fake()->sentence(4) : null,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['hidden_at' => now()]);
    }
}
