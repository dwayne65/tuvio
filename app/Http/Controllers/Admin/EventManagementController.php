<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Platform-wide view of every event, with the ability to remove any of them. */
class EventManagementController extends Controller
{
    use RecordsAdminAudit;

    public function index(Request $request): Response
    {
        $search = $request->string('q')->value();

        return Inertia::render('Admin/Events', [
            'events' => Event::withTrashed()
                ->with(['organization:id,name'])
                ->withCount(['orders as paid_orders_count' => fn ($q) => $q->where('status', 'paid')])
                ->withSum(['orders as revenue_minor' => fn ($q) => $q->where('status', 'paid')], 'total_minor')
                ->withCount(['tickets as tickets_sold' => fn ($q) => $q->whereIn('status', ['issued', 'used'])])
                ->when($search !== '', fn ($q) => $q->where('title', 'ilike', "%{$search}%"))
                ->latest('start_at')
                ->paginate(20)
                ->through(fn (Event $event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'organization' => $event->organization?->name,
                    'status' => $event->status,
                    'start_at' => $event->start_at?->toIso8601String(),
                    'currency' => $event->currency,
                    'tickets_sold' => (int) $event->tickets_sold,
                    'paid_orders' => (int) $event->paid_orders_count,
                    'revenue_minor' => (int) $event->revenue_minor,
                    'removed_at' => $event->deleted_at?->toIso8601String(),
                ]),
            'filters' => ['q' => $search],
        ]);
    }

    /**
     * Removes an event from the site, live or not. The row is soft-deleted so buyers' tickets,
     * orders and the ledger still resolve. Nothing is refunded automatically.
     */
    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $soldTickets = Ticket::where('event_id', $event->id)->whereIn('status', ['issued', 'used'])->count();

        $this->recordAdminAction($request, 'admin.event_removed', $event, null, [
            'title' => $event->title,
            'status' => $event->status,
            'tickets_sold' => $soldTickets,
        ]);

        $event->delete();

        $message = $soldTickets > 0
            ? "\"{$event->title}\" was removed. It had {$soldTickets} tickets sold; refunds are not automatic, so handle them from Transactions."
            : "\"{$event->title}\" was removed.";

        return back()->with('success', $message);
    }
}
