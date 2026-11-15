<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promoter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Every promoter link on the platform, with the power to pause or remove any of them. */
class PromoterController extends Controller
{
    use RecordsAdminAudit;

    public function index(Request $request): Response
    {
        $paid = fn ($q) => $q->where('status', 'paid');
        $search = $request->string('q')->value();

        return Inertia::render('Admin/Promoters', [
            'promoters' => Promoter::with(['organization:id,name', 'event:id,title'])
                ->withCount(['orders as orders_count' => $paid])
                ->withSum(['orders as revenue_minor' => $paid], 'total_minor')
                ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                    ->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")))
                ->latest()
                ->paginate(20)
                ->through(fn (Promoter $promoter) => [
                    'id' => $promoter->id,
                    'name' => $promoter->name,
                    'email' => $promoter->email,
                    'code' => $promoter->code,
                    'organization' => $promoter->organization?->name,
                    'event' => $promoter->event?->title,
                    'commission_percent' => $promoter->commission_bps / 100,
                    'clicks' => $promoter->clicks,
                    'orders' => (int) $promoter->orders_count,
                    'revenue_minor' => (int) $promoter->revenue_minor,
                    'is_active' => $promoter->is_active,
                ]),
            'filters' => ['q' => $search],
        ]);
    }

    public function toggle(Request $request, Promoter $promoter): RedirectResponse
    {
        $promoter->update(['is_active' => ! $promoter->is_active]);
        $this->recordAdminAction($request, $promoter->is_active ? 'admin.promoter_activated' : 'admin.promoter_paused', $promoter, null, ['code' => $promoter->code]);

        return back()->with('success', $promoter->is_active ? "Promoter {$promoter->code} is active again." : "Promoter {$promoter->code} is paused.");
    }

    public function destroy(Request $request, Promoter $promoter): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        // Orders keep their sale but lose the attribution (FK is null-on-delete).
        $this->recordAdminAction($request, 'admin.promoter_removed', $promoter, null, ['code' => $promoter->code, 'name' => $promoter->name]);
        $promoter->delete();

        return back()->with('success', "Promoter {$promoter->code} was removed.");
    }
}
