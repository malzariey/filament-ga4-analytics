<?php

declare(strict_types=1);

return [
    'loading' => 'Loading analytics…',
    'refreshing' => 'Refreshing…',
    'navigation_label' => 'Google Analytics reports',
    'open_in_google' => 'Open in Google Analytics',

    'custom_range' => 'Custom range',
    'custom_range_placeholder' => 'Pick a start and end date',

    'live_heading' => 'On the site now',
    'live_desc' => 'Refreshes every :seconds seconds',
    'updated_at' => 'Updated',
    'active_now' => 'Active visitors now',
    'per_minute' => 'Visitors in the last 30 minutes',
    'minutes_ago' => ':n minutes ago',
    'now' => 'Now',
    'top_pages_now' => 'Most viewed pages now',
    'devices_now' => 'Devices now',
    'countries_now' => 'Countries now',
    'nobody_now' => 'Nobody is on the site right now',

    'tab_visitors' => 'Visitors',
    'tab_staff' => 'Staff activity',

    'trend_heading' => 'Visitors over the period',
    'trend_desc_daily' => 'Visitors on each day of the selected period.',
    'trend_desc_hourly' => 'Visitors in each hour of the day.',
    'trend_users' => 'visitors',
    'trend_sessions' => 'sessions',

    'funnel_heading' => 'Conversion funnel',
    'funnel_desc' => 'Visitors at each step, and the share who reached it from the step before.',
    'users_unit' => 'visitors',
    'of_previous' => 'of previous',

    'events_heading' => 'Events',
    'events_desc_pinned' => 'How many times each event happened in the period.',
    'events_desc_top' => 'The most frequent events in the period.',

    'sources_heading' => 'Traffic sources and campaigns',
    'source' => 'Source / medium',
    'campaign' => 'Campaign',
    'sessions' => 'Sessions',
    'key_events' => 'Key events',

    'no_data' => 'No data for this period',
    'cached_note' => 'These figures refresh every :minutes minutes. Last read:',

    'staff_events' => 'Most frequent actions',
    'staff_pages' => 'Most visited pages',

    'ranges' => [
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
    ],

    'kpis' => [
        'users' => 'Visitors',
        'new_users' => 'New visitors',
        'sessions' => 'Sessions',
        'views' => 'Page views',
        'engagement_rate' => 'Engagement rate',
        'avg_session' => 'Avg. session',
        'bounce_rate' => 'Bounce rate',
        'events' => 'Events',
        'key_events' => 'Key events',
        'purchases' => 'Purchases',
        'revenue' => 'Revenue',
        'staff' => 'Active staff',
        'staff_views' => 'Pages opened',
        'staff_minutes' => 'Engaged minutes',
    ],

    'breakdowns' => [
        'pages' => 'Most viewed pages',
        'page_paths' => 'Most viewed paths',
        'landing_pages' => 'Landing pages',
        'devices' => 'Devices',
        'countries' => 'Countries',
        'cities' => 'Cities',
        'channels' => 'Channels',
        'browsers' => 'Browsers',
    ],

    'values' => [
        'buckets' => [
            '(other)' => 'Other',
            '(not set)' => 'Not set',
            '(direct)' => 'Direct',
            '(none)' => 'None',
            '(organic)' => 'Organic',
            '(referral)' => 'Referral',
            '(ai-assistant)' => 'AI assistant',
            '(data not available)' => 'Data not available',
            '(cross-network)' => 'Cross-network',
        ],
        'devices' => [
            'mobile' => 'Mobile',
            'desktop' => 'Desktop',
            'tablet' => 'Tablet',
            'smart tv' => 'Smart TV',
        ],
        // Listed even though they are GA's own words: a key missing here falls
        // back to the host's fallback locale, which on an Arabic-first site is
        // Arabic.
        'channels' => [
            'Direct' => 'Direct',
            'Organic Search' => 'Organic Search',
            'Paid Search' => 'Paid Search',
            'Organic Social' => 'Organic Social',
            'Paid Social' => 'Paid Social',
            'Referral' => 'Referral',
            'Email' => 'Email',
            'Display' => 'Display',
            'Organic Video' => 'Organic Video',
            'Organic Shopping' => 'Organic Shopping',
            'Organic Maps' => 'Organic Maps',
            'SMS' => 'SMS',
            'Unassigned' => 'Unassigned',
            'AI Assistant' => 'AI Assistant',
            'Cross-network' => 'Cross-network',
            'Affiliates' => 'Affiliates',
            'Audio' => 'Audio',
            'Mobile Push Notifications' => 'Mobile Push Notifications',
        ],
    ],

    'events' => [
        'page_view' => 'Page view',
        'session_start' => 'Session start',
        'first_visit' => 'First visit',
        'user_engagement' => 'Engagement',
        'scroll' => 'Scroll',
        'click' => 'Outbound click',
        'file_download' => 'File download',
        'form_start' => 'Form started',
        'form_submit' => 'Form submitted',
        'video_start' => 'Video start',
        'video_progress' => 'Video progress',
        'video_complete' => 'Video complete',
        'view_search_results' => 'Site search',
        'generate_lead' => 'Lead',
        'select_content' => 'Content selected',
        'share' => 'Share',
        'view_item' => 'Item view',
        'begin_checkout' => 'Checkout started',
        'add_payment_info' => 'Payment info added',
        'purchase' => 'Purchase',
        'refund' => 'Refund',
        'sign_up' => 'Sign up',
        'login' => 'Login',
    ],

    'errors' => [
        'not_configured' => 'No Google Analytics property id is set yet, so there are no reports to show.',
        'no_credentials' => 'The service-account key is missing or invalid on the server, so analytics cannot be read.',
        'forbidden' => 'Google Analytics refused the request. Check that the Analytics Data API is enabled and the service account has Viewer access to this property.',
        'quota' => 'The request quota is spent for now. The figures will be back in a few minutes.',
        'failed' => 'Analytics could not be read just now. It will be retried shortly.',
    ],
];
