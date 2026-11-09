<?php

namespace App\Domain\Contributions;

use App\Models\Campaign;
use App\Models\Contribution;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

class ContributionService
{
    public function create(Campaign $campaign, array $contributor, int $amountMinor, array $opts = [], ?User $user = null): Contribution
    {
        if ($campaign->status !== 'active') {
            throw new RuntimeException('This campaign is not accepting contributions.');
        }
        if ($amountMinor <= 0) {
            throw new RuntimeException('Enter a valid contribution amount.');
        }

        $bps = $campaign->commission_bps ?? config('ticketflow.contribution_commission_bps');
        $commission = (int) round($amountMinor * $bps / 10000);

        return $campaign->contributions()->create([
            'reference' => 'CTB-'.strtoupper(Str::random(8)),
            'user_id' => $user?->id,
            'contributor_name' => $opts['is_anonymous'] ?? false ? null : ($contributor['name'] ?? null),
            'contributor_email' => $contributor['email'] ?? null,
            'is_anonymous' => $opts['is_anonymous'] ?? false,
            'message' => $opts['message'] ?? null,
            'amount_minor' => $amountMinor,
            'currency' => $campaign->currency,
            'commission_minor' => $commission,
            'status' => 'pending',
        ]);
    }
}
