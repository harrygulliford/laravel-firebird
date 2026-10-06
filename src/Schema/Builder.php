<?php

namespace HarryGulliford\Firebird\Schema;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
    /**
     * Drop all tables from the database.
     *
     * @return void
     */
    public function dropAllTables()
    {
        $tables = array_column($this->getTables(), 'name');

        if (empty($tables)) {
            return;
        }

        // Drop the foreign keys first, so the tables can be dropped in any order.
        $this->connection->statement($this->grammar->compileDropAllForeignKeys());

        foreach ($tables as $table) {
            $this->connection->statement('DROP TABLE '.$this->grammar->wrap($table));
        }
    }

    /**
     * Drop all views from the database.
     *
     * @return void
     *
     * @throws \Illuminate\Database\QueryException
     */
    public function dropAllViews()
    {
        $views = array_column($this->getViews(), 'name');

        // Views can depend on other views, so keep dropping the ones that can be
        // dropped until none are left, or no further progress can be made.
        while ($views) {
            $failed = [];

            foreach ($views as $view) {
                try {
                    $this->connection->statement('DROP VIEW '.$this->grammar->wrap($view));
                } catch (QueryException $e) {
                    $failed[] = $view;
                }
            }

            if (count($failed) === count($views)) {
                throw $e;
            }

            $views = $failed;
        }
    }
}
