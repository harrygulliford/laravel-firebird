<?php

namespace HarryGulliford\Firebird\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\JoinLateralClause;
use Illuminate\Support\Str;

class FirebirdGrammar extends Grammar
{
    /**
     * The components that make up a select clause.
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
     * @var array
     *
     * @link https://firebirdsql.org/file/documentation/chunk/en/refdocs/fblangref40/fblangref40-commons.html#fblangref40-commons-compar
     */
    protected $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        '!<', '!>', '~<', '~>', '^<', '^>', '~=', '^=',
        'like', 'not like', 'between', 'not between',
        'containing', 'not containing', 'starting with', 'not starting with',
        'similar to', 'not similar to', 'is distinct from', 'is not distinct from',
    ];

    /**
     * Compile the "select *" portion of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $columns
     * @return string|null
     */
    protected function compileColumns(Builder $query, $columns)
    {
        // See superclass.
        if (! is_null($query->aggregate)) {
            return;
        }

        $select = 'select ';

        // Before Firebird v3, the syntax used to limit and offset rows is
        // "select first [int] skip [int] * from table". Laravel's query builder
        // doesn't natively support inserting components between "select" and
        // the column names, so compile the limit and offset here.

        if (isset($query->limit) && $usesLegacyLimitAndOffset ??= $this->usesLegacyLimitAndOffset()) {
            $select .= $this->compileLegacyLimit($query, $query->limit).' ';
        }

        if (isset($query->offset) && $usesLegacyLimitAndOffset ??= $this->usesLegacyLimitAndOffset()) {
            $select .= $this->compileLegacyOffset($query, $query->offset).' ';
        }

        if ($query->distinct) {
            $select .= 'distinct ';
        }

        return $select.$this->columnize($columns);
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        if ($this->usesLegacyLimitAndOffset()) {
            return;
        }

        return 'fetch first '.(int) $limit.' rows only';
    }

    /**
     * Compile the "limit" portions of the query for legacy versions of Firebird.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLegacyLimit(Builder $query, $limit)
    {
        return 'first '.(int) $limit;
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        if ($this->usesLegacyLimitAndOffset()) {
            return;
        }

        return 'offset '.(int) $offset.' rows';
    }

    /**
     * Compile the "offset" portions of the query for legacy versions of Firebird.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileLegacyOffset(Builder $query, $offset)
    {
        return 'skip '.(int) $offset;
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param  string  $seed
     * @return string
     */
    public function compileRandom($seed)
    {
        return 'RAND()';
    }

    /**
     * Wrap a union subquery in parentheses.
     *
     * @param  string  $sql
     * @return string
     */
    protected function wrapUnion($sql)
    {
        return $sql;
    }

    /**
     * Compile a date based where clause.
     *
     * @param  string  $type
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $condition = ($type === 'date' || $type === 'time')
            ? 'cast('.$this->wrap($where['column']).' as '.$type.') '
            : 'extract('.$type.' from '.$this->wrap($where['column']).') ';

        $condition .= $where['operator'].' '.$this->parameter($where['value']);

        return $condition;
    }

    /**
     * Compile the select clause for a stored procedure.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $procedure
     * @param  array  $values
     * @return string
     */
    public function compileProcedure(Builder $query, $procedure, array $values = null)
    {
        $procedure = $this->wrap($procedure);

        return $procedure.' ('.$this->parameterize($values).')';
    }

    /**
     * Compile an aggregated select clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        // Wrap `aggregate` in double quotes to ensure the resultset returns the
        // column name as a lowercase string. This resolves compatibility with
        // the framework's paginator.
        return Str::replaceLast(
            'as aggregate', 'as "aggregate"', parent::compileAggregate($query, $aggregate)
        );
    }

    /**
     * Compile a "lateral join" clause.
     *
     * @param  \Illuminate\Database\Query\JoinLateralClause  $join
     * @param  string  $expression
     * @return string
     */
    public function compileJoinLateral(JoinLateralClause $join, string $expression): string
    {
        return trim("{$join->type} join lateral {$expression} on true");
    }

    /**
     * Determine if the database uses the legacy limit and offset syntax.
     *
     * @return bool
     */
    protected function usesLegacyLimitAndOffset(): bool
    {
        return version_compare($this->connection->getServerVersion(), '3.0.0', '<');
    }
}
