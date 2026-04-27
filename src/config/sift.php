<?php

return [
    /*
    |---------------------------------------------------------------------- 
    | Custom Search Operators
    |---------------------------------------------------------------------- 
    | 
    | All fields will now use the "LIKE" operator.
    |
    */
    'custom_operators' => [
        'operator' => 'LIKE',
    ],

    /*
    |---------------------------------------------------------------------- 
    | Enable Exact Match Search
    |---------------------------------------------------------------------- 
    |
    | If set to true, the search will use an exact match (using the `=` operator)
    | for the fields instead of the default "LIKE" operator. 
    |
    | WARNING: Enabling exact match search is NOT recommended for general use, as
    | it can lead to very limited search results and may cause unexpected behavior 
    | in searches that need to support partial matches.
    |
    | Default: false
    */
    'enable_exact_match_search' => false,

    /*
    |---------------------------------------------------------------------- 
    | Case Sensitive Search
    |---------------------------------------------------------------------- 
    |
    | If set to true, the search will be case-sensitive.
    | If set to false, the search will be case-insensitive.
    |
    | Default: false
    */
    'case_sensitive' => false,

    /*
    |--------------------------------------------------------------------------- 
    | Default Exclude Fields
    |--------------------------------------------------------------------------- 
    | 
    | This option defines the default fields that should be excluded from the 
    | search functionality across all models. You can modify this array to add 
    | or remove fields globally. 
    |
    */

    'default_exclude_fields' => [
        'id',
        'updated_at',
    ],

    /*
    |---------------------------------------------------------------------- 
    | Custom Timestamp Format 
    |---------------------------------------------------------------------- 
    | 
    | This option defines the custom timestamp format used for searching timestamp fields. 
    | The format is applied dynamically to timestamp fields during a search query.
    | The %s placeholder is replaced with the actual field name in the query.
    | Modify this format as per your application's requirements.
    |
    | Example : DATE_FORMAT(created_at, '%b %D, %Y - %l:%i:%s %p') = 'Jan 28, 2025 - 3:15:45 PM'
    |
    */

    'custom_timestamp_format' => "DATE_FORMAT(%s, '%b %D, %Y - %l:%i:%s %p')",

    /*
    |---------------------------------------------------------------------- 
    | Custom Date Format 
    |---------------------------------------------------------------------- 
    | 
    | This option defines the custom date format used for searching date fields. 
    | The format is applied dynamically to date fields during a search query.
    | The %s placeholder is replaced with the actual field name in the query.
    | Modify this format as per your application's requirements.
    |
    | Example : DATE_FORMAT(start_date, '%b %D, %Y') = '3:15:45 PM, Jan 28, 2025'
    |
    */

    'custom_date_format' => "DATE_FORMAT(%s, '%b %D, %Y')",

    /*
    |---------------------------------------------------------------------- 
    | Custom Time Format
    |---------------------------------------------------------------------- 
    | 
    | This option defines the custom time format used for searching time fields. 
    | The format is applied dynamically to time fields during a search query.
    | The %s placeholder is replaced with the actual field name in the query.
    | Modify this format as per your application's requirements.
    |
    | Example : DATE_FORMAT(start_time, '%h:%i:%s %p') = '3:15:45 PM'
    |
    */

    'custom_time_format' => "DATE_FORMAT(%s, '%h:%i:%s %p')",
];
