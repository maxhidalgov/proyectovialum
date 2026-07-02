<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ──────────────────────────────────────────────────────────────────────────────
//  Sincronización automática con Bsale
//  Corre cada 4 horas → facturas de venta (año actual) + compras (smart mode)
//
//  Para activar en Railway: ver Procfile (worker: php artisan schedule:work)
// ──────────────────────────────────────────────────────────────────────────────

Schedule::command('bsale:sync')
    ->everyFourHours()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/bsale-sync.log'));

// Sincroniza estado de cobranza (monto_por_cobrar) desde Chipax /dtes
// Corre cada 6 horas → actualiza qué facturas están pagadas según Chipax
Schedule::command('chipax:sync-cobranza')
    ->everySixHours()
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/chipax-cobranza.log'));

// ──────────────────────────────────────────────────────────────────────────────
//  Check diario de asistencia via Workera
//  Lunes–Viernes a las 09:30 → inyecta reporte en chat IA producción
// ──────────────────────────────────────────────────────────────────────────────
Schedule::command('workera:check-diario')
    ->weekdays()
    ->at('09:30')
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/workera-asistencia.log'));

