<?php

namespace HarryGulliford\Firebird\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
    /**
     * Set the stored procedure which the query is targeting.
     *
     * @param  string  $procedure
     * @param  array  $bindings
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function procedure(string $procedure, array $bindings = [])
    {
        $expression = $this->grammar->compileProcedure($this, $procedure, $bindings);

        $this->fromRaw($expression, $this->cleanBindings($bindings));

        return $this;
    }

    /**
     * Alias to set the stored procedure which the query is targeting.
     *
     * @param  string  $procedure
     * @param  array  $bindings
     * @return \Illuminate\Database\Query\Builder|static
     *
     * @deprecated This method is deprecated and will be removed in a future
     * release. Use the `procedure` method instead.
     */
    public function fromProcedure(string $procedure, array $bindings = [])
    {
        return $this->procedure($procedure, $bindings);
    }
}
