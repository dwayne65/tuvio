<?php

namespace App\Http\Controllers;

use App\Domain\Contributions\ContributionService;
use App\Domain\Payments\PaymentService;
use App\Models\Campaign;
use App\Models\Contribution;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ContributionController extends Controller
{
    public function __construct(
        private ContributionService $contributions,
        private PaymentService $payments,
    ) {}

    public function show(Campaign $campaign): Response
    {
        abort_unless($campaign->status === 'active', 404);
        $campaign->load('organization');

        return Inertia::render('Campaigns/Show', [
            'campaign' => [
                'title' => $campaign->title, 'slug' => $campaign->slug,
                'description' => $campaign->description, 'cover_image_path' => $campaign->cover_image_path,
                'goal_minor' => $campaign->goal_minor, 'raised_minor' => $campaign->raised_minor,
                'currency' => $campaign->currency, 'contributors_count' => $campaign->contributors_count,
                'progress' => $campaign->progressPercent(),
                'allow_anonymous' => $campaign->allow_anonymous,
                'deadline_at' => $campaign->deadline_at?->toIso8601String(),
                'organization' => ['name' => $campaign->organization->name],
                'suggested' => $campaign->suggested_amounts_minor ?? [1000000, 5000000, 10000000],
            ],
        ]);
    }

    public function contribute(Request $request, Campaign $campaign): SymfonyResponse
    {
        $data = $request->validate([
            'amount_major' => ['required', 'numeric', 'min:1'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:500'],
            'is_anonymous' => ['boolean'],
        ]);

        try {
            $contribution = $this->contributions->create(
                $campaign,
                ['name' => $data['name'] ?? null, 'email' => $data['email'] ?? null],
                (int) round($data['amount_major'] * 100),
                ['is_anonymous' => $data['is_anonymous'] ?? false, 'message' => $data['message'] ?? null],
                $request->user(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $this->customerMessage($e));
        }

        $intent = $this->payments->initiateForContribution(
            $contribution,
            route('contribute.callback', $contribution->reference),
        );

        if (config('ticketflow.demo_mode') && config('ticketflow.payments.default') === 'fake') {
            $this->payments->verifyAndFulfill(
                Payment::where('contribution_id', $contribution->id)->latest()->firstOrFail(),
            );

            return redirect()->route('contribute.complete', $contribution->reference)
                ->with('success', 'Demo payment confirmed!');
        }

        return Inertia::location($intent->redirectUrl);
    }

    public function callback(Request $request, string $reference): RedirectResponse
    {
        $contribution = Contribution::where('reference', $reference)->firstOrFail();
        $payment = Contribution::where('reference', $reference)->first()->payment
            ?? Payment::where('contribution_id', $contribution->id)->latest()->firstOrFail();

        $ok = $this->payments->verifyAndFulfill($payment);

        return redirect()->route('contribute.complete', $reference)
            ->with($ok ? 'success' : 'error', $ok ? 'Thank you for your contribution!' : 'Payment was not completed.');
    }

    public function complete(string $reference): Response
    {
        $contribution = Contribution::where('reference', $reference)->with('campaign')->firstOrFail();

        return Inertia::render('Campaigns/Complete', [
            'contribution' => [
                'reference' => $contribution->reference,
                'status' => $contribution->status,
                'amount_minor' => $contribution->amount_minor,
                'currency' => $contribution->currency,
                'receipt_number' => $contribution->receipt_number,
                'campaign' => ['title' => $contribution->campaign->title, 'slug' => $contribution->campaign->slug],
            ],
        ]);
    }
}
