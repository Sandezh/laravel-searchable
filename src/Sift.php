<?php

namespace Model\Searchable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait Sift
{
    protected $operator;
    protected $enable_exact_match_search;
    protected $case_sensitive;
    protected $custom_timestamp_format;
    protected $timestamp_fields = [];
    protected $custom_date_format;
    protected $date_fields = [];
    protected $custom_time_format;
    protected $time_fields = [];
    protected $json_fields = [];

    /**
     * Helper method to initialize common settings.
     */
    protected function initializeSiftTrait(): void
    {
        $this->operator = config('searchable.custom_operators.operator', 'LIKE');
        $this->enable_exact_match_search = config('searchable.enable_exact_match_search', false);
        $this->case_sensitive = config('searchable.case_sensitive', false);
        $this->custom_timestamp_format = config('searchable.custom_timestamp_format', "DATE_FORMAT(%s, '%Y-%m-%d %H:%i:%s')");
        $this->custom_date_format = config('searchable.custom_date_format', "DATE_FORMAT(%s, '%Y-%m-%d')");
        $this->custom_time_format = config('searchable.custom_time_format', "DATE_FORMAT(%s, '%H:%i:%s')");

        $columns = Schema::getColumnListing($this->getTable());
        foreach ($columns as $column) {
            $type = Schema::getColumnType($this->getTable(), $column);

            switch ($type) {
                case 'datetime':
                case 'timestamp':
                    $this->timestamp_fields[] = $column;
                    break;
                case 'date':
                    $this->date_fields[] = $column;
                    break;
                case 'time':
                    $this->time_fields[] = $column;
                    break;
                case 'json':
                    $this->json_fields[] = $column;
                    break;
            }
        }
    }

    /**
     * Dynamically populate the $searchable property with all fields except the excluded ones.
     *
     * @param array $exclude_fields
     * @param array|null $default_exclude_fields
     * @return array
     */
    protected static function getSearchableFields(array $exclude_fields = [], ?array $default_exclude_fields = []): array
    {
        $default_exclude_fields = empty($default_exclude_fields) ? config('searchable.default_exclude_fields', []) : $default_exclude_fields;

        $exclude_fields = array_merge($default_exclude_fields, $exclude_fields);

        $columns = Schema::getColumnListing((new static)->getTable());

        return array_diff($columns, $exclude_fields);
    }

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

    /**
     * Safely get a static property from the model.
     */
    protected function getStaticProperty(string $property): array
    {
        return property_exists(static::class, $property) ? static::$$property : [];
    }

    /**
     * Apply the search query to the model's direct searchable fields.
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

    /**
     * Apply the search query to the model's relationSearchable fields.
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

    /**
     * Apply the search query to the model's jsonSearchable fields.
     */
    protected function applyJsonSearchableFields(Builder $query, string $search_term): void
    {
        $jsonSearchable = $this->getStaticProperty('json_searchable');

        foreach ($jsonSearchable as $field => $keys) {
            $this->processJsonSearch($query, $field, $keys, $search_term);
        }
    }

    /**
     * Apply the search query to the model's jsonRelationSearchable fields.
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

    /**
     * Unified logic to apply constraints to a field.
     */
    protected function applyFieldConstraint(Builder $query, string $field, string $search_term): void
    {
        $isJson = in_array($field, $this->json_fields);
        $driver = $query->getConnection()->getDriverName();

        if ($isJson) {
            if ($driver === 'sqlite') {
                $expression = $field;
            } else {
                $expression = "JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.*'))";
            }
        } else {
            $expression = $field;
        }

        $this->applyRawConstraint($query, $expression, $field, $search_term);
    }

    /**
     * Apply a raw SQL constraint with date/time formatting and case sensitivity.
     */
    protected function applyRawConstraint(Builder $query, string $expression, string $field_for_type, string $search_term): void
    {
        $searchTermValue = $this->enable_exact_match_search ? $search_term : "%{$search_term}%";
        $operator = $this->enable_exact_match_search ? '=' : $this->operator;

        $format = match (true) {
            in_array($field_for_type, $this->timestamp_fields) => $this->custom_timestamp_format,
            in_array($field_for_type, $this->date_fields) => $this->custom_date_format,
            in_array($field_for_type, $this->time_fields) => $this->custom_time_format,
            default => null,
        };

        if ($format) {
            $expression = str_replace('%s', $expression, $format);
        }

        if ($this->case_sensitive && !$format && !in_array($field_for_type, $this->json_fields)) {
            $query->where($field_for_type, $operator, $searchTermValue);
            return;
        }

        $sql = $this->case_sensitive ? "{$expression} {$operator} ?" : "LOWER({$expression}) {$operator} LOWER(?)";
        $query->whereRaw($sql, [$searchTermValue]);
    }

    /**
     * Helper to process JSON search logic for both direct and relational fields.
     */
    protected function processJsonSearch(Builder $query, $field, $keys, string $search_term): void
    {
        if (is_numeric($field)) {
            $field = $keys;
            $keys = ['*'];
        }

        $driver = $query->getConnection()->getDriverName();

        foreach ((array) $keys as $key) {
            $query->orWhere(function (Builder $q) use ($field, $key, $search_term, $driver) {
                if ($driver === 'sqlite') {
                    $selector = $key === '*' ? '' : "->{$key}";
                    $expression = "{$field}{$selector}";
                    $this->applyRawConstraint($q, $expression, $key, $search_term);
                } else {
                    $jsonSelector = $key === '*' ? '$.*' : "$.{$key}";
                    $expression = "JSON_UNQUOTE(JSON_EXTRACT({$field}, '{$jsonSelector}'))";
                    $this->applyRawConstraint($q, $expression, $key, $search_term);
                }
            });
        }
    }

    /**
     * Get normalized field names from JSON search configuration.
     */
    protected function getNormalizedJsonFields(array $config): array
    {
        $fields = [];
        foreach ($config as $field => $keys) {
            $fields[] = is_numeric($field) ? $keys : $field;
        }
        return $fields;
    }
}
