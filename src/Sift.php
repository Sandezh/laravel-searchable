<?php

namespace Searchkit\Searchable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Searchkit\Searchable\Traits\Core;
use Searchkit\Searchable\Traits\SearchableFields;
use Searchkit\Searchable\Traits\RelationSearchableFields;
use Searchkit\Searchable\Traits\JsonSearchableFields;
use Searchkit\Searchable\Traits\JsonRelationSearchableFields;

/**
 * @mixin Model
 */
trait Sift
{
    use Core;
    use SearchableFields;
    use RelationSearchableFields;
    use JsonSearchableFields;
    use JsonRelationSearchableFields;

    /**
     * Apply a search query to the model based on its searchable fields & relationSearchable fields.
     *
     * @param Builder $query
     * @param string|null $search_term
     * @return Builder
     */
    public function scopeSearch(Builder $query, ?string $search_term): Builder
    {
        $this->initializeSiftTrait();

        $searchable = $this->getStaticProperty('searchable');
        $relationSearchable = $this->getStaticProperty('relation_searchable');
        $jsonSearchable = $this->getStaticProperty('json_searchable');
        $jsonRelationSearchable = $this->getStaticProperty('json_relation_searchable');

        if (empty($searchable) && empty($relationSearchable) && empty($jsonSearchable) && empty($jsonRelationSearchable)) {
            return $query;
        }

        return $query->when($search_term, function (Builder $query) use ($search_term) {
            $query->where(function (Builder $query) use ($search_term) {
                $this->applySearchableFields($query, $search_term);
                $this->applyRelationSearchableFields($query, $search_term);
                $this->applyJsonSearchableFields($query, $search_term);
                $this->applyJsonRelationSearchableFields($query, $search_term);
            });
        });
    }
}
