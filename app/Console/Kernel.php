<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define los comandos Artisan disponibles.
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Define la programación de comandos (Scheduler).
     */
    protected function schedule(Schedule $schedule)
    {
        // Ejecuta el comando de actualización cada minuto,
        // solo de lunes a viernes entre las 08:30 y 18:00
        $schedule->command('cotizaciones:actualizar')
            ->everyMinute()
            ->weekdays()
            ->between('08:30', '18:00');
               // Ejecutar cada hora (puede ser cada 15 min si quieres más frecuencia)
    $schedule->command('promedios:calcular')
        ->everyHour()
        ->between('08:00', '18:00') // Solo en horario laboral
        ->weekdays(); // Solo lunes a viernes
    }
    
}
