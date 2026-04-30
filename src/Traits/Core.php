<?php

namespace Searchkit\Searchable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * @mixin Model
 */
trait Core
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
     * Safely get a static property from the model.
     *
     * @param string $property
     * @return array
     */
    protected function getStaticProperty(string $property): array
    {
        return property_exists(static::class, $property) ? static::$$property : [];
    }

    /**
     * Unified logic to apply constraints to a field.
     *
     * @param Builder $query
     * @param string $field
     * @param string $search_term
     * @return void
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
     *
     * @param Builder $query
     * @param string $expression
     * @param string $field_for_type
     * @param string $search_term
     * @return void
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
     * Get normalized field names from JSON search configuration.
     *
     * @param array $config
     * @return array
     */
    protected function getNormalizedJsonFields(array $config): array
    {
        $fields = [];
        foreach ($config as $field => $keys) {
            $fields[] = is_numeric($field) ? $keys : $field;
        }
        return $fields;
    }

    /**
     * Helper to process JSON search logic for both direct and relational fields.
     *
     * @param Builder $query
     * @param mixed $field
     * @param mixed $keys
     * @param string $search_term
     * @return void
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
}
