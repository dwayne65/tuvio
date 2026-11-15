<?php

return [
    // Default platform currency (ISO-4217). UGX for the primary (Uganda/Africa) market.
    'default_currency' => env('TICKETFLOW_CURRENCY', 'UGX'),

    // Default platform commission in basis points (500 = 5.00%).
    // Can be overridden per organization and per event.
    'commission_bps' => (int) env('TICKETFLOW_COMMISSION_BPS', 500),

    // Tax charged on the platform fee (not on the ticket price), in basis points: 1800 = 18%
    // Uganda VAT. It follows the fee: added for buyers when they pay the fee, otherwise
    // taken from the organizer's earnings with the commission. Held as TAX_PAYABLE.
    'tax' => [
        'bps' => (int) env('TICKETFLOW_TAX_BPS', 1800),
        'label' => env('TICKETFLOW_TAX_LABEL', 'VAT'),
    ],

    // Default commission on contributions/fundraising (configured independently of tickets).
    'contribution_commission_bps' => (int) env('TICKETFLOW_CONTRIBUTION_COMMISSION_BPS', 300),

    // How long an unpaid order holds its ticket inventory before the reservation expires.
    'reservation_minutes' => (int) env('TICKETFLOW_RESERVATION_MINUTES', 15),

    // Local demo mode settles fake payments and payouts instantly. Keep this
    // disabled anywhere real money or a real payment provider is used.
    'demo_mode' => (bool) env('TICKETFLOW_DEMO_MODE', false),

    // Shared secret for POST /internal/cron, which runs the scheduler on hosts without cron.
    // Leave empty to switch the endpoint off.
    'cron_token' => env('CRON_TOKEN'),

    // Days after an event ends before organizer earnings become eligible for payout.
    'payout_settlement_days' => (int) env('TICKETFLOW_PAYOUT_SETTLEMENT_DAYS', 7),

    // How approved organizer payouts leave the platform. "paytota" sends Mobile Money
    // payouts through the Paytota disbursement API; "manual" means an admin pays them
    // outside TUVIO (and bank payouts are always manual) and records the reference.
    'payouts' => [
        'driver' => env('PAYOUT_DRIVER', 'manual'),
    ],

    // One-time codes emailed at sign-in, on top of the password (and the authenticator
    // app, where one is set up). Admins always get them; so do members of these plans.
    'email_login_codes' => [
        'enabled' => (bool) env('TICKETFLOW_EMAIL_LOGIN_CODES', true),
        'plans' => ['professional', 'enterprise'],
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'resend_seconds' => 60,
    ],

    'ussd' => [
        // The shortcode the USSD provider assigned, without * and #. Events add their own
        // code after it (*288*24#). Dial codes are not shown anywhere until this is set.
        'shortcode' => env('USSD_SHORTCODE'),
        // Telcos cut screens longer than this; replies are trimmed to fit.
        'max_screen_length' => (int) env('USSD_MAX_SCREEN_LENGTH', 182),
        // PEM public key the USSD gateway signs callbacks with; "\n" escapes are allowed in .env.
        'public_key' => str_replace('\n', "\n", (string) env('USSD_PUBLIC_KEY')),
        // How long a caller's place in the menu is kept between keypresses.
        'session_minutes' => (int) env('USSD_SESSION_MINUTES', 5),
        // Smallest contribution accepted over USSD, in major currency units.
        'minimum_contribution' => (int) env('USSD_MINIMUM_CONTRIBUTION', 1000),
    ],

    'payments' => [
        'default' => env('PAYMENT_PROVIDER', 'flutterwave'),
        'flutterwave' => [
            'public_key' => env('FLW_PUBLIC_KEY'),
            'secret_key' => env('FLW_SECRET_KEY'),
            'encryption_key' => env('FLW_ENCRYPTION_KEY'),
            'webhook_secret' => env('FLW_WEBHOOK_SECRET'),
            'base_url' => env('FLW_BASE_URL', 'https://api.flutterwave.com/v3'),
        ],
        'paytota' => [
            'brand_id' => env('PAYTOTA_BRAND_ID'),
            'api_key' => env('PAYTOTA_API_KEY'),
            // PEM public key used to verify callback signatures; "\n" escapes are allowed in .env.
            'public_key' => str_replace('\n', "\n", (string) env('PAYTOTA_PUBLIC_KEY')),
            'base_url' => env('PAYTOTA_BASE_URL', 'https://gate.paytota.com/api/v1/'),
            // Buyer country and dialing code sent with each purchase (Mobile Money routing).
            'country' => env('PAYTOTA_COUNTRY', 'UG'),
            'dialing_code' => env('PAYTOTA_DIALING_CODE', '256'),
            // Offer Mobile Money at checkout. Keep false until Paytota has enabled a
            // Mobile Money terminal for the brand, otherwise every attempt is cancelled.
            'mobile_money' => (bool) env('PAYTOTA_MOBILE_MONEY', true),
            // Payout status callbacks go to the webhook set up in the Paytota dashboard, which
            // may sign with its own key. Falls back to public_key when left empty.
            'webhook_public_key' => str_replace('\n', "\n", (string) env('PAYTOTA_WEBHOOK_PUBLIC_KEY')),
        ],
    ],

    // Text messages to buyers (tickets after payment). "log" only writes them to the log.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'africastalking' => [
            'username' => env('AT_USERNAME', 'sandbox'),
            'api_key' => env('AT_API_KEY'),
            // Registered alphanumeric sender ID, e.g. TUVIO. Leave empty to use AT's default.
            'sender_id' => env('AT_SENDER_ID'),
            'sandbox' => (bool) env('AT_SANDBOX', true),
        ],
        // Used to turn local numbers such as 0772123456 into +256772123456.
        'dialing_code' => env('SMS_DIALING_CODE', '256'),
    ],
];
