<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Verified-buyer review: the order reference + email prove purchase, so
     * guest buyers (no account) can review too. One review per order.
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = Order::query()
            ->where('event_id', $event->id)
            ->where('reference', strtoupper(trim($data['reference'])))
            ->where('status', 'paid')
            ->whereRaw('lower(buyer_email) = ?', [strtolower($data['email'])])
            ->first();

        if (! $order) {
            return back()->withErrors(['reference' => 'We couldn’t find a paid order with that reference and email for this event.']);
        }

        $attended = $order->tickets()->where('status', 'used')->exists();
        if (! $attended && ($event->start_at === null || $event->start_at->isFuture())) {
            return back()->withErrors(['reference' => 'Reviews open once the event starts or your ticket is scanned at the gate.']);
        }

        if ($order->review()->exists()) {
            return back()->withErrors(['reference' => 'This order has already been reviewed. Thank you!']);
        }

        $order->review()->create([
            'event_id' => $event->id,
            'user_id' => $order->user_id,
            'name' => $order->buyer_name,
            'rating' => $data['rating'],
            'body' => $data['body'] ?? null,
        ]);

        return back()->with('success', 'Thanks for your review!');
    }
}
