<?php

/**
 * Firebird Connection for Laravel.
 *
 * Version-aware connection that can detect server capabilities.
 *
 * Feature availability by server version:
 *   FB 2.5: FIRST/SKIP, GEN_ID, stored procedures, CONTAINING, UPDATE OR INSERT
 *   FB 3.0: FETCH FIRST, BOOLEAN, IDENTITY columns, window functions, OFFSET/ROWS
 *   FB 4.0: LATERAL JOIN, INT128, DECFLOAT, TZ types, named windows, LAG/LEAD
 *   FB 5.0: SKIP LOCKED, partial indexes, MERGE RETURNING, scrollable cursors
 *
 * Wire protocol (negotiated between client and server):
 *   P12 = FB 2.5, P15 = FB 3.0.2, P16 = FB 4.0, P18 = FB 5.0, P19 = FB 5.0.3
 *   Client libfbclient 3.0 negotiates max P15 even against FB 5.0 server.
 *
 * SQL Dialect (property of the .fdb file):
 *   Dialect 1: InterBase legacy — DATE includes time, "" = string literal, no BOOLEAN, no TIME
 *   Dialect 3: Modern — DATE = date-only, "" = identifier, BOOLEAN (FB 3.0+), TIME available
 *   Dialect is auto-negotiated; can be forced via dialect= in DSN.
 */

namespace HarryGulliford\Firebird;

use HarryGulliford\Firebird\Query\Builder as FirebirdQueryBuilder;
use HarryGulliford\Firebird\Query\Grammars\FirebirdGrammar as FirebirdQueryGrammar;
use HarryGulliford\Firebird\Query\Processors\FirebirdProcessor as FirebirdQueryProcessor;
use HarryGulliford\Firebird\Schema\Builder as FirebirdSchemaBuilder;
use HarryGulliford\Firebird\Schema\Grammars\FirebirdGrammar as FirebirdSchemaGrammar;
use Illuminate\Database\Connection as DatabaseConnection;
use Illuminate\Support\Str;
use PDO;

class FirebirdConnection extends DatabaseConnection
{
    /**
     * {@inheritDoc}
     */
    public function getDriverTitle()
    {
        return 'Firebird';
    }

    /**
     * Get the server version for the connection.
     *
     * Parses the PDO version string which looks like:
     *   Firebird/Linux/ARM64 (remote server), version "LI-V5.0.2.1613 Firebird 5.0/tcp ..."
     *
     * @return string  e.g. "5.0.2"
     */
    public function getServerVersion(): string
    {
        $version = $this->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

        return Str::match('/\(remote server\), version "\w+-V(\d+\.\d+\.\d+)/', $version);
    }

    /**
     * Get the server major version number.
     *
     * Useful for version-aware feature detection:
     *   2 = FB 2.5 (legacy, FIRST/SKIP, GEN_ID, no BOOLEAN)
     *   3 = FB 3.0 (IDENTITY, BOOLEAN, FETCH FIRST, window functions)
     *   4 = FB 4.0 (LATERAL, INT128, DECFLOAT, TZ types, 63-char identifiers)
     *   5 = FB 5.0 (SKIP LOCKED, partial indexes, MERGE RETURNING)
     *
     * @return int
     */
    public function getServerMajorVersion(): int
    {
        $version = $this->getServerVersion();

        return (int) explode('.', $version)[0];
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new FirebirdQueryGrammar($this);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \Illuminate\Database\Query\Processors\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new FirebirdQueryProcessor;
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new FirebirdSchemaBuilder($this);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Illuminate\Database\Schema\Grammars\Grammar|null
     */
    protected function getDefaultSchemaGrammar()
    {
        return new FirebirdSchemaGrammar($this);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function query()
    {
        return new FirebirdQueryBuilder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }

    /**
     * Execute a stored procedure.
     *
     * Selectable procedures (FB 1.0+): SELECT * FROM procedure(args)
     * Executable procedures (FB 1.0+): EXECUTE PROCEDURE name(args)
     *
     * @param  string  $procedure
     * @param  array  $bindings
     * @return \Illuminate\Support\Collection
     */
    public function executeProcedure(string $procedure, array $bindings = [])
    {
        return $this->query()->procedure($procedure, $bindings)->get();
    }

    /**
     * Escape a boolean value for Firebird.
     *
     * FB 2.5 and Dialect 1: no native BOOLEAN — use 0/1 (SMALLINT/CHAR)
     * FB 3.0+ Dialect 3: native BOOLEAN with TRUE/FALSE/UNKNOWN
     *
     * We use 0/1 for maximum cross-version compatibility.
     *
     * @param  bool  $value
     * @return string
     */
    protected function escapeBool($value)
    {
        return $value ? '1' : '0';
    }

    /**
     * Escape a binary value for Firebird.
     *
     * Firebird hex literal: x'DEADBEEF' (all versions)
     *
     * @param  string  $value
     * @return string
     */
    protected function escapeBinary($value)
    {
        $hex = bin2hex($value);

        return "x'{$hex}'";
    }

    /**
     * Determine if the given exception is a unique constraint error.
     *
     * Firebird error codes (from jrd/iberror.h):
     *   -803: isc_no_dup (violation of PRIMARY or UNIQUE KEY constraint)
     *
     * @param  \Exception  $exception
     * @return bool
     */
    protected function isUniqueConstraintError(\Exception $exception)
    {
        return str_contains($exception->getMessage(), 'violation of PRIMARY or UNIQUE KEY constraint')
            || str_contains($exception->getMessage(), '-803');
    }

    /**
     * Parse the constraint name and columns from a unique constraint violation exception.
     *
     * Firebird error format: violation of PRIMARY or UNIQUE KEY constraint "CONSTRAINT_NAME" on table "TABLE_NAME"
     * Problematic key value is ("COL1" = val1, "COL2" = val2)
     *
     * @param  \Exception  $exception
     * @return array{index: string|null, columns: array}
     */
    protected function parseUniqueConstraintViolation(\Exception $exception): array
    {
        $index = null;
        $columns = [];

        if (preg_match('/constraint "([^"]+)"/', $exception->getMessage(), $matches)) {
            $index = $matches[1];
        }

        if (preg_match_all('/"([^"]+)"\s*=/', $exception->getMessage(), $colMatches)) {
            $columns = $colMatches[1];
        }

        return ['index' => $index, 'columns' => $columns];
    }
}
