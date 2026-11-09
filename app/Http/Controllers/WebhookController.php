<?php

namespace App\Http\Controllers;

use App\Domain\Payments\PaymentService;
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
}
