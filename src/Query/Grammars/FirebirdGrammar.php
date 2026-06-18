<?php

/**
 * Firebird Query Grammar for Laravel.
 *
 * Firebird SQL version support matrix (from parse.y analysis):
 *
 * LIMIT/OFFSET:
 *   - FIRST n SKIP m .............. FB 1.0+ (Firebird-specific, between SELECT and columns)
 *   - ROWS n TO m ................. FB 1.0+ (Firebird-specific, at end of query)
 *   - OFFSET n ROWS FETCH FIRST .. FB 3.0+ (SQL:2008 standard, at end of query)
 *
 * DML:
 *   - INSERT RETURNING ............ FB 2.0+
 *   - UPDATE OR INSERT ............ FB 2.1+ (upsert)
 *   - MERGE ....................... FB 2.1+ (basic), FB 3.0+ (DELETE in WHEN MATCHED),
 *                                   FB 5.0+ (NOT MATCHED BY SOURCE, RETURNING)
 *   - UPDATE/DELETE RETURNING ..... FB 2.0+
 *   - Multi-row INSERT VALUES .... NOT SUPPORTED (any version)
 *   - DELETE/UPDATE with JOIN ..... NOT SUPPORTED (use subquery or MERGE)
 *   - TRUNCATE TABLE .............. NOT SUPPORTED (use DELETE FROM)
 *
 * LOCKING:
 *   - WITH LOCK ................... FB 1.5+ (single-table SELECT only)
 *   - SKIP LOCKED ................. FB 5.0+
 *   - FOR UPDATE [OF cols] ........ FB 1.0+ (cursor declaration, not same as WITH LOCK)
 *
 * OPERATORS (all versions unless noted):
 *   - CONTAINING .................. case-insensitive substring search
 *   - STARTING WITH ............... prefix match (uses index)
 *   - SIMILAR TO .................. FB 2.5+ (SQL regex)
 *   - IS [NOT] DISTINCT FROM ..... FB 2.0+ (NULL-safe comparison)
 *
 * FUNCTIONS:
 *   - Window functions ............ FB 3.0+ (ROW_NUMBER, RANK, etc.)
 *   - Named windows ............... FB 4.0+
 *   - LAG/LEAD/NTH_VALUE ......... FB 4.0+
 *   - FILTER clause on aggregates . FB 4.0+
 *   - BIN_AND/BIN_OR/BIN_XOR ..... FB 2.1+ (bitwise, function syntax not operator)
 *
 * CTEs:
 *   - WITH ... AS ................. FB 2.1+
 *   - WITH RECURSIVE .............. FB 2.1+
 *
 * JOINS:
 *   - LATERAL derived tables ...... FB 4.0+
 *   - NATURAL JOIN ................ FB 1.0+ (rarely needed from query builder)
 *
 * DIALECT DIFFERENCES:
 *   - Dialect 1: double-quotes = string literals, DATE = TIMESTAMP, no TIME type, no BOOLEAN
 *   - Dialect 3: double-quotes = identifiers (case-sensitive), DATE = date-only, BOOLEAN (FB 3.0+)
 *   - Dialect is a property of the .fdb file, negotiated automatically by client
 *
 * IDENTIFIER LIMITS:
 *   - FB 2.5/3.0: 31 bytes
 *   - FB 4.0+: 63 characters (252 bytes UTF8)
 */

namespace HarryGulliford\Firebird\Query\Grammars;

use HarryGulliford\Firebird\FirebirdConnection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\JoinLateralClause;
use Illuminate\Support\Str;

class FirebirdGrammar extends Grammar
{
    /**
     * Check if the connection supports a given feature.
     *
     * Reads 'server_version' from the connection config.
     * No runtime query — purely config-driven.
     */
    protected function supports(string $feature): bool
    {
        if ($this->connection instanceof FirebirdConnection) {
            return $this->connection->supportsFeature($feature);
        }

        return true; // assume latest if connection type unknown
    }

