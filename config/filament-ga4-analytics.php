<?php

declare(strict_types=1);

/*
 * Google Analytics 4 reports page — fallbacks only.
 *
 * A host normally supplies the property id and the service account from its own
 * settings through the panel plugin (`->propertyId(fn () => …)`,
 * `->credentials(fn () => …)`) or `Ga4Connection::resolve…Using()`. These
 * values are what is used when it does not.
 */
return [
    // `google` reads the Data API; `fake` answers canned rows (tests, local
    // screenshots) and refuses to run in production. Pin `fake` in phpunit.xml.
    'driver' => env('GA4_ANALYTICS_DRIVER', 'google'),

    // Numeric GA4 property id(s) — NOT the G-XXXXXXXXXX measurement id.
    'property_id' => env('GA4_PROPERTY_ID'),
    'staff_property_id' => env('GA4_STAFF_PROPERTY_ID'),

    // Absolute path to the read-only service account's JSON key, outside the
    // web root. Grant the account «Viewer» on the property in GA.
    'credentials_path' => env('GA4_CREDENTIALS_PATH'),

    // Zone for «today» in links and the custom-range picker; null falls back to
    // app.display_timezone, then app.timezone.
    'timezone' => env('GA4_ANALYTICS_TIMEZONE'),

    // 0 = Sunday … 6 = Saturday.
    'first_day_of_week' => 6,

    // ⛔ The cache is the quota control: the Data API meters every report
    // against a daily token budget per property.
    'cache' => [
        'realtime_seconds' => 25,
        'report_seconds' => 300,
        'error_seconds' => 60,
    ],

    'timeout' => 15,

    // Extra attempts on a dropped connection or a 5xx only — never on a 4xx.
    'retries' => 1,

    'data_api_url' => 'https://analyticsdata.googleapis.com/v1beta',
    'token_url' => 'https://oauth2.googleapis.com/token',
];
