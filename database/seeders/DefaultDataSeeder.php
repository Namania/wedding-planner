<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\GallerySettings;
use App\Models\TimelineEvent;
use App\Models\Wedding;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $wedding = Wedding::firstOrCreate([], [
            'spouse_1_name' => 'MADAME',
            'spouse_2_name' => 'MONSIEUR',
            'date' => '2028-01-01',
        ]);

        Budget::firstOrCreate([], [
            'total' => 20000,
        ]);

        GallerySettings::firstOrCreate([], [
            'invite_token' => Str::random(64),
        ]);

        $this->seedTimeline($wedding);
    }

    private function seedTimeline(Wedding $wedding): void
    {
        if (TimelineEvent::exists()) {
            return;
        }

        $day = $wedding->date;

        $schedule = [
            ['title' => 'Préparatifs', 'time' => '08:00', 'note' => 'Coiffure, maquillage et habillage.'],
            ['title' => 'Cérémonie', 'time' => '15:00', 'note' => null],
            ['title' => 'Photos de couple', 'time' => '16:00', 'note' => null],
            ['title' => "Vin d'honneur", 'time' => '17:00', 'note' => null],
            ['title' => 'Repas', 'time' => '19:30', 'note' => null],
            ['title' => 'Ouverture du bal', 'time' => '21:30', 'note' => null],
            ['title' => 'Soirée dansante', 'time' => '22:00', 'note' => null],
            ['title' => 'Brunch du lendemain', 'time' => '11:00', 'day_offset' => 1, 'note' => null],
        ];

        foreach ($schedule as $event) {
            TimelineEvent::create([
                'title' => $event['title'],
                'starts_at' => $day->copy()->addDays($event['day_offset'] ?? 0)->setTimeFromTimeString($event['time']),
                'note' => $event['note'],
            ]);
        }
    }
}
