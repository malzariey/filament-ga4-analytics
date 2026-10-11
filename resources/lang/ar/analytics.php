<?php

declare(strict_types=1);

return [
    'loading' => 'جارٍ تحميل التحليلات…',
    'refreshing' => 'يتحدّث…',
    'navigation_label' => 'تقارير Google Analytics',
    'open_in_google' => 'فتح في Google Analytics',

    'custom_range' => 'فترة مخصّصة',
    'custom_range_placeholder' => 'اختر من تاريخ إلى تاريخ',

    'live_heading' => 'الآن على الموقع',
    'live_desc' => 'يتحدّث كل :seconds ثانية',
    'updated_at' => 'آخر تحديث',
    'active_now' => 'زوار نشطون الآن',
    'per_minute' => 'الزوار في آخر 30 دقيقة',
    'minutes_ago' => 'قبل :n دقيقة',
    'now' => 'الآن',
    'top_pages_now' => 'أكثر الصفحات مشاهدة الآن',
    'devices_now' => 'الأجهزة الآن',
    'countries_now' => 'الدول الآن',
    'nobody_now' => 'لا يوجد زوار الآن',

    'tab_visitors' => 'الزوار',
    'tab_staff' => 'نشاط الموظفين',

    'trend_heading' => 'الزوار عبر الفترة',
    'trend_desc_daily' => 'عدد الزوار في كل يوم من الفترة المختارة.',
    'trend_desc_hourly' => 'عدد الزوار في كل ساعة من اليوم.',
    'trend_users' => 'زوار',
    'trend_sessions' => 'جلسات',

    'funnel_heading' => 'مسار التحويل',
    'funnel_desc' => 'عدد الزوار في كل خطوة، ونسبة من انتقل إليها من الخطوة السابقة.',
    'users_unit' => 'زائر',
    'of_previous' => 'من السابقة',

    'events_heading' => 'الأحداث',
    'events_desc_pinned' => 'عدد مرات وقوع كل حدث في الفترة.',
    'events_desc_top' => 'أكثر الأحداث تكراراً في الفترة.',

    'sources_heading' => 'مصادر الزيارات والحملات',
    'source' => 'المصدر / الوسيط',
    'campaign' => 'الحملة',
    'sessions' => 'الجلسات',
    'key_events' => 'أحداث رئيسية',

    'no_data' => 'لا توجد بيانات في هذه الفترة',
    'cached_note' => 'تُحدَّث هذه الأرقام كل :minutes دقائق. آخر قراءة:',

    'staff_events' => 'أكثر الأحداث تنفيذاً',
    'staff_pages' => 'أكثر الصفحات زيارة',

    'ranges' => [
        'today' => 'اليوم',
        '7d' => 'آخر 7 أيام',
        '30d' => 'آخر 30 يوماً',
        '90d' => 'آخر 90 يوماً',
    ],

    'kpis' => [
        'users' => 'الزوار',
        'new_users' => 'زوار جدد',
        'sessions' => 'الجلسات',
        'views' => 'مشاهدات الصفحات',
        'engagement_rate' => 'نسبة التفاعل',
        'avg_session' => 'متوسط مدة الجلسة',
        'bounce_rate' => 'نسبة الارتداد',
        'events' => 'الأحداث',
        'key_events' => 'الأحداث الرئيسية',
        'purchases' => 'عمليات الشراء',
        'revenue' => 'الإيرادات',
        'staff' => 'موظفون نشطون',
        'staff_views' => 'صفحات مفتوحة',
        'staff_minutes' => 'دقائق عمل',
    ],

    'breakdowns' => [
        'pages' => 'أكثر الصفحات مشاهدة',
        'page_paths' => 'أكثر المسارات مشاهدة',
        'landing_pages' => 'صفحات الدخول',
        'devices' => 'الأجهزة',
        'countries' => 'الدول',
        'cities' => 'المدن',
        'channels' => 'قنوات الزيارات',
        'browsers' => 'المتصفحات',
    ],

    'values' => [
        // GA folds low-volume rows into «(other)» and blanks into «(not set)».
        'buckets' => [
            '(other)' => 'أخرى',
            '(not set)' => 'غير محدد',
            '(direct)' => 'مباشر',
            '(none)' => 'بدون',
            '(organic)' => 'بحث طبيعي',
            '(referral)' => 'إحالة',
            '(ai-assistant)' => 'مساعد ذكاء اصطناعي',
            '(data not available)' => 'غير متاح',
            '(cross-network)' => 'عبر عدة قنوات',
        ],
        'devices' => [
            'mobile' => 'جوال',
            'desktop' => 'حاسب',
            'tablet' => 'جهاز لوحي',
            'smart tv' => 'تلفاز',
        ],
        'channels' => [
            'Direct' => 'مباشر',
            'Organic Search' => 'بحث طبيعي',
            'Paid Search' => 'بحث مدفوع',
            'Organic Social' => 'تواصل اجتماعي',
            'Paid Social' => 'تواصل اجتماعي مدفوع',
            'Referral' => 'إحالة من مواقع أخرى',
            'Email' => 'البريد الإلكتروني',
            'Display' => 'إعلانات مرئية',
            'Organic Video' => 'فيديو',
            'Organic Shopping' => 'تسوّق',
            'Organic Maps' => 'الخرائط',
            'SMS' => 'رسائل نصية',
            'Unassigned' => 'غير مصنّف',
            'AI Assistant' => 'مساعدات الذكاء الاصطناعي',
            'Cross-network' => 'عبر عدة قنوات',
            'Affiliates' => 'الشركاء',
            'Audio' => 'صوتيات',
            'Mobile Push Notifications' => 'إشعارات الجوال',
        ],
    ],

    // Google's automatically collected and recommended events. A host names
    // its own through the plugin's ->eventLabels().
    'events' => [
        'page_view' => 'مشاهدة صفحة',
        'session_start' => 'بدء جلسة',
        'first_visit' => 'زيارة أولى',
        'user_engagement' => 'تفاعل',
        'scroll' => 'تمرير الصفحة',
        'click' => 'نقر على رابط خارجي',
        'file_download' => 'تنزيل ملف',
        'form_start' => 'بدء تعبئة نموذج',
        'form_submit' => 'إرسال نموذج',
        'video_start' => 'تشغيل فيديو',
        'video_progress' => 'متابعة فيديو',
        'video_complete' => 'إكمال فيديو',
        'view_search_results' => 'بحث في الموقع',
        'generate_lead' => 'طلب تواصل',
        'select_content' => 'اختيار محتوى',
        'share' => 'مشاركة',
        'view_item' => 'مشاهدة عنصر',
        'begin_checkout' => 'بدء الشراء',
        'add_payment_info' => 'إدخال بيانات الدفع',
        'purchase' => 'شراء',
        'refund' => 'استرداد',
        'sign_up' => 'تسجيل حساب',
        'login' => 'تسجيل دخول',
    ],

    'errors' => [
        'not_configured' => 'لم يُضبط رقم الموقع في Google Analytics (Property ID) بعد، فلا توجد تقارير لعرضها.',
        'no_credentials' => 'مفتاح حساب الخدمة غير موجود أو غير صالح على الخادم، فلا يمكن قراءة التحليلات.',
        'forbidden' => 'رفضت Google Analytics الطلب. تأكد أن واجهة Analytics Data API مفعّلة وأن حساب الخدمة مضاف بصلاحية «مشاهد» على هذا الموقع.',
        'quota' => 'تجاوزنا حصة الطلبات المسموح بها مؤقتاً. ستعود الأرقام خلال دقائق.',
        'failed' => 'تعذّرت قراءة التحليلات الآن. سنحاول مجدداً بعد قليل.',
    ],
];
