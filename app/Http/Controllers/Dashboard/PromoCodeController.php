<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PromoCodeController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('view', $org);

        $codes = $org->promoCodes()
            ->with('event:id,title')
            ->withSum('redemptions as discounted_minor', 'amount_discounted_minor')
            ->latest()->get()
            ->map(fn (PromoCode $p) => [
                'id' => $p->id, 'code' => $p->code, 'type' => $p->type,
                // Percent codes are stored in basis points; show them as whole percents.
                'value' => $p->type === 'percent' ? $p->value / 100 : $p->value,
                'currency' => $p->currency ?? $org->default_currency,
                'event' => $p->event?->title,
                'redeemed' => $p->redeemed_count, 'max' => $p->max_redemptions,
                'discounted_minor' => (int) $p->discounted_minor,
                'ends_at' => $p->ends_at?->toIso8601String(),
                'is_active' => $p->is_active, 'redeemable' => $p->isRedeemable(),
            ]);

        return Inertia::render('Dashboard/Discounts', [
            'codes' => $codes,
            'events' => $org->events()->orderBy('title')->get(['id', 'title']),
            'currency' => $org->default_currency,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $org = $this->organization($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash',
                Rule::unique('promo_codes')->where('organization_id', $org->id)->where(fn ($q) => $q->where('code', strtoupper($request->string('code')->value())))],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:1', $request->input('type') === 'percent' ? 'max:100' : 'max:100000000'],
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')->where('organization_id', $org->id)],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $org->promoCodes()->create([
            'code' => strtoupper($data['code']),
            'type' => $data['type'],
            // Percent → basis points; fixed → minor units of the org currency.
            'value' => (int) round($data['value'] * 100),
            'currency' => $data['type'] === 'fixed' ? $org->default_currency : null,
            'event_id' => $data['event_id'] ?? null,
            'max_redemptions' => $data['max_redemptions'] ?? null,
            'min_order_minor' => isset($data['min_order']) ? (int) round($data['min_order'] * 100) : null,
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        return back()->with('success', 'Promo code '.strtoupper($data['code']).' created.');
    }

    public function toggle(Request $request, PromoCode $promoCode): RedirectResponse
    {
        $org = $this->organization($request);
        abort_unless($promoCode->organization_id === $org->id, 404);

        $promoCode->update(['is_active' => ! $promoCode->is_active]);

        return back()->with('success', $promoCode->is_active ? 'Promo code activated.' : 'Promo code paused.');
    }

    public function destroy(Request $request, PromoCode $promoCode): RedirectResponse
    {
        $org = $this->organization($request);
        abort_unless($promoCode->organization_id === $org->id, 404);

        // Keep codes that were used so past orders still show where their discount came from.
        if ($promoCode->redemptions()->exists()) {
            $promoCode->update(['is_active' => false]);

            return back()->with('success', 'This code has been used, so it was paused instead of deleted.');
        }

        $promoCode->delete();

        return back()->with('success', 'Promo code deleted.');
    }

    private function organization(Request $request): Organization
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('manageMarketing', $org);

        return $org;
    }
}
