<?php

namespace HarryGulliford\Firebird\Schema;

use Illuminate\Database\Schema\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
    /**
     * Drop all tables from the database.
     */
    public function dropAllTables()
    {
        $tables = $this->getTables();

        if (empty($tables)) {
            return;
        }

        // Disable foreign key checks by dropping FKs first
        foreach ($tables as $table) {
            $foreignKeys = $this->getForeignKeys($table['name']);
            foreach ($foreignKeys as $fk) {
                $this->connection->statement(
                    sprintf('ALTER TABLE %s DROP CONSTRAINT %s',
                        $this->connection->getQueryGrammar()->wrapTable($table['name']),
                        $fk['name']
                    )
                );
            }
        }

        foreach ($tables as $table) {
            $this->connection->statement(
                'DROP TABLE '.$this->connection->getQueryGrammar()->wrapTable($table['name'])
            );
        }
    }

    /**
     * Drop all views from the database.
     */
    public function dropAllViews()
    {
        $views = $this->getViews();

        foreach ($views as $view) {
            $this->connection->statement(
                'DROP VIEW '.$this->connection->getQueryGrammar()->wrapTable($view['name'])
            );
        }
    }
}
