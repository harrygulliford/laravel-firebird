<?php

namespace Firebird;

class Connection extends \Illuminate\Database\Connection
{
    /**
     * Get the default query grammar instance.
     *
     * @return \Firebird\Query\Grammars\FirebirdGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammars\FirebirdGrammar();
    }

    /**
     * Get the default post processor instance.
     *
     * @return \Firebird\Query\Processors\FirebirdProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processors\FirebirdProcessor();
    }

    /**
     * Get a schema builder instance for this connection.
     *
     * @return \Firebird\Schema\Builder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new Schema\Builder($this);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Firebird\Schema\Grammars\FirebirdGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new Schema\Grammars\FirebirdGrammar());
    }

    /**
     * Get query builder.
     *
     * @return \Firebird\Query\Builder
     */
    protected function getQueryBuilder()
    {
        return new Query\Builder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    /**
     * Get a new query builder instance.
     *
     * @return \Firebird\Query\Builder
     */
    public function query()
    {
        return $this->getQueryBuilder();
    }

    /**
     * Execute stored function.
     *
     * @param string $function
     * @param array $values
     * @return mixed
     */
    public function executeFunction($function, array $values = null)
    {
        $query = $this->getQueryBuilder();

        return $query->executeFunction($function, $values);
    }

    /**
     * Execute stored procedure.
     *
     * @param string $procedure
     * @param array $values
     */
    public function executeProcedure($procedure, array $values = null)
    {
        $query = $this->getQueryBuilder();

        $query->executeProcedure($procedure, $values);
    }
}
