<?php

/**
 * Firebird Query Processor.
 *
 * Processes raw result sets from Firebird system tables (RDB$*) into
 * the normalized format that Laravel's Schema Builder expects.
 *
 * Firebird internal field type codes (from src/jrd/dsc.h, RDB$FIELDS.RDB$FIELD_TYPE):
 *   7  = SMALLINT (16-bit), with scale → NUMERIC/DECIMAL
 *   8  = INTEGER (32-bit), with scale → NUMERIC/DECIMAL
 *   10 = FLOAT (32-bit IEEE 754)
 *   12 = DATE (Dialect 3: date-only; Dialect 1: date+time like TIMESTAMP)
 *   13 = TIME (Dialect 3 only; does not exist in Dialect 1)
 *   14 = CHAR (fixed-length)
 *   16 = BIGINT (64-bit), with scale → NUMERIC/DECIMAL, subtype 1 = NUMERIC, 2 = DECIMAL
 *   23 = BOOLEAN (FB 3.0+ only)
 *   24 = DECFLOAT(16) (FB 4.0+)
 *   25 = DECFLOAT(34) (FB 4.0+)
 *   26 = INT128 (FB 4.0+)
 *   27 = DOUBLE PRECISION (64-bit IEEE 754)
 *   28 = TIME WITH TIME ZONE (FB 4.0+)
 *   29 = TIMESTAMP WITH TIME ZONE (FB 4.0+)
 *   35 = TIMESTAMP (date+time without timezone)
 *   37 = VARCHAR (variable-length)
 *   261 = BLOB (subtype 0 = binary, 1 = text)
 */

namespace HarryGulliford\Firebird\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;

class FirebirdProcessor extends Processor
{
    /**
     * Process an "insert get ID" query.
     *
     * PDO_Firebird does not support lastInsertId(). Instead, the grammar
     * appends RETURNING to the INSERT, and we execute it as a SELECT.
     * Same pattern used by PostgreSQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $result = $query->getConnection()->selectFromWriteConnection($sql, $values)[0];

        $sequence = $sequence ?: 'id';

        $id = is_object($result) ? $result->{$sequence} : $result[$sequence];

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Process the results of a columns query.
     *
     * Transforms Firebird RDB$RELATION_FIELDS + RDB$FIELDS metadata into
     * Laravel's expected column format for Schema::getColumns().
     *
     * @param  array  $results
     * @return array
     */
    public function processColumns($results)
    {
        return array_map(function ($result) {
            $result = (object) $result;

            $type = $this->mapFieldType(
                (int) ($result->field_type ?? 0),
                (int) ($result->field_sub_type ?? 0),
                (int) ($result->field_length ?? 0),
                (int) ($result->field_precision ?? 0),
                (int) ($result->field_scale ?? 0)
            );

            return [
                'name' => trim($result->name ?? ''),
                'type_name' => $type,
                'type' => $type,
                'collation' => isset($result->collation_name) ? trim($result->collation_name) : null,
                'nullable' => empty($result->null_flag),
                'default' => isset($result->default_source) ? trim($result->default_source) : null,
                'auto_increment' => ! empty($result->identity_type),
                'comment' => isset($result->description) ? trim($result->description) : null,
                'generation' => isset($result->computed_source)
                    ? ['type' => 'always', 'expression' => trim($result->computed_source)]
                    : null,
            ];
        }, $results);
    }

    /**
     * Process the results of an indexes query.
     *
     * Transforms Firebird RDB$INDICES + RDB$INDEX_SEGMENTS metadata.
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
                'columns' => array_map('trim', explode(',', strtolower(trim($result->columns ?? '')))),
                'type' => 'btree',
                'unique' => ! empty($result->unique_flag),
                'primary' => ! empty($result->is_primary),
            ];
        }, $results);
    }

    /**
     * Process the results of a foreign keys query.
     *
     * Transforms Firebird RDB$REF_CONSTRAINTS + RDB$RELATION_CONSTRAINTS metadata.
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
                'columns' => array_map('trim', explode(',', strtolower(trim($result->columns ?? '')))),
                'foreign_schema' => null, // Firebird has no schema concept
                'foreign_table' => strtolower(trim($result->foreign_table ?? '')),
                'foreign_columns' => array_map('trim', explode(',', strtolower(trim($result->foreign_columns ?? '')))),
                'on_update' => strtolower(trim($result->update_rule ?? 'NO ACTION')),
                'on_delete' => strtolower(trim($result->delete_rule ?? 'NO ACTION')),
            ];
        }, $results);
    }

    /**
     * Map Firebird internal field type codes to human-readable type names.
     *
     * Type codes from jrd/dsc.h and src/dsql/parse.y.
     * Scale/subtype determine if an integer type is actually NUMERIC/DECIMAL.
     *
     * Dialect 1 note: type 12 (DATE) acts as TIMESTAMP in Dialect 1.
     * Type 13 (TIME) does not exist in Dialect 1.
     *
     * @param  int  $type     RDB$FIELD_TYPE
     * @param  int  $subType  RDB$FIELD_SUB_TYPE (0=binary, 1=text/numeric, 2=decimal)
     * @param  int  $length   RDB$FIELD_LENGTH (bytes)
     * @param  int  $precision RDB$FIELD_PRECISION
     * @param  int  $scale    RDB$FIELD_SCALE (negative = decimal places)
     * @return string
     */
    protected function mapFieldType(int $type, int $subType, int $length, int $precision, int $scale): string
    {
        return match ($type) {
            // Integer types — with negative scale become NUMERIC/DECIMAL
            7 => $scale < 0 ? 'numeric('.$precision.', '.abs($scale).')' : 'smallint',
            8 => $scale < 0 ? 'numeric('.$precision.', '.abs($scale).')' : 'integer',
            16 => match (true) {
                $scale < 0 => ($subType === 2 ? 'decimal' : 'numeric').'('.$precision.', '.abs($scale).')',
                $subType === 1 => 'numeric('.$precision.', 0)',
                default => 'bigint',
            },

            // Floating point
            10 => 'float',
            27 => 'double precision',

            // Date/time — type 12 is DATE in Dialect 3, TIMESTAMP in Dialect 1
            12 => 'date',
            13 => 'time',
            35 => 'timestamp',

            // String types
            14 => 'char('.$length.')',
            37 => 'varchar('.$length.')',

            // Boolean (FB 3.0+)
            23 => 'boolean',

            // DECFLOAT (FB 4.0+)
            24 => 'decfloat(16)',
            25 => 'decfloat(34)',

            // INT128 (FB 4.0+) — with scale becomes high-precision NUMERIC
            26 => $scale < 0 ? 'numeric('.$precision.', '.abs($scale).')' : 'int128',

            // Time zone types (FB 4.0+)
            28 => 'time with time zone',
            29 => 'timestamp with time zone',

            // BLOBs
            261 => $subType === 1 ? 'blob sub_type text' : 'blob sub_type binary',

            default => 'unknown('.$type.')',
        };
    }
}
