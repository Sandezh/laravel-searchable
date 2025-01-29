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
    | Timestamp Fields 
    |---------------------------------------------------------------------- 
    | 
    | This option defines the fields in your database that are considered 
    | timestamp fields and require the custom format for searching. 
    | Add or remove fields as per your application's needs. 
    | Example: ['created_at', 'updated_at']
    |
    */

    'timestamp_fields' => [
        'created_at',
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
    | Date Fields 
    |---------------------------------------------------------------------- 
    | 
    | This option defines the fields in your database that are considered 
    | date-only fields (without time) and should use a custom format for searching. 
    | These fields are treated as date-only values.
    | Example: ['birthdate', 'start_date']
    |
    */

    'date_fields' => [
        'start_date', // Example field
    ],

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
    | Time Fields
    |---------------------------------------------------------------------- 
    | 
    | This option defines the fields in your database that are considered 
    | time fields and should use the custom time format during search queries.
    | These fields are treated as time-only values (without date).
    | Example: ['start_time', 'end_time']
    |
    */

    'time_fields' => [
        'start_time', // Example field
        'end_time', // Example field
    ],

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
    | Example : DATE_FORMAT(start_time, '%l:%i:%s %p') = '3:15:45 PM'
    |
    */

    'custom_time_format' => "DATE_FORMAT(%s, '%l:%i:%s %p')",
];
