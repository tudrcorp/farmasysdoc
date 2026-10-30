<?php

namespace App\Services\AiAgent;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Conexión propia del agente: la sesión de MySQL queda en solo lectura y con tiempo máximo.
 * En producción usa además un usuario de MySQL que solo tiene permiso SELECT.
 */
class AiAgentReadOnlyDatabase
{
    public const string CONNECTION = 'ai_readonly';

    private bool $prepared = false;

    public function __construct(private AiAgentSqlGuard $guard) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, int $maxRows): array
    {
        $query = $this->guard->validate($sql);
        $this->ensureOnlyCurrentDatabase($query);
        $limit = max(1, $maxRows);

        $rows = $this->connection()->select("SELECT agent_result.* FROM ({$query}) AS agent_result LIMIT {$limit}");

        return array_map(fn (object $row): array => (array) $row, $rows);
    }

    /**
     * Consultas fijas de las herramientas: no pasan por el filtro, pero sí por la conexión de lectura.
     *
     * @param  list<mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    public function selectTrusted(string $sql, array $bindings = []): array
    {
        return array_map(fn (object $row): array => (array) $row, $this->connection()->select($sql, $bindings));
    }

    /**
     * El servidor aloja otras bases de datos: se rechaza cualquier referencia a ellas.
     */
    private function ensureOnlyCurrentDatabase(string $query): void
    {
        $connection = $this->connection();
        $databases = array_map(fn (object $row): string => mb_strtolower((string) ($row->name ?? '')), $connection->select('SELECT SCHEMA_NAME AS name FROM information_schema.SCHEMATA'));
        $others = array_diff($databases, [mb_strtolower($connection->getDatabaseName())]);

        preg_match_all('/[a-z0-9_$]+/', str_replace('`', '', mb_strtolower($query)), $matches);

        if (array_intersect($matches[0], $others) !== []) {
            throw new RuntimeException('Solo se puede consultar la base de datos de Farmadoc.');
        }
    }

    public function connection(): Connection
    {
        $connection = DB::connection(self::CONNECTION);

        if (! $this->prepared) {
            $timeout = (int) config('ai_agent.query_timeout_ms');
            $isMariaDb = str_contains(mb_strtolower((string) $connection->selectOne('SELECT VERSION() AS version')->version), 'mariadb');
            $connection->statement('SET SESSION TRANSACTION READ ONLY');
            $connection->statement($isMariaDb
                ? 'SET SESSION max_statement_time = '.($timeout / 1000)
                : "SET SESSION max_execution_time = {$timeout}");
            $this->prepared = true;
        }

        return $connection;
    }
}
