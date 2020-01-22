<?php

namespace Firebird\Query;

use Illuminate\Database\Query\Builder as QueryBuilder;

class Builder extends QueryBuilder
{
    /**
     * Add a from stored procedure clause to the query builder.
     *
     * @param string $procedure
     * @param array $values
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function fromProcedure(string $procedure, array $values = [])
    {
        $compiledProcedure = $this->grammar->compileProcedure($this, $procedure, $values);

        $this->fromRaw($compiledProcedure, array_values($values));

        return $this;
    }

    /**
     * Insert or update a record matching the attributes, and fill it with values.
     * This feature dont override the updateOrInsert from Eloquent ORM.
     *
     * @param  array  $attributes
     * @param  array  $values
     * @return bool
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        if (! $this->where($attributes)->first()) {
            return $this->insert(array_merge($attributes, $values));
        }

        return (bool) $this->take(1)->update($values);
    }
}
