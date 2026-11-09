<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\CheckIn\CheckInService;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckInController extends Controller
{
    public function __construct(private CheckInService $checkIn) {}

    /** List events the organizer can check guests into. */
    public function index(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }

        return Inertia::render('Dashboard/CheckIn/Index', [
            'events' => $org->events()->whereIn('status', ['published', 'completed'])
                ->orderBy('start_at')->get()
                ->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'slug' => $e->slug, 'start_at' => $e->start_at?->toIso8601String()]),
        ]);
    }

    public function scanner(Request $request, Event $event): Response
    {
        $this->authorize('checkIn', $event);

        return Inertia::render('Dashboard/CheckIn/Scanner', [
            'event' => ['id' => $event->id, 'title' => $event->title, 'slug' => $event->slug],
            'stats' => $this->computeStats($event),
        ]);
    }

    public function scan(Request $request, Event $event): JsonResponse
    {
        $this->authorize('checkIn', $event);

        $data = $request->validate([
            'qr_token' => ['required', 'string'],
            'client_uuid' => ['nullable', 'uuid'],
            'gate' => ['nullable', 'string', 'max:60'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'scanned_at' => ['nullable', 'date'],
        ]);

        $outcome = $this->checkIn->scan($event, $data['qr_token'], $request->user(), [
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
