<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Payouts\PayoutService;
use App\Http\Controllers\Controller;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function __construct(private PayoutService $payouts) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        if (! $org) {
            return redirect()->route('organizations.create');
        }
        $this->authorize('manageFinances', $org);

        return Inertia::render('Dashboard/Payouts', [
            'available_minor' => $this->payouts->availableMinor($org),
            'currency' => $org->default_currency,
            'accounts' => $org->payoutAccounts()->get()->map(fn ($a) => [
                'id' => $a->id, 'type' => $a->type, 'provider' => $a->provider,
                'label' => $a->label, 'is_default' => $a->is_default,
                'verification_status' => $a->verification_status,
            ]),
            'payouts' => $org->payouts()->latest()->get()->map(fn ($p) => [
                'reference' => $p->reference, 'amount_minor' => $p->amount_minor,
                'currency' => $p->currency, 'status' => $p->status,
                'requested_at' => $p->requested_at?->toIso8601String(),
                'processed_at' => $p->processed_at?->toIso8601String(),
            ]),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('manageFinances', $org);

        $data = $request->validate([
            'type' => ['required', Rule::in(['mobile_money', 'bank'])],
            'provider' => ['nullable', 'string', 'max:60'],
            'label' => ['nullable', 'string', 'max:60'],
            'account_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'max:60'],
        ]);

        $isFirst = $org->payoutAccounts()->count() === 0;
        $org->payoutAccounts()->create([...$data, 'currency' => $org->default_currency, 'is_default' => $isFirst]);

        return back()->with('success', 'Payout account added.');
    }

    public function request(Request $request): RedirectResponse
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org, 403);
        $this->authorize('requestPayout', $org);

        if ($org->payoutAccounts()->count() === 0) {
            return back()->with('error', 'Add a payout account before requesting a payout.');
        }

        try {
            $payout = $this->payouts->request($org);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payout {$payout->reference} requested for review.");
    }
}
