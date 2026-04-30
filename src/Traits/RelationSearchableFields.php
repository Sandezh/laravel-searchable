<?php

namespace Model\Searchable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait RelationSearchableFields
{
    /**
     * Apply the search query to the model's relationSearchable fields.
     *
     * @param Builder $query
     * @param string $search_term
     * @return void
     */
    protected function applyRelationSearchableFields(Builder $query, string $search_term): void
    {
        $relationSearchable = $this->getStaticProperty('relation_searchable');
        $jsonRelationSearchable = $this->getStaticProperty('json_relation_searchable');

        foreach ($relationSearchable as $relation => $columns) {
            $jsonRelationFields = $this->getNormalizedJsonFields($jsonRelationSearchable[$relation] ?? []);

            foreach ((array) $columns as $column) {
                if (in_array($column, $jsonRelationFields)) {
                    continue;
                }

                $query->orWhereHas($relation, fn(Builder $q) => $this->applyFieldConstraint($q, $column, $search_term));
            }
        }
    }
}
