<?php

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
     * @return string
     */
    public function getServerVersion(): string
    {
        $version = $this->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

        return Str::match('/\(remote server\), version "\w+-V(\d+\.\d+\.\d+)/', $version);
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
     * Firebird 2.5 uses 0/1, FB 3.0+ supports TRUE/FALSE.
     *
     * @param  bool  $value
     * @return string
     */
    protected function escapeBool($value)
    {
        // Use integer representation for maximum compatibility across Firebird versions
        return $value ? '1' : '0';
    }

    /**
     * Escape a binary value for Firebird.
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
     * @param  \Exception  $exception
     * @return bool
     */
    protected function isUniqueConstraintError(\Exception $exception)
    {
        return str_contains($exception->getMessage(), 'violation of PRIMARY or UNIQUE KEY constraint')
            || str_contains($exception->getMessage(), '-803');
    }

    /**
     * Parse the constraint name from a unique constraint violation exception.
     *
     * @param  \Exception  $exception
     * @return string|null
     */
    protected function parseUniqueConstraintViolation(\Exception $exception): ?string
    {
        if (preg_match('/constraint "([^"]+)"/', $exception->getMessage(), $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get the server major version number.
     *
     * @return int
     */
    public function getServerMajorVersion(): int
    {
        $version = $this->getServerVersion();
        return (int) explode('.', $version)[0];
    }
}
