<?php

namespace App\Http\Controllers;

use App\Mail\SupportReceivedMail;
use App\Models\Order;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    /**
     * Public support request. When it names an order the buyer owns, it is
     * routed to that event's organizer; otherwise it goes to platform admins.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
            'order_reference' => ['nullable', 'string', 'max:40'],
        ]);

        $order = null;
        if (! empty($data['order_reference'])) {
            $order = Order::where('reference', strtoupper(trim($data['order_reference'])))
                ->whereRaw('lower(buyer_email) = ?', [strtolower($data['email'])])
                ->with('event')
                ->first();
        }

        $ticket = SupportTicket::create([
            'reference' => 'SUP-'.strtoupper(Str::random(6)),
            'organization_id' => $order?->event->organization_id,
            'event_id' => $order?->event_id,
            'order_id' => $order?->id,
            'user_id' => $request->user()?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
        ]);

        try {
            Mail::to($ticket->email, $ticket->name)->send(new SupportReceivedMail($ticket));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', "Thanks! Your request {$ticket->reference} was received — we'll reply by email.");
    }
}
