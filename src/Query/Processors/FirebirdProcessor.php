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
     * @param  array  $results
     * @return array
     */
    public function processColumns($results)
    {
        return array_map(function ($result) {
            $result = (object) $result;
            $type = $this->mapFieldType((int) ($result->field_type ?? 0), (int) ($result->field_sub_type ?? 0), (int) ($result->field_length ?? 0), (int) ($result->field_precision ?? 0), (int) ($result->field_scale ?? 0));
            return [
                'name' => trim($result->name ?? ''),
                'type_name' => $type,
                'type' => $type,
                'collation' => isset($result->collation_name) ? trim($result->collation_name) : null,
                'nullable' => empty($result->null_flag),
                'default' => isset($result->default_source) ? trim($result->default_source) : null,
                'auto_increment' => !empty($result->identity_type),
                'comment' => isset($result->description) ? trim($result->description) : null,
                'generation' => isset($result->computed_source) ? ['type' => 'always', 'expression' => trim($result->computed_source)] : null,
            ];
        }, $results);
    }

    /**
     * Process the results of an indexes query.
     *
     * @param  array  $results
     * @return array
     */
    public function processIndexes($results)
    {
        return array_map(function ($result) {
            $result = (object) $result;
            return [
                'name' => strtolower(trim($result->name ?? '')),
                'columns' => explode(',', strtolower(trim($result->columns ?? ''))),
                'type' => 'btree',
                'unique' => !empty($result->unique_flag),
                'primary' => !empty($result->is_primary),
            ];
        }, $results);
    }

    /**
     * Process the results of a foreign keys query.
     *
     * @param  array  $results
     * @return array
     */
    public function processForeignKeys($results)
    {
        return array_map(function ($result) {
            $result = (object) $result;
            return [
                'name' => strtolower(trim($result->name ?? '')),
                'columns' => explode(',', strtolower(trim($result->columns ?? ''))),
                'foreign_schema' => null,
                'foreign_table' => strtolower(trim($result->foreign_table ?? '')),
                'foreign_columns' => explode(',', strtolower(trim($result->foreign_columns ?? ''))),
                'on_update' => strtolower(trim($result->update_rule ?? 'NO ACTION')),
                'on_delete' => strtolower(trim($result->delete_rule ?? 'NO ACTION')),
            ];
        }, $results);
    }

    /**
     * Map Firebird internal field type codes to human-readable type names.
     *
     * @param  int  $type
     * @param  int  $subType
     * @param  int  $length
     * @param  int  $precision
     * @param  int  $scale
     * @return string
     */
    protected function mapFieldType(int $type, int $subType, int $length, int $precision, int $scale): string
    {
        return match ($type) {
            7 => $scale < 0 ? "numeric({$precision}, ".abs($scale).")" : 'smallint',
            8 => $scale < 0 ? "numeric({$precision}, ".abs($scale).")" : 'integer',
            10 => 'float',
            12 => 'date',
            13 => 'time',
            14 => "char({$length})",
            16 => $scale < 0 ? "numeric({$precision}, ".abs($scale).")" : ($subType === 1 ? "numeric({$precision}, ".abs($scale).")" : 'bigint'),
            23 => 'boolean',
            27 => 'double precision',
            35 => 'timestamp',
            37 => "varchar({$length})",
            261 => $subType === 1 ? 'blob sub_type text' : 'blob sub_type binary',
            default => 'unknown',
        };
    }
}
