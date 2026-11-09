<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\CheckIn\CheckInService;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventStaff;
use App\Models\Ticket;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckInController extends Controller
{
    public function __construct(private CheckInService $checkIn) {}

    /** List events the user can check guests into: their organization's, plus any they staff. */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $org = CurrentOrganization::resolve($request);
        $assignments = EventStaff::where('user_id', $user->id)
            ->whereHas('event', fn ($q) => $q->whereIn('status', ['published', 'completed']))
            ->with('event')
            ->get();

        if (! $org && $assignments->isEmpty()) {
            return redirect()->route('organizations.create');
        }

        $orgEvents = $org
            ? $org->events()->whereIn('status', ['published', 'completed'])->orderBy('start_at')->get()
            : collect();

        $row = fn (Event $e, string $access) => [
            'id' => $e->id, 'title' => $e->title, 'slug' => $e->slug, 'start_at' => $e->start_at?->toIso8601String(),
            'access' => $access,
            'can_manage' => $user->can('update', $e),
            'can_view_dashboard' => $user->can('viewCheckInDashboard', $e),
        ];

        return Inertia::render('Dashboard/CheckIn/Index', [
            'events' => $orgEvents->map(fn (Event $e) => $row($e, 'team'))
                ->concat($assignments->reject(fn ($a) => $orgEvents->contains('id', $a->event_id))->map(fn ($a) => $row($a->event, $a->role)))
                ->values(),
        ]);
    }

    public function scanner(Request $request, Event $event): Response
    {
        $this->authorize('checkIn', $event);

        return Inertia::render('Dashboard/CheckIn/Scanner', [
            'event' => ['id' => $event->id, 'title' => $event->title, 'slug' => $event->slug],
            'stats' => $this->computeStats($event),
            'gates' => $event->gates()->where('is_active', true)->pluck('name'),
            'canManage' => $request->user()->can('update', $event),
            'canViewDashboard' => $request->user()->can('viewCheckInDashboard', $event),
        ]);
    }

    public function scan(Request $request, Event $event): JsonResponse
    {
        $this->authorize('checkIn', $event);

        $data = $request->validate([
            'qr_token' => ['required_without:code', 'nullable', 'string'],
            'code' => ['nullable', 'string', 'max:40'],
            'client_uuid' => ['nullable', 'uuid'],
            'gate' => ['nullable', 'string', 'max:60'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'scanned_at' => ['nullable', 'date'],
        ]);

        // Staff may type the printed ticket code when a QR can't be read.
        $token = $data['qr_token'] ?? Ticket::where('event_id', $event->id)
            ->where('code', strtoupper(trim($data['code'])))
            ->value('qr_token') ?? 'unknown-code:'.$data['code'];

        $outcome = $this->checkIn->scan($event, $token, $request->user(), [
            'client_uuid' => $data['client_uuid'] ?? null,
            'gate' => $data['gate'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'scanned_at' => $data['scanned_at'] ?? null,
        ]);

        return response()->json([
            ...$outcome,
            'stats' => $this->computeStats($event),
        ]);
    }

    public function stats(Request $request, Event $event): JsonResponse
    {
        $this->authorize('checkIn', $event);

        return response()->json($this->computeStats($event));
    }

    private function computeStats(Event $event): array
    {
        $sold = $event->tickets()->whereIn('status', ['issued', 'used'])->count();
        $checkedIn = $event->tickets()->where('status', 'used')->count();

        return [
            'sold' => $sold,
            'checked_in' => $checkedIn,
            'remaining' => max(0, $sold - $checkedIn),
        ];
    }
}
