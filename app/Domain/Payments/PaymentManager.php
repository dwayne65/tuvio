<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Providers\FakeProvider;
use App\Domain\Payments\Providers\FlutterwaveProvider;
use App\Domain\Payments\Providers\PaytotaProvider;
use InvalidArgumentException;

/** Resolves a configured payment provider by name. */
class PaymentManager
{
    public function provider(?string $name = null): PaymentProvider
    {
        $name ??= config('ticketflow.payments.default');

        return match ($name) {
            'fake' => new FakeProvider,
            'flutterwave' => new FlutterwaveProvider(config('ticketflow.payments.flutterwave')),
            'paytota' => new PaytotaProvider(config('ticketflow.payments.paytota')),
            default => throw new InvalidArgumentException("Unknown payment provider [{$name}]."),
        };
    }
}
