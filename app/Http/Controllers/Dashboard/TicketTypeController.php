<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketTypeController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $data = $this->validateType($request);
        $event->ticketTypes()->create([
            ...$data,
            'currency' => $event->currency,
            'sort' => $event->ticketTypes()->max('sort') + 1,
        ]);

        return back()->with('success', 'Ticket type added.');
    }

    public function update(Request $request, Event $event, TicketType $ticketType): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($ticketType->event_id === $event->id, 404);

        $data = $this->validateType($request);

        // Never allow total capacity below what has already been sold/reserved.
        if (($data['quantity_total'] ?? null) !== null) {
            $committed = $ticketType->quantity_sold + $ticketType->quantity_reserved;
            abort_if($data['quantity_total'] < $committed, 422, "Capacity cannot be below {$committed} already committed.");
        }

        $ticketType->update($data);

        return back()->with('success', 'Ticket type updated.');
    }

    public function destroy(Request $request, Event $event, TicketType $ticketType): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($ticketType->event_id === $event->id, 404);
        abort_if($ticketType->quantity_sold > 0, 422, 'Cannot delete a ticket type that has sales.');

        $ticketType->delete();

        return back()->with('success', 'Ticket type removed.');
    }

    private function validateType(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['free', 'paid'])],
            'tier' => ['nullable', Rule::in(TicketType::TIERS)],
            'description' => ['nullable', 'string', 'max:500'],
            'perks' => ['nullable', 'array', 'max:8'],
            'perks.*' => ['nullable', 'string', 'max:60'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after:sales_start_at'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'quantity_total' => ['nullable', 'integer', 'min:1'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::in(['active', 'paused', 'hidden'])],
        ]);

        // A free ticket never carries a price.
        if ($data['kind'] === 'free') {
            $data['price_minor'] = 0;
        }

        $data['perks'] = array_values(array_filter(array_map(fn ($perk) => trim((string) $perk), $data['perks'] ?? [])));

        return $data;
    }
}
