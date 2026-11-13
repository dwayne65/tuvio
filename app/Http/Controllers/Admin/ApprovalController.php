<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Events\EventService;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Organization;
use App\Notifications\EventReviewedNotification;
use App\Notifications\OrganizationReviewedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** The admin approval queue: new organizer accounts and events waiting to go live. */
class ApprovalController extends Controller
{
    public function __construct(private EventService $events) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Approvals', [
            'organizations' => Organization::where('status', Organization::STATUS_PENDING)
                ->with('owner')->oldest()->get()
                ->map(fn (Organization $org) => [
                    'id' => $org->id,
                    'name' => $org->name,
                    'country' => $org->country,
                    'support_email' => $org->support_email,
                    'owner' => $org->owner ? [
                        'name' => $org->owner->name,
                        'email' => $org->owner->email,
                        'email_verified' => $org->owner->hasVerifiedEmail(),
                        'joined_at' => $org->owner->created_at->toIso8601String(),
                    ] : null,
                    'submitted_at' => $org->created_at->toIso8601String(),
                ]),
            'events' => Event::where('status', 'in_review')
                ->with(['organization', 'ticketTypes', 'category'])->oldest('review_requested_at')->get()
                ->map(fn (Event $event) => [
                    'id' => $event->id,
                    'slug' => $event->slug,
                    'title' => $event->title,
                    'description' => Str::limit(strip_tags((string) $event->description), 600),
                    'organization' => $event->organization->name,
                    'category' => $event->category?->name,
                    'type' => $event->type,
                    'visibility' => $event->visibility,
                    'start_at' => $event->start_at?->toIso8601String(),
                    'place' => collect([$event->venue_name, $event->city])->filter()->implode(', ') ?: ($event->online_url ? 'Online' : null),
                    'cover_url' => $event->cover_url,
                    'ticket_types' => $event->ticketTypes->map(fn ($type) => [
                        'name' => $type->name, 'price_minor' => $type->price_minor, 'quantity_total' => $type->quantity_total,
                    ]),
                    'currency' => $event->currency,
                    'submitted_at' => $event->review_requested_at?->toIso8601String(),
                ]),
        ]);
    }

    public function approveOrganization(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->isAwaitingApproval(), 422, 'This organizer is not waiting for approval.');

        $organization->forceFill([
            'status' => Organization::STATUS_ACTIVE,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'rejection_reason' => null,
        ])->save();

        $this->audit($request, 'admin.organization_approved', $organization);
        rescue(fn () => $organization->owner?->notify(new OrganizationReviewedNotification($organization, approved: true)));

        return back()->with('success', "{$organization->name} is approved. The organizer has been emailed.");
    }

    public function rejectOrganization(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->isAwaitingApproval(), 422, 'This organizer is not waiting for approval.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $organization->forceFill([
            'status' => Organization::STATUS_REJECTED,
            'approved_at' => null,
            'approved_by' => $request->user()->id,
            'rejection_reason' => $data['reason'],
        ])->save();

        $this->audit($request, 'admin.organization_rejected', $organization, $data['reason']);
        rescue(fn () => $organization->owner?->notify(new OrganizationReviewedNotification($organization, approved: false)));

        return back()->with('success', "{$organization->name} was rejected.");
    }

    public function approveEvent(Request $request, Event $event): RedirectResponse
    {
        try {
            $this->events->approve($event, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit($request, 'admin.event_approved', $event);
        rescue(fn () => $event->organization->owner?->notify(new EventReviewedNotification($event->fresh(), approved: true)));

        return back()->with('success', "\"{$event->title}\" is approved and live.");
    }

    public function rejectEvent(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $event = $this->events->reject($event, $request->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit($request, 'admin.event_rejected', $event, $data['reason']);
        rescue(fn () => $event->organization->owner?->notify(new EventReviewedNotification($event, approved: false)));

        return back()->with('success', "\"{$event->title}\" was sent back to the organizer.");
    }

    private function audit(Request $request, string $action, Organization|Event $subject, ?string $reason = null): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->id,
            'new_values' => array_filter(['reason' => $reason]),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);
    }
}
