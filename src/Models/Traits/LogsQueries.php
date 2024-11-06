<?php

namespace FilDonadoni\MongoTelescope\Models\Traits;

use FilDonadoni\MongoTelescope\MongoDB\Builder;

trait LogsQueries
{
    /**
     * Create a new Eloquent builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \FilDonadoni\MongoTelescope\MongoDB\Builder
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }
}
