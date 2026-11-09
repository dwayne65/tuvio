<?php

namespace App\Console\Commands;

use App\Domain\Orders\OrderService;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:release-expired')]
#[Description('Return ticket holds from unpaid orders whose reservation window has passed')]
class ReleaseExpiredReservations extends Command
{
    public function handle(OrderService $orders): int
    {
        $released = 0;

        Order::where('status', 'awaiting_payment')
            ->where('reserved_until', '<', now())
            ->with('items')
            ->chunkById(100, function ($expired) use ($orders, &$released) {
                foreach ($expired as $order) {
                    $orders->releaseReservation($order);
                    $released++;
                }
            });

        $this->info("Released {$released} expired reservation(s).");

        return self::SUCCESS;
    }
}
