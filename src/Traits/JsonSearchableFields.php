<?php

namespace Model\Searchable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait JsonSearchableFields
{
    /**
     * Apply the search query to the model's jsonSearchable fields.
     *
     * @param Builder $query
     * @param string $search_term
     * @return void
     */
    protected function applyJsonSearchableFields(Builder $query, string $search_term): void
    {
        $jsonSearchable = $this->getStaticProperty('json_searchable');

        foreach ($jsonSearchable as $field => $keys) {
            $this->processJsonSearch($query, $field, $keys, $search_term);
        }
    }
}
