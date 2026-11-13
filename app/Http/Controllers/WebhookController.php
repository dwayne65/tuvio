<?php

namespace App\Http\Controllers;

use App\Domain\Payments\PaymentService;
use App\Domain\Payouts\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function flutterwave(Request $request): Response
    {
        $this->payments->handleWebhook(
            'flutterwave',
            $request->getContent(),
            $request->header('verif-hash'),
            $request->all(),
        );

        // Always 200 so the provider stops retrying a delivery we've recorded.
        return response('ok', 200);
    }

    public function paytota(Request $request, PayoutService $payouts): Response
    {
        // Payout (disbursement) status callbacks share this URL with purchase callbacks.
        if ($request->input('type') === 'payout' || str_starts_with((string) $request->input('event_type'), 'payout.')) {
            $payouts->handlePaytotaCallback(
                $request->getContent(),
                $request->header('X-Signature') ?? $request->header('Signature'),
                $request->all(),
            );

            return response('ok', 200);
        }

        $this->payments->handleWebhook(
            'paytota',
            $request->getContent(),
            $request->header('X-Signature') ?? $request->header('Signature'),
            $request->all(),
        );

        return response('ok', 200);
    }
}
