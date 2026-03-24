<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Arranque de PHPUnit / Pest (antes de Composer y Laravel)
|--------------------------------------------------------------------------
|
| Debe coincidir con <php> de phpunit.xml. Se aplica aquí primero para que
| Dotenv (safeLoad) no pueda sobrescribir DB_* ni APP_ENV cuando el IDE o el
| sistema exportan MySQL u otra base de desarrollo.
|
| Sin este archivo, ejecutar pruebas sin cargar phpunit.xml puede dejar
| RefreshDatabase apuntando a tu base real y vaciarla con migrate:fresh.
|
*/

$testingEnvironment = [
    'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'APP_LOCALE' => 'es',
    'APP_FALLBACK_LOCALE' => 'en',
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
];

foreach ($testingEnvironment as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}

/*
 * Quitar credenciales de MySQL/Postgres del entorno para que Dotenv no reutilice
 * host/usuario de .env y termine mezclando sqlite con datos de conexión de desarrollo.
 */
$dbKeysToStrip = [
    'DB_HOST',
    'DB_PORT',
    'DB_USERNAME',
    'DB_PASSWORD',
    'DB_SOCKET',
    'DATABASE_URL',
    'MYSQL_ATTR_SSL_CA',
    'MYSQL_ATTR_SSL_CERT',
    'MYSQL_ATTR_SSL_KEY',
];

foreach ($dbKeysToStrip as $key) {
    unset($_ENV[$key], $_SERVER[$key]);
    putenv($key);
}

require dirname(__DIR__).'/vendor/autoload.php';
