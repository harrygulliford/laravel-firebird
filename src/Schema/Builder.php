<?php

namespace HarryGulliford\Firebird\Schema;

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
     */
    public function dropAllViews()
    {
        $views = array_column($this->getViews(), 'name');

        $dependencies = [];

        foreach ($this->connection->selectFromWriteConnection($this->grammar->compileViewDependencies()) as $row) {
            $dependencies[$row->view][] = $row->depends_on;
        }

        // Views can select from other views, so drop the views that nothing
        // remaining depends on first. Views can't have circular dependencies.
        while ($views) {
            $dependedOn = array_merge(...array_map(fn ($view) => $dependencies[$view] ?? [], $views));

            foreach (array_diff($views, $dependedOn) as $view) {
                $this->connection->statement('DROP VIEW '.$this->grammar->wrap($view));
            }

            $views = array_values(array_intersect($views, $dependedOn));
        }
    }
}
