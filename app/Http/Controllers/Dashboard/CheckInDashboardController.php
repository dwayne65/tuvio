<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\CheckIn\CheckInStats;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Inertia\Inertia;
use Inertia\Response;

/** Live attendance dashboard for the event day (polled by the page every few seconds). */
class CheckInDashboardController extends Controller
{
    public function show(Event $event, CheckInStats $stats): Response
    {
        $this->authorize('viewCheckInDashboard', $event);

        return Inertia::render('Dashboard/CheckIn/Live', [
            'event' => ['id' => $event->id, 'title' => $event->title, 'slug' => $event->slug, 'start_at' => $event->start_at?->toIso8601String()],
            'stats' => fn () => $stats->forEvent($event),
            'canManage' => request()->user()->can('update', $event),
            'updatedAt' => fn () => now()->toIso8601String(),
        ]);
    }
}
