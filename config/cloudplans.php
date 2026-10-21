<?php

return [
    'overview' => [
        'benefits' => [
            'title' => 'Why Create a Trakli Cloud Account?',
            'description' => 'By registering for a Trakli Cloud account, you unlock seamless access across all your devices, secure cloud backups, exclusive feature updates, and priority support, giving you peace of mind and full control of your finances wherever you go.',
        ],
        'plans' => [
            'title' => 'Upgrade to Premium',
            'description' => 'Unlock powerful tools and premium features to achieve your financial goals.',
        ],
    ],

    'benefits' => [
        [
            'title' => 'Access Anywhere',
            'description' => 'Use Trakli on your phone, tablet, or browser. Your data stays synced across all devices.',
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
            'description' => 'Stay current with improvements and fixes. No manual updates needed.',
        ],
    ],

    'trial_days' => 3,
    'free_plan_enabled' => (bool) env('CLOUD_FREE_PLAN_ENABLED', true),
    'freemode_enabled' => (bool) env('CLOUD_FREEMODE_ENABLED', false),
    'regions' => [
        'us' => [
            'name' => 'United States',
            'currency' => 'USD',
            'monthly_price' => (float) env('CLOUD_PLAN_MONTHLY_PRICE_US', 8.99),
            'yearly_price' => (float) env('CLOUD_PLAN_YEARLY_PRICE_US', 86.28),
            'monthly_price_formatted' => env('CLOUD_PLAN_MONTHLY_PRICE_FORMATTED_US', '$8.99'),
            'yearly_price_formatted' => env('CLOUD_PLAN_YEARLY_PRICE_FORMATTED_US', '$86.28'),
        ],
        'eu' => [
            'name' => 'Europe',
            'currency' => 'EUR',
            'monthly_price' => (float) env('CLOUD_PLAN_MONTHLY_PRICE_EU', 8.99),
            'yearly_price' => (float) env('CLOUD_PLAN_YEARLY_PRICE_EU', 86.28),
            'monthly_price_formatted' => env('CLOUD_PLAN_MONTHLY_PRICE_FORMATTED_EU', '€8.99'),
            'yearly_price_formatted' => env('CLOUD_PLAN_YEARLY_PRICE_FORMATTED_EU', '€86.28'),
        ],
        'uk' => [
            'name' => 'United Kingdom',
            'currency' => 'GBP',
            'monthly_price' => (float) env('CLOUD_PLAN_MONTHLY_PRICE_UK', 8.99),
            'yearly_price' => (float) env('CLOUD_PLAN_YEARLY_PRICE_UK', 86.28),
            'monthly_price_formatted' => env('CLOUD_PLAN_MONTHLY_PRICE_FORMATTED_UK', '£8.99'),
            'yearly_price_formatted' => env('CLOUD_PLAN_YEARLY_PRICE_FORMATTED_UK', '£86.28'),
        ],
    ],

    'plans' => [
        'free' => [
            'id' => 'free',
            'name' => 'Free',
            'interval' => 'lifetime',
            'feature_keys' => [],
            'limits' => [
                'max_wallets' => 3,
                'max_categories' => 5,
            ],
            'token_allowance' => env('CLOUD_PLAN_FREE_TOKEN_ALLOWANCE', 0),
            // The strings below are marketing copy. What the gate reads is
            // feature_keys, limits and token_allowance above.
            'features' => [
                'Up to 3 wallets',
                'Up to 5 new custom categories',
                'Manual entry, reports and basic imports',
            ],
            'cta' => [
                'text' => 'Current Plan',
                'button_text' => 'Get Started',
            ],
        ],
        'monthly' => [
            'id' => 'monthly',
            'name' => 'Monthly',
            'interval' => 'month',
            'feature_keys' => ['plaid', 'bank_connections', 'ai'],
            'limits' => [
                'max_wallets' => null,
                'max_categories' => null,
            ],
            'token_allowance' => 100000,
            'features' => [
                'Unlimited wallets and categories',
                'Unlimited bank connections and accounts',
                '100,000 AI tokens per UTC calendar month',
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
            'feature_keys' => ['plaid', 'bank_connections', 'ai'],
            'limits' => [
                'max_wallets' => null,
                'max_categories' => null,
            ],
            'token_allowance' => 100000,
            'features' => [
                'Everything in Monthly',
                'Save 20% with yearly billing',
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
