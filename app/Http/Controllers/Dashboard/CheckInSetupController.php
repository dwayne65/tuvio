<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventGate;
use App\Models\EventStaff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entrances and gate staff for an event. Event staff only ever get scanner
 * access (and managers the live dashboard) — never finances or settings.
 */
class CheckInSetupController extends Controller
{
    public function show(Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('Dashboard/CheckIn/Setup', [
            'event' => ['id' => $event->id, 'title' => $event->title, 'slug' => $event->slug, 'start_at' => $event->start_at?->toIso8601String()],
            'gates' => $event->gates()->get()->map(fn (EventGate $gate) => [
                'id' => $gate->id, 'name' => $gate->name, 'is_active' => $gate->is_active,
                'checked_in' => $event->checkIns()->where('result', 'valid')->where('gate', $gate->name)->count(),
            ]),
            'staff' => $event->staff()->with('user')->latest()->get()->map(fn (EventStaff $member) => [
                'id' => $member->id, 'role' => $member->role,
                'name' => $member->user->name, 'email' => $member->user->email,
                'scans' => $event->checkIns()->where('scanned_by', $member->user_id)->count(),
            ]),
            'organizationMembers' => $event->organization->members()->count(),
        ]);
    }

    public function storeGate(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('event_gates')->where('event_id', $event->id)],
        ], ['name.unique' => 'This event already has an entrance with that name.']);

        $gate = $event->gates()->create([...$data, 'sort' => (int) $event->gates()->max('sort') + 1]);
        $this->audit($request, 'event.gate_added', $gate, new: ['name' => $gate->name]);

        return back()->with('success', "“{$gate->name}” added.");
    }

    public function updateGate(Request $request, Event $event, EventGate $gate): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($gate->event_id === $event->id, 404);

        $gate->update($request->validate(['is_active' => ['required', 'boolean']]));

        return back()->with('success', $gate->is_active ? "“{$gate->name}” is open." : "“{$gate->name}” is closed.");
    }

    public function destroyGate(Request $request, Event $event, EventGate $gate): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($gate->event_id === $event->id, 404);

        // Past check-ins keep the gate name, so history is never lost.
        $gate->delete();
        $this->audit($request, 'event.gate_removed', $gate, old: ['name' => $gate->name]);

        return back()->with('success', 'Entrance removed.');
    }

    public function storeStaff(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['scanner', 'manager'])],
        ]);

        $user = User::whereRaw('lower(email) = ?', [strtolower($data['email'])])->first();
        if (! $user) {
            return back()->withErrors(['email' => 'No TicketFlow account uses this email. Ask them to sign up (it’s free), then add them again.']);
        }
        if ($user->belongsToOrganization($event->organization)) {
            return back()->withErrors(['email' => 'This person is already on your team and can scan every event.']);
        }

        $member = $event->staff()->updateOrCreate(
            ['user_id' => $user->id],
            ['role' => $data['role'], 'assigned_by' => $request->user()->id],
        );
        $this->audit($request, 'event.staff_assigned', $member, new: ['user' => $user->email, 'role' => $data['role']]);

        return back()->with('success', "{$user->name} can now ".($data['role'] === 'manager' ? 'scan and view the live dashboard.' : 'scan tickets for this event.'));
    }

    public function destroyStaff(Request $request, Event $event, EventStaff $staff): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($staff->event_id === $event->id, 404);

        $staff->delete();
        $this->audit($request, 'event.staff_removed', $staff, old: ['user_id' => $staff->user_id, 'role' => $staff->role]);

        return back()->with('success', 'Access removed.');
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(Request $request, string $action, Model $subject, ?array $old = null, ?array $new = null): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
