<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Marca como "ya ejecutadas" todas las migraciones anteriores a un corte,
 * sin ejecutarlas. Sirve para bases de datos cuyo esquema ya existe
 * (poblado por importación) pero que no tienen las migraciones registradas
 * en la tabla `migrations`.
 *
 * Uso:
 *   php artisan migrate:baseline                 → corte por defecto (2026_07_01)
 *   php artisan migrate:baseline --before=2026_07_01
 *   php artisan migrate:baseline --dry-run
 *
 * Idempotente: sólo inserta las que faltan. Nunca toca datos ni esquema.
 */
class MigrateBaseline extends Command
{
    protected $signature = 'migrate:baseline
        {--before=2026_07_01 : Marca como ejecutadas las migraciones cuyo nombre sea anterior a este prefijo}
        {--dry-run : Muestra qué se marcaría sin insertar nada}';

    protected $description = 'Marca migraciones antiguas como ejecutadas sin correrlas (baseline)';

    public function handle(): int
    {
        $before = $this->option('before');
        $dryRun = $this->option('dry-run');

        // Asegurar que la tabla migrations existe
        if (!DB::getSchemaBuilder()->hasTable('migrations')) {
            $this->error('La tabla `migrations` no existe. Corre primero: php artisan migrate:install');
            return 1;
        }

        // Migraciones ya registradas
        $registradas = DB::table('migrations')->pluck('migration')->all();

        // Archivos de migración en disco
        $archivos = collect(File::files(database_path('migrations')))
            ->map(fn($f) => $f->getFilenameWithoutExtension())
            ->sort()
            ->values();

        // Candidatas: anteriores al corte y no registradas aún
        $aMarcar = $archivos->filter(function ($nombre) use ($before, $registradas) {
            return $nombre < $before && !in_array($nombre, $registradas);
        })->values();

        if ($aMarcar->isEmpty()) {
            $this->info("Nada que marcar. Todas las migraciones anteriores a '{$before}' ya están registradas.");
            return 0;
        }

        $this->info("Migraciones a marcar como ejecutadas (corte < {$before}):");
        foreach ($aMarcar as $m) {
            $this->line("  • {$m}");
        }

        if ($dryRun) {
            $this->warn('[dry-run] No se insertó nada.');
            return 0;
        }

        // Usar un batch nuevo (siguiente al máximo actual)
        $batch = (int) DB::table('migrations')->max('batch') + 1;

        $filas = $aMarcar->map(fn($m) => ['migration' => $m, 'batch' => $batch])->all();
        DB::table('migrations')->insert($filas);

        $this->info(count($filas) . " migraciones marcadas como ejecutadas (batch {$batch}).");
        $this->line('Ahora `php artisan migrate --force` sólo correrá las migraciones nuevas.');

        return 0;
    }
}
