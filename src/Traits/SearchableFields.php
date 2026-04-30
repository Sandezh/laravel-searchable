<?php

namespace Model\Searchable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait SearchableFields
{
    /**
     * Apply the search query to the model's direct searchable fields.
     *
     * @param Builder $query
     * @param string $search_term
     * @return void
     */
    protected function applySearchableFields(Builder $query, string $search_term): void
    {
        $searchable = $this->getStaticProperty('searchable');
        $jsonSearchableFields = $this->getNormalizedJsonFields($this->getStaticProperty('json_searchable'));

        foreach ($searchable as $field) {
            if (in_array($field, $jsonSearchableFields)) {
                continue;
            }

            $query->orWhere(fn(Builder $q) => $this->applyFieldConstraint($q, $field, $search_term));
        }
    }
}
