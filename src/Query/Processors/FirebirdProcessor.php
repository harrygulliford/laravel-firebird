<?php

namespace Firebird\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;

class FirebirdProcessor extends Processor
{
    /**
     * Process an "execute function" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array   $values
     *
     * @return mixed
     */
    public function processExecuteFunction(Builder $query, $sql, $values)
    {
        $result = $query->getConnection()->selectOne($sql, $values);

        return $result['VAL'];
    }
}
