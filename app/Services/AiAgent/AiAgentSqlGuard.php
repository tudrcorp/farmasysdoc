<?php

namespace App\Services\AiAgent;

use RuntimeException;

/**
 * Filtro de las consultas que escribe la IA. Es una capa más: la conexión también
 * es de solo lectura. Bloquea columnas por nombre y prohíbe `*`, porque un alias
 * podría ocultar una columna sensible en el resultado.
 */
final class AiAgentSqlGuard
{
    /**
     * @var list<string>
     */
    private const array FORBIDDEN_KEYWORDS = [
        'insert', 'update', 'delete', 'replace', 'drop', 'alter', 'create', 'truncate', 'grant', 'revoke',
        'rename', 'call', 'set', 'lock', 'unlock', 'handler', 'load', 'into', 'outfile', 'dumpfile', 'execute', 'prepare',
        'load_file', 'sleep', 'benchmark', 'get_lock', 'release_lock', 'user', 'current_user', 'database', 'version',
    ];

    /**
     * Devuelve la consulta lista para ejecutar, sin el punto y coma final.
     */
    public function validate(string $sql): string
    {
        $query = rtrim(trim($sql), "; \n\r\t");

        if ($query === '') {
            throw new RuntimeException('La consulta está vacía.');
        }

        if (preg_match('/--|\/\*|#/', $this->withoutStrings($query)) === 1) {
            throw new RuntimeException('La consulta no puede tener comentarios.');
        }

        $code = mb_strtolower($this->withoutStrings($query));

        if (str_contains($code, ';')) {
            throw new RuntimeException('Solo se permite una consulta a la vez.');
        }

        if (preg_match('/^(select|with)\b/', $code) !== 1) {
            throw new RuntimeException('Solo se permiten consultas de lectura.');
        }

        if (preg_match('/\b(information_schema|performance_schema|mysql|sys)\b/', $code) === 1) {
            throw new RuntimeException('No se puede consultar el catálogo del servidor.');
        }

        if (preg_match('/\b(select|all|distinct|distinctrow|high_priority|straight_join|sql_[a-z_]+)\s*\*|[,.]\s*\*/', $code) === 1) {
            throw new RuntimeException('Indica las columnas: no se permite `*`.');
        }

        preg_match_all('/[a-z_][a-z0-9_$]*/', str_replace('`', '', $code), $matches);
        $identifiers = array_unique($matches[0]);

        foreach ($identifiers as $identifier) {
            $this->ensureAllowed($identifier);
        }

        return $query;
    }

    public function isColumnAllowed(string $column): bool
    {
        return preg_match((string) config('ai_agent.blocked_column_pattern'), mb_strtolower($column)) !== 1;
    }

    public function isTableAllowed(string $table): bool
    {
        return ! in_array(mb_strtolower($table), config('ai_agent.blocked_tables'), true);
    }

    private function ensureAllowed(string $identifier): void
    {
        if (in_array($identifier, self::FORBIDDEN_KEYWORDS, true)) {
            throw new RuntimeException("La palabra «{$identifier}» no está permitida.");
        }

        if (! $this->isTableAllowed($identifier)) {
            throw new RuntimeException("La tabla «{$identifier}» no está disponible.");
        }

        if (! $this->isColumnAllowed($identifier)) {
            throw new RuntimeException("La columna «{$identifier}» es confidencial.");
        }
    }

    private function withoutStrings(string $sql): string
    {
        return (string) preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'|\"(?:[^\"\\\\]|\\\\.)*\"/s", "''", $sql);
    }
}
