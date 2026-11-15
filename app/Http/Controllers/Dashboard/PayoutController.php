<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Payouts\PayoutService;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationPayoutAccount;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'has_open_payout' => $org->payouts()->whereIn('status', PayoutService::OPEN_STATUSES)->exists(),
            'accounts' => $org->payoutAccounts()->orderByDesc('is_default')->get()->map(fn (OrganizationPayoutAccount $a) => [
                'id' => $a->id, 'type' => $a->type, 'provider' => $a->provider,
                'label' => $a->label, 'is_default' => $a->is_default,
                'account_name' => $a->account_name,
                'masked_number' => '•••• '.substr((string) $a->account_number, -4),
                'verification_status' => $a->verification_status,
            ]),
            'payouts' => $org->payouts()->with('payoutAccount')->latest()->get()->map(fn ($p) => [
                'reference' => $p->reference, 'amount_minor' => $p->amount_minor,
                'currency' => $p->currency, 'status' => $p->status,
                'destination' => $p->payoutAccount ? ($p->payoutAccount->provider ?? $p->payoutAccount->type).' •••• '.substr((string) $p->payoutAccount->account_number, -4) : null,
                // Only failed or rejected payouts explain themselves; in-flight notes are for admins.
                'reason' => in_array($p->status, ['failed', 'cancelled'], true) ? $p->notes : null,
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
            'provider' => ['required', 'string', 'max:60', Rule::when($request->input('type') === 'mobile_money', Rule::in(['MTN', 'Airtel']))],
            'label' => ['nullable', 'string', 'max:60'],
            'account_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'max:60'],
            'bank_code' => ['nullable', 'string', 'max:30'],
        ]);

        if ($data['type'] === 'mobile_money') {
            $data['account_number'] = $this->mobileMoneyNumber($data['account_number']);
            $data['bank_code'] = null;
        }

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

        // Payouts leave without an admin now, so moving money out needs the password again.
        $request->validate(['password' => ['required', 'current_password']]);

        try {
            $payout = $this->payouts->sendAutomatically($this->payouts->request($org));
        } catch (\RuntimeException $e) {
            return back()->with('error', $this->customerMessage($e));
        }

        return match ($payout->status) {
            'paid' => back()->with('success', "Payout {$payout->reference} has been paid."),
            'processing' => back()->with('success', "Payout {$payout->reference} is on its way to your Mobile Money. It usually arrives within minutes."),
            'failed' => back()->with('error', "Payout {$payout->reference} was declined: {$payout->notes} The money is available to request again."),
            default => back()->with('success', "Payout {$payout->reference} requested. Our team will send it shortly."),
        };
    }

    public function makeDefault(Request $request, OrganizationPayoutAccount $account): RedirectResponse
    {
        $org = $this->accountOrganization($request, $account);

        $org->payoutAccounts()->whereKeyNot($account->id)->update(['is_default' => false]);
        $account->update(['is_default' => true]);

        return back()->with('success', 'Default payout account updated.');
    }

    public function destroyAccount(Request $request, OrganizationPayoutAccount $account): RedirectResponse
    {
        $org = $this->accountOrganization($request, $account);

        if ($org->payouts()->where('organization_payout_account_id', $account->id)->whereIn('status', PayoutService::OPEN_STATUSES)->exists()) {
            return back()->with('error', 'This account has a payout in progress. Remove it once that payout is finished.');
        }

        $account->delete();

        if ($account->is_default) {
            $org->payoutAccounts()->oldest()->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Payout account removed.');
    }

    /** The current organization, provided it owns the account and the user manages its finances. */
    private function accountOrganization(Request $request, OrganizationPayoutAccount $account): Organization
    {
        $org = CurrentOrganization::resolve($request);
        abort_unless($org && $account->organization_id === $org->id, 404);
        $this->authorize('manageFinances', $org);

        return $org;
    }

    /**
     * Normalizes a Ugandan MTN/Airtel number to 2567XXXXXXXX, the form Paytota pays out to,
     * so a typo is caught here instead of as a failed transfer.
     */
    private function mobileMoneyNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);
        $dialingCode = config('ticketflow.payments.paytota.dialing_code', '256');
        $international = str_starts_with($digits, '0') ? $dialingCode.substr($digits, 1) : $digits;

        if (! preg_match('/^'.preg_quote($dialingCode, '/').'7\d{8}$/', $international)) {
            throw ValidationException::withMessages(['account_number' => 'Enter a valid Mobile Money number, e.g. 0772 123456.']);
        }

        return $international;
    }
}
