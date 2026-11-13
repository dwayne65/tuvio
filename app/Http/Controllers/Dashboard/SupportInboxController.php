<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Mail\SupportReplyMail;
use App\Models\SupportTicket;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Support inbox. Organizers see requests about their own events; platform
 * admins (the admin.* routes) see everything, including general requests.
 */
class SupportInboxController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $query = $this->scope($request);
        if (! $query) {
            return redirect()->route('organizations.create');
        }

        $status = $request->string('status')->value() ?: 'open';

        $tickets = (clone $query)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with('event:id,title')
            ->latest()->paginate(15)->withQueryString()
            ->through(fn (SupportTicket $t) => [
                'reference' => $t->reference, 'name' => $t->name, 'email' => $t->email,
                'subject' => $t->subject, 'message' => $t->message,
                'event' => $t->event?->title, 'status' => $t->status,
                'reply' => $t->reply, 'replied_at' => $t->replied_at?->toIso8601String(),
                'at' => $t->created_at->toIso8601String(),
            ]);

        return Inertia::render('Support/Inbox', [
            'tickets' => $tickets,
            'counts' => [
                'open' => (clone $query)->where('status', 'open')->count(),
                'answered' => (clone $query)->where('status', 'answered')->count(),
                'closed' => (clone $query)->where('status', 'closed')->count(),
            ],
            'filters' => ['status' => $status],
            'admin' => $request->routeIs('admin.*'),
        ]);
    }

    public function reply(Request $request, string $reference): RedirectResponse
    {
        $ticket = $this->find($request, $reference);
        $data = $request->validate(['reply' => ['required', 'string', 'max:5000']]);

        $ticket->update([
            'reply' => $data['reply'],
            'status' => 'answered',
            'replied_by' => $request->user()->id,
            'replied_at' => now(),
        ]);

        try {
            Mail::to($ticket->email, $ticket->name)->send(new SupportReplyMail($ticket->fresh('organization')));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', "Reply sent to {$ticket->email}.");
    }

    public function close(Request $request, string $reference): RedirectResponse
    {
        $ticket = $this->find($request, $reference);
        $ticket->update(['status' => $ticket->status === 'closed' ? 'open' : 'closed']);

        return back()->with('success', $ticket->status === 'closed' ? 'Request closed.' : 'Request reopened.');
    }

    private function find(Request $request, string $reference): SupportTicket
    {
        $query = $this->scope($request);
        abort_unless($query, 403);

        return $query->where('reference', $reference)->firstOrFail();
    }

    /** @return Builder<SupportTicket>|null */
    private function scope(Request $request): ?Builder
    {
        // Admin routes are already behind the `admin` middleware.
        if ($request->routeIs('admin.*')) {
            return SupportTicket::query();
        }

        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return null;
        }
        $this->authorize('view', $org);

        return SupportTicket::where('organization_id', $org->id);
    }
}