    /**
     * Check if the connection uses Dialect 1.
     *
     * Dialect 1: double-quotes are string literals, not identifiers.
     * All identifiers must be unquoted (case-insensitive uppercase).
     */
    protected function isDialect1(): bool
    {
        if ($this->connection instanceof FirebirdConnection) {
            return $this->connection->getDialect() === 1;
        }

        return false;
    }

    /**
     * Wrap a value in keyword identifiers.
     *
     * Dialect 1: identifiers are NOT quoted (double-quotes = string literals).
     * Dialect 3: identifiers wrapped in double-quotes (case-sensitive).
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }

        if ($this->isDialect1()) {
            return $value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }

    /**
     * The components that make up a select clause.
     *
     * Firebird requires OFFSET before FETCH FIRST (SQL:2008 syntax, FB 3.0+).
     *
     * @var string[]
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'offset',
        'limit',
        'lock',
    ];

    /**
     * All of the available clause operators.
     *
     * Includes Firebird-specific operators from ParserTokens.h:
     * - containing/not containing: case-insensitive substring (all versions)
     * - starting with: prefix match, index-friendly (all versions)
     * - similar to: SQL regex (FB 2.5+)
     * - is [not] distinct from: NULL-safe comparison (FB 2.0+)
     *
     * @var string[]
     */
    protected $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        '!<', '!>', '~<', '~>', '^<', '^>', '~=', '^=',
        'like', 'not like', 'between', 'not between',
        'containing', 'not containing', 'starting with', 'not starting with',
        'similar to', 'not similar to', 'is distinct from', 'is not distinct from',
    ];

    /**
     * The bitwise operator mapping.
     *
     * Firebird uses function syntax for bitwise operations (FB 2.1+):
     * BIN_AND(), BIN_OR(), BIN_XOR(), BIN_NOT(), BIN_SHL(), BIN_SHR()
     * Standard &, |, ^ operators are NOT supported.
     *
     * @var string[]
     */
    protected $bitwiseOperators = [];

    /**
     * Compile the "limit" portions of the query.
     *
     * Uses SQL:2008 FETCH FIRST syntax (FB 3.0+).
     * For FB 2.5, override in Firebird25Grammar with FIRST/SKIP.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        if (! $this->supports('fetch_first')) {
            // FB 2.5: FIRST/SKIP handled in compileSelect()
            return '';
        }

        return 'fetch first '.(int) $limit.' rows only';
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * Uses SQL:2008 OFFSET ROWS syntax (FB 3.0+).
     * For FB 2.5, override in Firebird25Grammar with FIRST/SKIP.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        if (! $this->supports('fetch_first')) {
            // FB 2.5: FIRST/SKIP handled in compileSelect()
            return '';
        }

        return 'offset '.(int) $offset.' rows';
    }

    /**
     * Compile the random statement into SQL.
     *
     * Firebird uses RAND() (all versions). Seed is ignored.
     *
     * @param  string  $seed
     * @return string
     */
    /**
     * Compile a select query into SQL.
     *
     * On FB 2.5 (server_version < 3), injects FIRST/SKIP after SELECT keyword.
     * On FB 3.0+, uses standard OFFSET/FETCH FIRST at end of query.
     *
     * FB 2.5 syntax: SELECT FIRST 10 SKIP 20 * FROM ...
     * FB 3.0+ syntax: SELECT * FROM ... OFFSET 20 ROWS FETCH FIRST 10 ROWS ONLY
     */
    public function compileSelect(Builder $query)
    {
        $sql = parent::compileSelect($query);

        if (! $this->supports('fetch_first')) {
            $limiter = '';

            if (isset($query->limit)) {
                $limiter .= 'first '.(int) $query->limit.' ';
            }

            if (isset($query->offset)) {
                $limiter .= 'skip '.(int) $query->offset.' ';
            }

            if ($limiter) {
                $sql = preg_replace('/^select /i', 'select '.$limiter, $sql);
            }
        }

        return $sql;
    }

    public function compileRandom($seed)
    {
        return 'rand()';
    }

    /**
     * Wrap a union subquery in parentheses.
     *
     * Firebird does not support bare parenthesized subqueries in UNION;
     * requires wrapping as derived table: SELECT * FROM (subquery).
     *
     * @param  string  $sql
     * @return string
     */
    protected function wrapUnion($sql)
    {
        return 'select * from ('.$sql.')';
    }

    /**
     * Compile the "union" queries attached to the main query.
     *
     * Firebird requires OFFSET before FETCH FIRST in union queries.
     * Note: INTERSECT and EXCEPT are NOT supported in any Firebird version.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    protected function compileUnions(Builder $query)
    {
        $sql = '';

        foreach ($query->unions as $union) {
            $sql .= $this->compileUnion($union);
        }

        if (! empty($query->unionOrders)) {
            $sql .= ' '.$this->compileOrders($query, $query->unionOrders);
        }

        if (isset($query->unionOffset)) {
            $sql .= ' '.$this->compileOffset($query, $query->unionOffset);
        }

        if (isset($query->unionLimit)) {
            $sql .= ' '.$this->compileLimit($query, $query->unionLimit);
        }

        return ltrim($sql);
    }

    /**
     * Compile an exists statement into SQL.
     *
     * Firebird requires FROM RDB$DATABASE for scalar SELECT statements.
     * RDB$DATABASE is a system table that always contains exactly one row.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    public function compileExists(Builder $query)
    {
        if (! $this->supports('exists_function')) {
            // FB 2.5: EXISTS() not usable as scalar function.
            // Use CASE WHEN EXISTS(...) THEN 1 ELSE 0 END instead.
            return sprintf(
                'select case when exists(%s) then 1 else 0 end as "exists" from rdb$database',
                $this->compileSelect($query)
            );
        }

        return sprintf('select exists(%s) as "exists" from rdb$database',
            $this->compileSelect($query));
    }

    /**
     * Compile a date based where clause.
     *
     * Firebird uses CAST for date/time extraction and EXTRACT for components.
     * Note: In Dialect 1, DATE type includes time (acts as TIMESTAMP).
     * In Dialect 3, DATE is date-only.
     *
     * @param  string  $type
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $condition = ($type === 'date' || $type === 'time')
            ? sprintf('cast(%s as %s)', $this->wrap($where['column']), $type)
            : sprintf('extract(%s from %s)', $type, $this->wrap($where['column']));

        return $condition.' '.$where['operator'].' '.$this->parameter($where['value']);
    }

    /**
     * Compile the select clause for a stored procedure.
     *
     * Firebird selectable procedures: SELECT * FROM procedure(args)
     * Executable procedures: EXECUTE PROCEDURE name(args)
     * Both available since FB 1.0.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $procedure
     * @param  array  $values
     * @return string
     */
    public function compileProcedure(Builder $query, $procedure, array $values = [])
    {
        return $this->wrap($procedure).' ('.$this->parameterize($values).')';
    }

    /**
     * Compile an aggregated select clause.
     *
     * Firebird returns column names in UPPERCASE by default. Wrapping
     * "aggregate" in double quotes forces lowercase, which the Laravel
     * paginator expects.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        $sql = parent::compileAggregate($query, $aggregate);

        if ($this->isDialect1()) {
            // Dialect 1: no quoting. Firebird returns AGGREGATE (uppercase).
            // Laravel paginator accesses ->aggregate which works because
            // PHP object property access is case-sensitive but PDO returns
            // the case as-is from the alias. We use AGGREGATE without quotes.
            return $sql;
        }

        // Dialect 3: wrap in double quotes to force lowercase result column name.
        return Str::replaceLast('as aggregate', 'as "aggregate"', $sql);
    }

    /**
     * Compile a "lateral join" clause.
     *
     * LATERAL derived tables: FB 4.0+ only (SQL:2011).
     * Will produce an error on FB 2.5/3.0.
     *
     * @param  \Illuminate\Database\Query\JoinLateralClause  $join
     * @param  string  $expression
     * @return string
     */
    public function compileJoinLateral(JoinLateralClause $join, string $expression): string
    {
        if (! $this->supports('lateral')) {
            throw new \RuntimeException(
                'LATERAL joins require Firebird 4.0+. Set server_version >= 4 in your database config.'
            );
        }

        return trim("{$join->type} join lateral {$expression} on true");
    }

    /**
     * Compile an insert and get ID statement into SQL.
     *
     * Uses RETURNING clause (FB 2.0+) instead of lastInsertId()
     * which is not supported by PDO_Firebird.
     * Same pattern as PostgreSQL.
     *
     * FB 4.0+ supports RETURNING *.
     * FB 5.0+ supports RETURNING with multiple rows from INSERT...SELECT.
     *
     * @param  Builder  $query
     * @param  array  $values
     * @param  string|null  $sequence
     * @return string
     */
    public function compileInsertGetId(Builder $query, $values, $sequence)
    {
        return $this->compileInsert($query, $values).' returning '.$this->wrap($sequence ?: 'id');
    }

    /**
     * Compile an insert statement into SQL.
     *
     * Firebird does NOT support multi-row INSERT VALUES (), (), () syntax
     * (confirmed in parse.y — no grammar rule for multiple value lists).
     * For multiple rows, generate separate INSERT statements.
     *
     * Workaround alternatives (not used here):
     * - EXECUTE BLOCK with multiple INSERTs
     * - INSERT INTO ... SELECT ... UNION ALL SELECT ... FROM RDB$DATABASE
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        if (empty($values)) {
            $table = $this->wrapTable($query->from);
            return "insert into {$table} default values";
        }

        // If not a nested array (single record as associative array), delegate to parent
        if (! is_array(reset($values))) {
            return parent::compileInsert($query, [$values]);
        }

        // Single row — delegate to parent
        if (count($values) === 1) {
            return parent::compileInsert($query, $values);
        }

        // Multiple rows — Firebird does not support multi-row VALUES (), ()
        $table = $this->wrapTable($query->from);
        $columns = $this->columnize(array_keys(reset($values)));

        $sql = [];
        foreach ($values as $record) {
            $sql[] = 'insert into '.$table.' ('.$columns.') values ('.$this->parameterize($record).')';
        }

        return implode('; ', $sql);
    }

    /**
     * Compile a truncate table statement into SQL.
     *
     * Firebird has NO TRUNCATE TABLE statement (confirmed in parse.y —
     * only local temp table truncation exists in PSQL context).
     * Uses DELETE FROM as workaround (all versions).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return ['delete from '.$this->wrapTable($query->from) => []];
    }

    /**
     * Compile the lock into SQL.
     *
     * Firebird locking (from parse.y):
     * - WITH LOCK: pessimistic row-level locking (FB 1.5+)
     *   Only works on single-table, top-level SELECT.
     *   NOT available with: JOINs, DISTINCT, GROUP BY, UNION, subqueries, aggregates.
     * - SKIP LOCKED: skip rows locked by other transactions (FB 5.0+)
     * - FOR UPDATE [OF cols]: cursor declaration, different from WITH LOCK
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  bool|string  $value
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        if (is_string($value)) {
            return $value;
        }

        return $value ? 'with lock' : '';
    }

    /**
     * Compile an "upsert" statement into SQL.
     *
     * Uses Firebird's UPDATE OR INSERT syntax (FB 2.1+, from parse.y:7457-7475):
     *   UPDATE OR INSERT INTO table (cols) VALUES (vals) [MATCHING (cols)] [RETURNING ...]
     *
     * Without MATCHING: matches on PRIMARY KEY (uses IS NOT DISTINCT for NULL comparison).
     * The $update parameter is ignored — Firebird's UPDATE OR INSERT updates ALL columns.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @param  array  $uniqueBy
     * @param  array  $update
     * @return string
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update)
    {
        $table = $this->wrapTable($query->from);
        $columns = $this->columnize(array_keys(reset($values)));
        $matching = $this->columnize($uniqueBy);

        $sql = [];
        foreach ($values as $record) {
            $sql[] = 'update or insert into '.$table
                .' ('.$columns.') values ('.$this->parameterize($record).')'
                .' matching ('.$matching.')';
        }

        return implode('; ', $sql);
    }

    /**
     * Compile an "insert or ignore" statement into SQL.
     *
     * Uses UPDATE OR INSERT without MATCHING clause (FB 2.1+).
     * Defaults to matching on PRIMARY KEY.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        $table = $this->wrapTable($query->from);
        $columns = $this->columnize(array_keys(reset($values)));

        $sql = [];
        foreach ($values as $record) {
            $sql[] = 'update or insert into '.$table
                .' ('.$columns.') values ('.$this->parameterize($record).')';
        }

        return implode('; ', $sql);
    }

    /**
     * Compile a MERGE statement.
     *
     * MERGE syntax (from parse.y:7285-7362):
     *   FB 2.1+: basic MERGE INTO ... USING ... ON ... WHEN MATCHED/NOT MATCHED
     *   FB 3.0+: DELETE in WHEN MATCHED clause
     *   FB 5.0+: WHEN NOT MATCHED BY SOURCE, RETURNING, PLAN, ORDER BY
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $table
     * @param  string  $source
     * @param  string  $onCondition
     * @param  string|null  $whenMatched
     * @param  string|null  $whenNotMatched
     * @return string
     */
    public function compileMerge(Builder $query, string $table, string $source, string $onCondition, ?string $whenMatched = null, ?string $whenNotMatched = null)
    {
        $sql = 'merge into '.$this->wrapTable($table).' using '.$source.' on '.$onCondition;

        if ($whenMatched) {
            $sql .= ' when matched then '.$whenMatched;
        }

        if ($whenNotMatched) {
            $sql .= ' when not matched then '.$whenNotMatched;
        }

        return $sql;
    }

    /**
     * Compile an update statement with RETURNING clause.
     *
     * UPDATE ... RETURNING is supported since FB 2.0 (parse.y:7411-7451).
     * Useful for getting modified values without a second query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @param  array|string  $returning
     * @return string
     */
    public function compileUpdateReturning(Builder $query, array $values, $returning = '*')
    {
        $sql = $this->compileUpdate($query, $values);

        if (is_array($returning)) {
            $returning = $this->columnize($returning);
        }

        return $sql.' returning '.$returning;
    }

    /**
     * Compile a delete statement with RETURNING clause.
     *
     * DELETE ... RETURNING is supported since FB 2.0 (parse.y:7368-7406).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array|string  $returning
     * @return string
     */
    public function compileDeleteReturning(Builder $query, $returning = '*')
    {
        $sql = $this->compileDelete($query);

        if (is_array($returning)) {
            $returning = $this->columnize($returning);
        }

        return $sql.' returning '.$returning;
    }

    /**
     * Compile a bitwise where clause.
     *
     * Firebird uses function syntax (FB 2.1+), not operators:
     *   BIN_AND(a, b), BIN_OR(a, b), BIN_XOR(a, b)
     *   BIN_NOT(a), BIN_SHL(a, n), BIN_SHR(a, n)
     *
     * Standard &, |, ^ operators are NOT supported in Firebird SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereBitwise(Builder $query, $where)
    {
        $column = $this->wrap($where['column']);
        $value = $this->parameter($where['value']);

        $function = match ($where['operator']) {
            '&' => 'bin_and',
            '|' => 'bin_or',
            '^' => 'bin_xor',
            default => throw new \RuntimeException("Unsupported bitwise operator: {$where['operator']}"),
        };

        return "{$function}({$column}, {$value}) > 0";
    }
}
