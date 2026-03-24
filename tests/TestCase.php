<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTestsDoNotUseUnsafeDatabase();
    }

    /**
     * RefreshDatabase ejecuta migrate:fresh sobre la conexión por defecto y borra todos los datos.
     *
     * Además de MySQL/Postgres, SQLite sobre un archivo (p. ej. database/database.sqlite del .env)
     * también se vacía por completo: muchos entornos usan SQLite en archivo para desarrollo.
     *
     * Solo se permiten: SQLite :memory: (phpunit.xml) o un archivo explícito database/testing.sqlite.
     */
    protected function assertTestsDoNotUseUnsafeDatabase(): void
    {
        if (! $this->app->runningUnitTests()) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb', 'pgsql', 'sqlsrv'], true)) {
            $dbName = (string) DB::connection()->getDatabaseName();
            $this->fail(
                'BLOQUEO: las pruebas intentan usar ['.$driver.'] en la base ['.$dbName.']. '.
                'RefreshDatabase / migrate:fresh BORRARÍA todos los datos de esa base (incl. farmadoc_db u otra de desarrollo). '.
                'Prohibido usar la misma base que en .env para desarrollo. '.
                'Asegura phpunit.xml → bootstrap=tests/bootstrap.php y ejecuta: php artisan test o ./vendor/bin/pest desde la raíz del proyecto. '.
                'En el IDE, la configuración de PHPUnit debe apuntar al phpunit.xml de este repo.'
            );
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $database = DB::connection()->getDatabaseName();

        if ($database === ':memory:') {
            return;
        }

        $normalized = str_replace('\\', '/', (string) $database);

        if (basename($normalized) === 'testing.sqlite') {
            return;
        }

        $this->fail(
            'BLOQUEO: SQLite en archivo ['.$database.']. migrate:fresh vaciaría ese archivo; no uses database.sqlite de desarrollo. '.
            'Usa solo :memory: (phpunit.xml + tests/bootstrap.php) o '.database_path('testing.sqlite').' dedicado a pruebas.'
        );
    }

    protected function skipUnlessFortifyFeature(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
