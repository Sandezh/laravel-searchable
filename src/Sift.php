<?php

namespace Model\Searchable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait Sift
{
    protected $operator;
    protected $custom_timestamp_format;
    protected $timestamp_fields;
    protected $custom_time_format;
    protected $time_fields;
    protected $custom_date_format;
    protected $date_fields;
    protected $enable_exact_match_search;
    protected $json_fields;

    /**
     * Helper method to initialize common settings.
     */
    protected function initializeSiftTrait(): void
    {
        $this->operator = config('searchable.custom_operators.operator', 'LIKE');
        $this->custom_timestamp_format = config('searchable.custom_timestamp_format', "DATE_FORMAT(%s, '%Y-%m-%d %H:%i:%s')");
        $this->custom_time_format = config('searchable.custom_time_format', "DATE_FORMAT(%s, '%H:%i:%s')");
        $this->custom_date_format = config('searchable.custom_date_format', "DATE_FORMAT(%s, '%Y-%m-%d')");
        $this->enable_exact_match_search = config('searchable.enable_exact_match_search', false);

        $columns = Schema::getColumnListing($this->getTable());

        $this->timestamp_fields = [];
        $this->date_fields = [];
        $this->time_fields = [];
        $this->json_fields = [];

        foreach ($columns as $column) {
            $type = Schema::getColumnType($this->getTable(), $column);

            if (in_array($type, ['datetime', 'timestamp'])) {
                $this->timestamp_fields[] = $column;
            } elseif ($type === 'date') {
                $this->date_fields[] = $column;
            } elseif ($type === 'time') {
                $this->time_fields[] = $column;
            } elseif ($type === 'json') {
                $this->json_fields[] = $column;
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

        if (empty(static::$searchable) && empty(static::$relationSearchable)) {
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
        foreach (static::$searchable ?? [] as $field) {
            if (in_array($field, $this->timestamp_fields)) {
                $formatted_timestamp_query = str_replace('%s', $field, $this->custom_timestamp_format);

                if ($this->enable_exact_match_search) {
                    $query->orWhereRaw("{$formatted_timestamp_query} = ?", [$search_term]);
                } else {
                    $query->orWhereRaw("{$formatted_timestamp_query} {$this->operator} ?", ["%{$search_term}%"]);
                }
            } elseif (in_array($field, $this->date_fields)) {
                $formatted_date_query = str_replace('%s', $field, $this->custom_date_format);

                if ($this->enable_exact_match_search) {
                    $query->orWhereRaw("{$formatted_date_query} = ?", [$search_term]);
                } else {
                    $query->orWhereRaw("{$formatted_date_query} {$this->operator} ?", ["%{$search_term}%"]);
                }
            } elseif (in_array($field, $this->time_fields)) {
                $formatted_time_query = str_replace('%s', $field, $this->custom_time_format);

                if ($this->enable_exact_match_search) {
                    $query->orWhereRaw("{$formatted_time_query} = ?", [$search_term]);
                } else {
                    $query->orWhereRaw("{$formatted_time_query} {$this->operator} ?", ["%{$search_term}%"]);
                }
            } elseif (in_array($field, $this->json_fields)) {
                if ($this->enable_exact_match_search) {
                    $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.*')) = ?", [$search_term]);
                } else {
                    $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.*')) LIKE ?", ["%{$search_term}%"]);
                }
            } else {
                if ($this->enable_exact_match_search) {
                    $query->orWhere($field, '=', $search_term);
                } else {
                    $query->orWhere($field, $this->operator, "%{$search_term}%");
                }
            }
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
        foreach (static::$relationSearchable ?? [] as $relation => $columns) {
            foreach ((array) $columns as $column) {
                if (in_array($column, $this->timestamp_fields)) {
                    $formatted_timestamp_query = str_replace('%s', $column, $this->custom_timestamp_format);

                    $query->orWhereHas($relation, function (Builder $query) use ($formatted_timestamp_query, $search_term) {
                        if ($this->enable_exact_match_search) {
                            $query->whereRaw("{$formatted_timestamp_query} = ?", [$search_term]);
                        } else {
                            $query->whereRaw("{$formatted_timestamp_query} {$this->operator} ?", ["%{$search_term}%"]);
                        }
                    });
                } elseif (in_array($column, $this->date_fields)) {
                    $formatted_date_query = str_replace('%s', $column, $this->custom_date_format);

                    $query->orWhereHas($relation, function (Builder $query) use ($formatted_date_query, $search_term) {
                        if ($this->enable_exact_match_search) {
                            $query->whereRaw("{$formatted_date_query} = ?", [$search_term]);
                        } else {
                            $query->whereRaw("{$formatted_date_query} {$this->operator} ?", ["%{$search_term}%"]);
                        }
                    });
                } elseif (in_array($column, $this->time_fields)) {
                    $formatted_time_query = str_replace('%s', $column, $this->custom_time_format);

                    $query->orWhereHas($relation, function (Builder $query) use ($formatted_time_query, $search_term) {
                        if ($this->enable_exact_match_search) {
                            $query->whereRaw("{$formatted_time_query} = ?", [$search_term]);
                        } else {
                            $query->whereRaw("{$formatted_time_query} {$this->operator} ?", ["%{$search_term}%"]);
                        }
                    });
                } elseif (in_array($column, $this->json_fields)) {
                    $query->orWhereHas($relation, function (Builder $query) use ($column, $search_term) {
                        if ($this->enable_exact_match_search) {
                            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.*')) = ?", [$search_term]);
                        } else {
                            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.*')) LIKE ?", ["%{$search_term}%"]);
                        }
                    });
                } else {
                    $query->orWhereHas($relation, function (Builder $query) use ($column, $search_term) {
                        if ($this->enable_exact_match_search) {
                            $query->where($column, '=', $search_term);
                        } else {
                            $query->where($column, $this->operator, "%{$search_term}%");
                        }
                    });
                }
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
        foreach (static::$json_searchable ?? [] as $field => $keys) {
            foreach ($keys as $key) {
                if (in_array($key, $this->timestamp_fields)) {
                    $formatted_timestamp_query = str_replace('%s', $key, $this->custom_timestamp_format);

                    if ($this->enable_exact_match_search) {
                        $query->orWhereRaw("{$formatted_timestamp_query} = JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.{$key}'))", [$search_term]);
                    } else {
                        $query->orWhereRaw("{$formatted_timestamp_query} {$this->operator} JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.{$key}'))", ["%{$search_term}%"]);
                    }
                } elseif (in_array($key, $this->date_fields)) {
                    $formatted_date_query = str_replace('%s', $key, $this->custom_date_format);

                    if ($this->enable_exact_match_search) {
                        $query->orWhereRaw("{$formatted_date_query} = JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.{$key}'))", [$search_term]);
                    } else {
                        $query->orWhereRaw("{$formatted_date_query} {$this->operator} JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.{$key}'))", ["%{$search_term}%"]);
                    }
                } elseif (in_array($key, $this->time_fields)) {
                    $formatted_time_query = str_replace('%s', $key, $this->custom_time_format);

                    if ($this->enable_exact_match_search) {
                        $query->orWhereRaw("{$formatted_time_query} = JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.{$key}'))", [$search_term]);
                    } else {
                        $query->orWhereRaw("{$formatted_time_query} {$this->operator} JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.{$key}'))", ["%{$search_term}%"]);
                    }
                } else {
                    if ($this->enable_exact_match_search) {
                        $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.{$key}')) = ?", [$search_term]);
                    } else {
                        $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT({$field}, '$.{$key}')) {$this->operator} ?", ["%{$search_term}%"]);
                    }
                }
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
        foreach (static::$json_relation_searchable ?? [] as $relation => $json_columns) {
            foreach ((array) $json_columns as $json_column => $keys) {
                foreach ($keys as $key) {
                    if (in_array($key, $this->timestamp_fields)) {
                        $formatted_timestamp_query = str_replace('%s', $key, $this->custom_timestamp_format);

                        $query->orWhereHas($relation, function (Builder $query) use ($formatted_timestamp_query, $json_column, $key, $search_term) {
                            if ($this->enable_exact_match_search) {
                                $query->whereRaw("{$formatted_timestamp_query} = JSON_UNQUOTE(JSON_EXTRACT({$json_column}, '$.{$key}'))", [$search_term]);
                            } else {
                                $query->whereRaw("{$formatted_timestamp_query} {$this->operator} JSON_UNQUOTE(JSON_EXTRACT({$json_column}, '$.{$key}'))", ["%{$search_term}%"]);
                            }
                        });
                    } elseif (in_array($key, $this->date_fields)) {
                        $formatted_date_query = str_replace('%s', $key, $this->custom_date_format);

                        $query->orWhereHas($relation, function (Builder $query) use ($formatted_date_query, $json_column, $key, $search_term) {
                            if ($this->enable_exact_match_search) {
                                $query->whereRaw("{$formatted_date_query} = JSON_UNQUOTE(JSON_EXTRACT({$json_column}, '$.{$key}'))", [$search_term]);
                            } else {
                                $query->whereRaw("{$formatted_date_query} {$this->operator} JSON_UNQUOTE(JSON_EXTRACT({$json_column}, '$.{$key}'))", ["%{$search_term}%"]);
                            }
                        });
                    } elseif (in_array($key, $this->time_fields)) {
                        $formatted_time_query = str_replace('%s', $key, $this->custom_time_format);

                        $query->orWhereHas($relation, function (Builder $query) use ($formatted_time_query, $json_column, $key, $search_term) {
                            if ($this->enable_exact_match_search) {
                                $query->whereRaw("{$formatted_time_query} = JSON_UNQUOTE(JSON_EXTRACT({$json_column}, '$.{$key}'))", [$search_term]);
                            } else {
                                $query->whereRaw("{$formatted_time_query} {$this->operator} JSON_UNQUOTE(JSON_EXTRACT({$json_column}, '$.{$key}'))", ["%{$search_term}%"]);
                            }
                        });
                    } else {
                        $query->orWhereHas($relation, function (Builder $query) use ($json_column, $key, $search_term) {
                            if ($this->enable_exact_match_search) {
                                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$json_column}, '$.{$key}')) = ?", [$search_term]);
                            } else {
                                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT({$json_column}, '$.{$key}')) {$this->operator} ?", ["%{$search_term}%"]);
                            }
                        });
                    }
                }
            }
        }
    }
}
