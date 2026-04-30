<?php

namespace Searchkit\Searchable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait JsonRelationSearchableFields
{
    /**
     * Apply the search query to the model's jsonRelationSearchable fields.
     *
     * @param Builder $query
     * @param string $search_term
     * @return void
     */
    protected function applyJsonRelationSearchableFields(Builder $query, string $search_term): void
    {
        $jsonRelationSearchable = $this->getStaticProperty('json_relation_searchable');

        foreach ($jsonRelationSearchable as $relation => $json_columns) {
            $query->orWhereHas($relation, function (Builder $q) use ($json_columns, $search_term) {
                $q->where(function (Builder $innerQ) use ($json_columns, $search_term) {
                    foreach ((array) $json_columns as $field => $keys) {
                        $this->processJsonSearch($innerQ, $field, $keys, $search_term);
                    }
                });
            });
        }
    }
}
