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
    ->withoutOverlapping(10)        // skip si el anterior aún corre (max 10 min lock)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/bsale-sync.log'));

