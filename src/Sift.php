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

        $searchable = property_exists(static::class, 'searchable') ? static::$searchable : [];
        $relationSearchable = property_exists(static::class, 'relation_searchable') ? static::$relation_searchable : [];
        $jsonSearchable = property_exists(static::class, 'json_searchable') ? static::$json_searchable : [];
        $jsonRelationSearchable = property_exists(static::class, 'json_relation_searchable') ? static::$json_relation_searchable : [];

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
     * Apply the search query to the model's direct searchable fields.
     *
     * @param Builder $query
     * @param string $search_term
     * @return void
     */
    protected function applySearchableFields(Builder $query, string $search_term): void
    {
        $jsonSearchable = property_exists(static::class, 'json_searchable') ? static::$json_searchable : [];
        $jsonSearchableFields = [];
        foreach ($jsonSearchable as $field => $keys) {
            $jsonSearchableFields[] = is_numeric($field) ? $keys : $field;
        }

        foreach (static::$searchable ?? [] as $field) {
            if (in_array($field, $jsonSearchableFields)) {
                continue;
            }

            $query->orWhere(function (Builder $query) use ($field, $search_term) {
                $searchTermValue = $this->enable_exact_match_search ? $search_term : "%{$search_term}%";
                $operator = $this->enable_exact_match_search ? '=' : $this->operator;

                switch (true) {
                    case in_array($field, $this->timestamp_fields):
                        $formatted_timestamp_query = str_replace('%s', $field, $this->custom_timestamp_format);
                        if ($this->case_sensitive) {
                            $query->whereRaw("{$formatted_timestamp_query} {$operator} ?", [$searchTermValue]);
                        } else {
                            $query->whereRaw("LOWER({$formatted_timestamp_query}) {$operator} LOWER(?)", [$searchTermValue]);
                        }
                        break;

                    case in_array($field, $this->date_fields):
                        $formatted_date_query = str_replace('%s', $field, $this->custom_date_format);
                        if ($this->case_sensitive) {
                            $query->whereRaw("{$formatted_date_query} {$operator} ?", [$searchTermValue]);
                        } else {
                            $query->whereRaw("LOWER({$formatted_date_query}) {$operator} LOWER(?)", [$searchTermValue]);
                        }
                        break;

                    case in_array($field, $this->time_fields):
                        $formatted_time_query = str_replace('%s', $field, $this->custom_time_format);
                        if ($this->case_sensitive) {
                            $query->whereRaw("{$formatted_time_query} {$operator} ?", [$searchTermValue]);
                        } else {
                            $query->whereRaw("LOWER({$formatted_time_query}) {$operator} LOWER(?)", [$searchTermValue]);
                        }
                        break;

                    case in_array($field, $this->json_fields):
                        $jsonExpression = "JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.*'))";
                        if ($this->case_sensitive) {
                            $query->whereRaw("{$jsonExpression} {$operator} ?", [$searchTermValue]);
                        } else {
                            $query->whereRaw("LOWER({$jsonExpression}) {$operator} LOWER(?)", [$searchTermValue]);
                        }
                        break;

                    default:
                        if ($this->case_sensitive) {
                            if ($this->enable_exact_match_search) {
                                $query->where($field, '=', $search_term);
                            } else {
                                $query->where($field, $this->operator, "%{$search_term}%");
                            }
                        } else {
                            $query->whereRaw("LOWER({$field}) {$operator} LOWER(?)", [$searchTermValue]);
                        }
                        break;
                }
            });
        }
    }

    /**
     * Apply the search query to the model's relationSearchable fields.
     *
     * @param Builder $query
     * @param string $search_term
     * @return void
     */
    protected function applyRelationSearchableFields(Builder $query, string $search_term): void
    {
        $jsonRelationSearchable = property_exists(static::class, 'json_relation_searchable') ? static::$json_relation_searchable : [];

        foreach (static::$relation_searchable ?? [] as $relation => $columns) {
            $jsonRelationFields = [];
            if (isset($jsonRelationSearchable[$relation])) {
                foreach ((array) $jsonRelationSearchable[$relation] as $field => $keys) {
                    $jsonRelationFields[] = is_numeric($field) ? $keys : $field;
                }
            }

            foreach ((array) $columns as $column) {
                if (in_array($column, $jsonRelationFields)) {
                    continue;
                }

                $query->orWhereHas($relation, function (Builder $query) use ($column, $search_term) {
                    $searchTermValue = $this->enable_exact_match_search ? $search_term : "%{$search_term}%";
                    $operator = $this->enable_exact_match_search ? '=' : $this->operator;

                    switch (true) {
                        case in_array($column, $this->timestamp_fields):
                            $formatted_timestamp_query = str_replace('%s', $column, $this->custom_timestamp_format);
                            if ($this->case_sensitive) {
                                $query->whereRaw("{$formatted_timestamp_query} {$operator} ?", [$searchTermValue]);
                            } else {
                                $query->whereRaw("LOWER({$formatted_timestamp_query}) {$operator} LOWER(?)", [$searchTermValue]);
                            }
                            break;

                        case in_array($column, $this->date_fields):
                            $formatted_date_query = str_replace('%s', $column, $this->custom_date_format);
                            if ($this->case_sensitive) {
                                $query->whereRaw("{$formatted_date_query} {$operator} ?", [$searchTermValue]);
                            } else {
                                $query->whereRaw("LOWER({$formatted_date_query}) {$operator} LOWER(?)", [$searchTermValue]);
                            }
                            break;

                        case in_array($column, $this->time_fields):
                            $formatted_time_query = str_replace('%s', $column, $this->custom_time_format);
                            if ($this->case_sensitive) {
                                $query->whereRaw("{$formatted_time_query} {$operator} ?", [$searchTermValue]);
                            } else {
                                $query->whereRaw("LOWER({$formatted_time_query}) {$operator} LOWER(?)", [$searchTermValue]);
                            }
                            break;

                        case in_array($column, $this->json_fields):
                            $jsonExpression = "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.*'))";
                            if ($this->case_sensitive) {
                                $query->whereRaw("{$jsonExpression} {$operator} ?", [$searchTermValue]);
                            } else {
                                $query->whereRaw("LOWER({$jsonExpression}) {$operator} LOWER(?)", [$searchTermValue]);
                            }
                            break;

                        default:
                            if ($this->case_sensitive) {
                                if ($this->enable_exact_match_search) {
                                    $query->where($column, '=', $search_term);
                                } else {
                                    $query->where($column, $this->operator, "%{$search_term}%");
                                }
                            } else {
                                $query->whereRaw("LOWER({$column}) {$operator} LOWER(?)", [$searchTermValue]);
                            }
                            break;
                    }
                });
            }
        }
    }

    /**
     * Apply the search query to the model's jsonSearchable fields.
     *
     * @param Builder $query
     * @param string $search_term
     * @return void
     */
    protected function applyJsonSearchableFields(Builder $query, string $search_term): void
    {
        $jsonSearchable = property_exists(static::class, 'json_searchable') ? static::$json_searchable : [];

        foreach ($jsonSearchable ?? [] as $field => $keys) {
            if (is_numeric($field)) {
                $field = $keys;
                $keys = ['*'];
            }

            $keys = (array) $keys;

            foreach ($keys as $key) {
                $query->orWhere(function (Builder $query) use ($field, $key, $search_term) {
                    $jsonSelector = $key === '*' ? '$.*' : "$.{$key}";
                    $jsonExpression = "JSON_UNQUOTE(JSON_EXTRACT({$field}, '{$jsonSelector}'))";

                    $this->applyJsonConstraint($query, $jsonExpression, $key, $search_term);
                });
            }
        }
    }

    /**
     * Apply the search query to the model's jsonRelationSearchable fields.
     *
     * @param Builder $query
     * @param string $search_term
     * @return void
     */
    protected function applyJsonRelationSearchableFields(Builder $query, string $search_term): void
    {
        $jsonRelationSearchable = property_exists(static::class, 'json_relation_searchable') ? static::$json_relation_searchable : [];

        foreach ($jsonRelationSearchable ?? [] as $relation => $json_columns) {
            $query->orWhereHas($relation, function (Builder $query) use ($json_columns, $search_term) {
                $query->where(function (Builder $query) use ($json_columns, $search_term) {
                    foreach ((array) $json_columns as $json_column => $keys) {
                        if (is_numeric($json_column)) {
                            $json_column = $keys;
                            $keys = ['*'];
                        }

                        $keys = (array) $keys;

                        foreach ($keys as $key) {
                            $query->orWhere(function (Builder $query) use ($json_column, $key, $search_term) {
                                $jsonSelector = $key === '*' ? '$.*' : "$.{$key}";
                                $jsonExpression = "JSON_UNQUOTE(JSON_EXTRACT({$json_column}, '{$jsonSelector}'))";

                                $this->applyJsonConstraint($query, $jsonExpression, $key, $search_term);
                            });
                        }
                    }
                });
            });
        }
    }

    /**
     * Helper to apply the appropriate constraint (date/time/timestamp or default) to a JSON expression.
     *
     * @param Builder $query
     * @param string $jsonExpression
     * @param string $key
     * @param string $search_term
     * @return void
     */
    protected function applyJsonConstraint(Builder $query, string $jsonExpression, string $key, string $search_term): void
    {
        $searchTermValue = $this->enable_exact_match_search ? $search_term : "%{$search_term}%";
        $operator = $this->enable_exact_match_search ? '=' : $this->operator;

        switch (true) {
            case in_array($key, $this->timestamp_fields):
                $formatted_query = str_replace('%s', $jsonExpression, $this->custom_timestamp_format);
                if ($this->case_sensitive) {
                    $query->whereRaw("{$formatted_query} {$operator} ?", [$searchTermValue]);
                } else {
                    $query->whereRaw("LOWER({$formatted_query}) {$operator} LOWER(?)", [$searchTermValue]);
                }
                break;

            case in_array($key, $this->date_fields):
                $formatted_query = str_replace('%s', $jsonExpression, $this->custom_date_format);
                if ($this->case_sensitive) {
                    $query->whereRaw("{$formatted_query} {$operator} ?", [$searchTermValue]);
                } else {
                    $query->whereRaw("LOWER({$formatted_query}) {$operator} LOWER(?)", [$searchTermValue]);
                }
                break;

            case in_array($key, $this->time_fields):
                $formatted_query = str_replace('%s', $jsonExpression, $this->custom_time_format);
                if ($this->case_sensitive) {
                    $query->whereRaw("{$formatted_query} {$operator} ?", [$searchTermValue]);
                } else {
                    $query->whereRaw("LOWER({$formatted_query}) {$operator} LOWER(?)", [$searchTermValue]);
                }
                break;

            default:
                if ($this->case_sensitive) {
                    $query->whereRaw("{$jsonExpression} {$operator} ?", [$searchTermValue]);
                } else {
                    $query->whereRaw("LOWER({$jsonExpression}) {$operator} LOWER(?)", [$searchTermValue]);
                }
                break;
        }
    }
}
