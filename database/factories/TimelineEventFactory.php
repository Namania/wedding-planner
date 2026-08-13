<?php

namespace Database\Factories;

use App\Models\TimelineEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimelineEvent>
 */
class TimelineEventFactory extends Factory
{
    protected $model = TimelineEvent::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'starts_at' => fake()->dateTimeBetween('now', '+18 months'),
            'note' => fake()->optional(0.5)->sentence(12),
        ];
    }
}
