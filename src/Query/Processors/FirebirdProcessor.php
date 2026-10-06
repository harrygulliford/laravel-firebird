<?php

namespace HarryGulliford\Firebird\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;

class FirebirdProcessor extends Processor
{
    /**
     * Process an "insert get ID" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        // The pdo_firebird driver does not support `lastInsertId()`. Perform
        // the insert operation in a way that returns the id.

        $result = $query->getConnection()->selectFromWriteConnection($sql, $values)[0];

        $sequence = $sequence ?: 'id';

        $id = is_object($result) ? $result->{$sequence} : $result[$sequence];

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Process the results of a columns query.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{name: string, type: string, type_name: string, nullable: bool, default: mixed, auto_increment: bool, comment: string|null, generation: array{type: string, expression: string|null}|null}>
     */
    public function processColumns($results)
    {
        return array_map(function ($result) {
            $result = (object) $result;

            [$typeName, $type] = $this->columnType($result);

            $computed = $result->computed !== null ? trim($result->computed) : null;

            return [
                'name' => $result->name,
                'type_name' => $typeName,
                'type' => $type,
                'collation' => $result->collation,
                'nullable' => ! $result->not_null,
                'default' => $result->default !== null
                    ? preg_replace('/^\s*default\s+/i', '', trim($result->default))
                    : null,
                'auto_increment' => $result->identity_type !== null,
                'comment' => $result->comment,
                'generation' => $computed !== null ? [
                    'type' => 'virtual',
                    'expression' => preg_replace('/^\((.*)\)$/s', '$1', $computed),
                ] : null,
            ];
        }, $results);
    }

    /**
     * Get the type name and full type definition of a column.
     *
     * @param  object  $column
     * @return array{string, string}
     */
    protected function columnType($column)
    {
        $precision = (int) $column->precision;
        $scale = -(int) $column->scale;
        $length = (int) $column->length;
        $octets = $column->charset === 'OCTETS';

        // Integer types store numeric/decimal columns when they have a sub type.
        if (in_array($column->field_type, [7, 8, 16, 26]) && in_array($column->field_sub_type, [1, 2])) {
            $name = $column->field_sub_type == 1 ? 'numeric' : 'decimal';

            return [$name, "{$name}({$precision},{$scale})"];
        }

        $name = match ((int) $column->field_type) {
            7 => 'smallint',
            8 => 'integer',
            10 => 'float',
            12 => 'date',
            13 => 'time',
            14 => $octets ? 'binary' : 'char',
            16 => 'bigint',
            23 => 'boolean',
            24, 25 => 'decfloat',
            26 => 'int128',
            27 => 'double precision',
            28 => 'time with time zone',
            29 => 'timestamp with time zone',
            35 => 'timestamp',
            37 => $octets ? 'varbinary' : 'varchar',
            261 => 'blob',
            default => 'unknown',
        };

        return [$name, match ($name) {
            'char', 'varchar', 'binary', 'varbinary' => "{$name}({$length})",
            'decfloat' => $column->field_type == 24 ? 'decfloat(16)' : 'decfloat(34)',
            'blob' => $column->field_sub_type == 1 ? 'blob sub_type text' : 'blob sub_type binary',
            default => $name,
        }];
    }

    /**
     * Process the results of an indexes query.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{name: string, columns: list<string>, type: string|null, unique: bool, primary: bool}>
     */
    public function processIndexes($results)
    {
        return array_map(function ($rows) {
            $result = $rows[0];

            return [
                'name' => strtolower($result->name),
                'columns' => array_values(array_filter(array_column($rows, 'column'))),
                'type' => null,
                'unique' => (bool) $result->unique,
                'primary' => $result->constraint_type === 'PRIMARY KEY',
            ];
        }, $this->groupByName($results));
    }

    /**
     * Process the results of a foreign keys query.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{name: string, columns: list<string>, foreign_schema: string|null, foreign_table: string, foreign_columns: list<string>, on_update: string, on_delete: string}>
     */
    public function processForeignKeys($results)
    {
        // Firebird stores "NO ACTION" as "RESTRICT"; they behave the same.
        $rule = fn ($rule) => $rule === 'RESTRICT' ? 'no action' : strtolower($rule);

        return array_map(function ($rows) use ($rule) {
            $result = $rows[0];

            return [
                'name' => $result->name,
                'columns' => array_column($rows, 'column'),
                'foreign_schema' => null,
                'foreign_table' => $result->foreign_table,
                'foreign_columns' => array_column($rows, 'foreign_column'),
                'on_update' => $rule($result->on_update),
                'on_delete' => $rule($result->on_delete),
            ];
        }, $this->groupByName($results));
    }

    /**
     * Group per-column result rows by their "name".
     *
     * @param  list<array<string, mixed>|object>  $results
     * @return list<list<object>>
     */
    protected function groupByName($results)
    {
        $groups = [];

        foreach ($results as $result) {
            $result = (object) $result;

            $groups[$result->name][] = $result;
        }

        return array_values($groups);
    }
}
