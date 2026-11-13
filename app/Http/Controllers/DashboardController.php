<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Ticket;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Platform admins have their own workspace.
        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        $org = CurrentOrganization::resolve($request);

        return Inertia::render('Dashboard', [
            'isOrganizer' => $user->isOrganizer(),
            'organization' => $org ? [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'verification_status' => $org->verification_status,
                'status' => $org->status,
                'rejection_reason' => $org->status === 'rejected' ? $org->rejection_reason : null,
                'has_subscription' => $org->hasActiveSubscription(),
            ] : null,
            'metrics' => $org ? [
                'events' => $org->events()->count(),
                'published' => $org->events()->where('status', 'published')->count(),
                'in_review' => $org->events()->where('status', 'in_review')->count(),
                'tickets_sold' => Ticket::whereHas('event', fn ($q) => $q->where('organization_id', $org->id))
                    ->whereIn('status', ['issued', 'used'])->count(),
                'revenue_minor' => (int) Order::where('status', 'paid')
                    ->whereHas('event', fn ($q) => $q->where('organization_id', $org->id))->sum('total_minor'),
            ] : null,
            'recentEvents' => $org ? $org->events()->withCount(['tickets as sold' => fn ($q) => $q->whereIn('status', ['issued', 'used'])])
                ->latest()->limit(5)->get()
                ->map(fn ($e) => ['title' => $e->title, 'slug' => $e->slug, 'status' => $e->status, 'sold' => $e->sold, 'start_at' => $e->start_at?->toIso8601String()]) : [],
        ]);
    }
}
