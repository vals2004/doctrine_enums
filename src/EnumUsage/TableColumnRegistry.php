<?php

declare(strict_types=1);

/*
 * This file is part of the "Doctrine extension to manage enumerations in PostgreSQL" package.
 * (c) Alexey Sitka <alexey.sitka@gmail.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Enumeum\DoctrineEnum\EnumUsage;

use Doctrine\DBAL\Connection;

class TableColumnRegistry
{
    private bool $loaded = false;

    /** @var string[][] */
    private array $tables = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function isColumnExists(string $table, string $column): bool
    {
        if (!$this->loaded) {
            $this->load();
        }

        return isset($this->tables[$table][$column]);
    }

    public function getColumnType(string $table, string $column): ?string
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->tables[$table][$column] ?? null;
    }

    private function load(): void
    {
        $values = $this->connection->executeQuery($this->getUsageQuery())->fetchAllAssociative();
        foreach ($values as $value) {
            $this->tables[$value['table']][$value['column']] = $value['name'];
        }

        $this->loaded = true;
    }

    private function getUsageQuery(): string
    {
        return <<<QUERY
SELECT DISTINCT
    t.typname AS "name",
    c.relname AS "table",
    quote_ident(a.attname) AS "column",
    pg_get_expr(d.adbin, d.adrelid) AS "default"
FROM pg_catalog.pg_attribute a
    JOIN pg_catalog.pg_class c ON a.attrelid = c.oid
    JOIN pg_catalog.pg_type t ON a.atttypid = t.oid
    JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
    LEFT JOIN pg_catalog.pg_attrdef d ON c.oid = d.adrelid AND a.attnum = d.adnum
WHERE n.nspname NOT IN ('pg_catalog', 'information_schema', 'pg_toast')
    AND n.nspname = ANY(current_schemas(false))
    AND a.attnum > 0
    AND c.relkind = 'r'
    AND NOT a.attisdropped
;
QUERY;
    }
}
