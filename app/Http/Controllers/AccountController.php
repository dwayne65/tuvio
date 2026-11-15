<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Ticket;
use App\Support\TicketDesign;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /** The digital ticket wallet. */
    public function tickets(Request $request): Response
    {
        $tickets = Ticket::query()
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('status', '!=', 'void')
            ->with(['event.organization', 'event.category', 'ticketType', 'orderItem'])
            ->latest()
            ->get()
            ->map(fn (Ticket $t) => [
                'code' => $t->code,
                'qr_token' => $t->qr_token,          // owner may see their own token
                'status' => $t->status,
                'attendee_name' => $t->attendee_name,
                'type' => $t->ticketType->name,
                'tier' => $t->ticketType->resolvedTier(),
                'number' => str_pad((string) $t->id, 6, '0', STR_PAD_LEFT),
                'design' => TicketDesign::forWallet($t),
                'perks' => $t->ticketType->perks ?? [],
                // What the attendee actually paid, falling back to the list price.
                'price_minor' => $t->orderItem?->unit_price_minor ?? $t->ticketType->price_minor,
                'currency' => $t->orderItem?->currency ?? $t->ticketType->currency,
                'used_at' => $t->used_at?->toIso8601String(),
                'event' => [
                    'title' => $t->event->title,
                    'slug' => $t->event->slug,
                    'cover_url' => $t->event->cover_url,
                    'start_at' => $t->event->start_at?->toIso8601String(),
                    'venue_name' => $t->event->venue_name,
                    'city' => $t->event->city,
                    'venue_address' => $t->event->venue_address,
                    'organizer' => $t->event->organization?->name,
                    'category' => $t->event->category?->name,
                    'type' => $t->event->type,
                ],
            ]);

        return Inertia::render('Account/Tickets', ['tickets' => $tickets]);
    }

    public function orders(Request $request): Response
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('event')
            ->latest()
            ->paginate(10)
            ->through(fn (Order $o) => [
                'reference' => $o->reference,
                'status' => $o->status,
                'total_minor' => $o->total_minor,
                'currency' => $o->currency,
                'created_at' => $o->created_at->toIso8601String(),
                'event' => ['title' => $o->event->title, 'slug' => $o->event->slug],
                'tickets_count' => $o->tickets()->count(),
            ]);

        return Inertia::render('Account/Orders', ['orders' => $orders]);
    }
}
