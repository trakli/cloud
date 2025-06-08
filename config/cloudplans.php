<?php

return [
    'overview' => [
        'title' => 'Why Create a Trakli Cloud Account?',
        'description' => 'By registering for a Trakli Cloud account, you unlock seamless access across all your devices, secure cloud backups, exclusive feature updates, and priority support — giving you peace of mind and full control of your finances wherever you go.',
    ],

    'benefits' => [
        [
            'title' => 'Access Anywhere',
            'description' => 'Use Trakli on your phone, tablet, or browser — your data stays synced across all devices.',
        ],
        [
            'title' => 'Secure Cloud Backups',
            'description' => 'Never lose your data. Your transactions and settings are automatically backed up to the cloud.',
        ],
        [
            'title' => 'Early Access to New Features',
            'description' => 'Be the first to try out new budgeting tools, reports, and integrations before anyone else.',
        ],
        [
            'title' => 'Priority Support',
            'description' => 'Get help faster with our cloud user support channel, guaranteed response within 24 hours.',
        ],
        [
            'title' => 'Automatic Updates',
            'description' => 'Stay current with improvements and fixes — no manual updates needed.',
        ],
    ],

    // Prices are in cents
    'trial_days' => 3,
    'free_plan_enabled' => (bool) env('CLOUD_FREE_PLAN_ENABLED', true),
    'regions' => [
        'us' => [
            'name' => 'United States',
            'currency' => 'USD',
            'monthly_price' => (int) env('CLOUD_PLAN_MONTHLY_PRICE', 500),
            'yearly_price' => (int) env('CLOUD_PLAN_YEARLY_PRICE', 5000),
        ],
        'eu' => [
            'name' => 'Europe',
            'currency' => 'USD',
            'monthly_price' => (int) env('CLOUD_PLAN_MONTHLY_PRICE', 500),
            'yearly_price' => (int) env('CLOUD_PLAN_YEARLY_PRICE', 5000),
        ],
        'uk' => [
            'name' => 'United Kingdom',
            'currency' => 'USD',
            'monthly_price' => (int) env('CLOUD_PLAN_MONTHLY_PRICE', 500),
            'yearly_price' => (int) env('CLOUD_PLAN_YEARLY_PRICE', 5000),
        ],
    ],

    'plans' => [
        'monthly' => [
            'id' => 'monthly',
            'name' => 'Monthly',
            'interval' => 'month',
            'features' => [
                'Unlimited categories and wallets',
                'Mobile and web access',
                'CSV exports',
                'Community support',
            ],
            'cta' => [
                'text' => env('CLOUD_PLAN_CTA_TEXT', 'Start 3-Day Free Trial'),
                'button_text' => env('CLOUD_PLAN_BUTTON_TEXT', 'Get Started'),
            ],
        ],
        'yearly' => [
            'id' => 'yearly',
            'name' => 'Yearly',
            'interval' => 'year',
            'features' => [
                'Everything in Monthly',
                '2 months free (save ~17%)',
                'Premium support',
                'Early feature access',
                'Priority voting on roadmap',
            ],
            'cta' => [
                'text' => env('CLOUD_PLAN_CTA_TEXT', 'Start 3-Day Free Trial'),
                'button_text' => env('CLOUD_PLAN_BUTTON_TEXT', 'Get Started'),
            ],
        ],
    ],
];
