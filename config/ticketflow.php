<?php

return [
    // Default platform currency (ISO-4217). UGX for the primary (Uganda/Africa) market.
    'default_currency' => env('TICKETFLOW_CURRENCY', 'UGX'),

    // Default platform commission in basis points (500 = 5.00%).
    // Can be overridden per organization and per event.
    'commission_bps' => (int) env('TICKETFLOW_COMMISSION_BPS', 500),

    // Default commission on contributions/fundraising (configured independently of tickets).
    'contribution_commission_bps' => (int) env('TICKETFLOW_CONTRIBUTION_COMMISSION_BPS', 300),

    // How long an unpaid order holds its ticket inventory before the reservation expires.
    'reservation_minutes' => (int) env('TICKETFLOW_RESERVATION_MINUTES', 15),

    // Local demo mode settles fake payments and payouts instantly. Keep this
    // disabled anywhere real money or a real payment provider is used.
    'demo_mode' => (bool) env('TICKETFLOW_DEMO_MODE', false),

    // Days after an event ends before organizer earnings become eligible for payout.
    'payout_settlement_days' => (int) env('TICKETFLOW_PAYOUT_SETTLEMENT_DAYS', 7),

    'payments' => [
        'default' => env('PAYMENT_PROVIDER', 'flutterwave'),
        'flutterwave' => [
            'public_key' => env('FLW_PUBLIC_KEY'),
            'secret_key' => env('FLW_SECRET_KEY'),
            'encryption_key' => env('FLW_ENCRYPTION_KEY'),
            'webhook_secret' => env('FLW_WEBHOOK_SECRET'),
            'base_url' => env('FLW_BASE_URL', 'https://api.flutterwave.com/v3'),
        ],
    ],
];
