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
use App\Models\TimelineEvent;
use App\Models\User;
use App\Models\Venue;
use App\Models\Wedding;
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
            // 80% des invités sont déjà placés à une table.
            if (fake()->boolean(80)) {
                $guest->update(['seating_table_id' => $seatingTables->random()->id]);
            }
        });

        Task::factory(20)->create();

        $this->seedTimeline();

        // Trois scénarios de mariage, un seul actif à la fois (contrainte en base).
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

    // Planning du jour J calé sur la date du mariage, plutôt que des horaires
    // aléatoires qui n'auraient aucun sens pour cette fonctionnalité.
    private function seedTimeline(): void
    {
        $day = Wedding::first()->date;

        $schedule = [
            ['title' => 'Préparatifs', 'time' => '08:00', 'note' => 'Coiffure, maquillage et habillage.'],
            ['title' => 'Cérémonie', 'time' => '15:00', 'note' => null],
            ['title' => 'Photos de couple', 'time' => '16:00', 'note' => null],
            ["title" => "Vin d'honneur", 'time' => '17:00', 'note' => null],
            ['title' => 'Repas', 'time' => '19:30', 'note' => null],
            ['title' => 'Ouverture du bal', 'time' => '21:30', 'note' => null],
            ['title' => 'Soirée dansante', 'time' => '22:00', 'note' => null],
            ['title' => 'Brunch du lendemain', 'time' => '11:00', 'day_offset' => 1, 'note' => null],
        ];

        foreach ($schedule as $event) {
            TimelineEvent::factory()->create([
                'title' => $event['title'],
                'starts_at' => $day->copy()->addDays($event['day_offset'] ?? 0)->setTimeFromTimeString($event['time']),
                'note' => $event['note'],
            ]);
        }
    }
}
