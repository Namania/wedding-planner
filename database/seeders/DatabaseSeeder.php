<?php

namespace Database\Seeders;

use App\Models\Animation;
use App\Models\Caterer;
use App\Models\Florist;
use App\Models\Guest;
use App\Models\Outfit;
use App\Models\SeatingTable;
use App\Models\Simulation;
use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(DefaultDataSeeder::class);

        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $venues = Venue::factory(5)->create();
        $caterers = Caterer::factory(5)->create();
        $florists = Florist::factory(4)->create();
        $animations = Animation::factory(6)->create();
        $outfits = Outfit::factory(8)->create();

        $seatingTables = SeatingTable::factory(6)->create();
        Guest::factory(40)->create()->each(function (Guest $guest) use ($seatingTables) {
            if (fake()->boolean(80)) {
                $guest->update(['seating_table_id' => $seatingTables->random()->id]);
            }
        });

        Task::factory(20)->create();

        Simulation::factory()->create([
            'name' => 'Scénario principal',
            'is_active' => true,
            'venue_id' => $venues->random()->id,
            'caterer_id' => $caterers->random()->id,
            'florist_id' => $florists->random()->id,
        ])->animations()->attach($animations->random(rand(1, 3))->pluck('id'));

        Simulation::factory(2)->create()->each(function (Simulation $simulation) use ($venues, $caterers, $florists, $animations, $outfits) {
            $simulation->update([
                'venue_id' => $venues->random()->id,
                'caterer_id' => $caterers->random()->id,
                'florist_id' => $florists->random()->id,
            ]);
            $simulation->animations()->attach($animations->random(rand(1, 3))->pluck('id'));
            $simulation->outfits()->attach($outfits->random(rand(1, 2))->pluck('id'));
        });
    }
}
