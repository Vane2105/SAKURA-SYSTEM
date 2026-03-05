<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Evento;
use App\Models\Stand;

class SeedStandsForExistingEvents extends Command
{
    protected $signature = 'stands:fix-existing';
    protected $description = 'Ensure all events have exactly 20 stands';

    public function handle()
    {
        $eventos = Evento::withCount('stands')->get();

        foreach ($eventos as $evento) {
            $currentCount = $evento->stands_count;
            if ($currentCount < 20) {
                $missing = 20 - $currentCount;
                $this->info("Adding $missing stands to event: {$evento->nombre}");
                
                for ($i = $currentCount + 1; $i <= 20; $i++) {
                    $evento->stands()->create([
                        'name' => "Stand $i",
                        'precio' => 0,
                        'status' => 'disponible'
                    ]);
                }
            } else if ($currentCount > 20) {
                $this->warn("Event {$evento->nombre} has $currentCount stands. Manual cleanup might be needed if you want exactly 20.");
            }
        }

        $this->info('Stand sync completed.');
    }
}
