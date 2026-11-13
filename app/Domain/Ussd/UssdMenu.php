<?php

namespace App\Domain\Ussd;

use App\Domain\Contributions\ContributionService;
use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The TUVIO USSD menu: buy tickets, look up tickets bought with this phone number,
 * and contribute to campaigns. Each session's progress lives in the cache, keyed by
 * the gateway's session id, because every keypress arrives as a separate request.
 *
 * Mobile Money prompts are sent after the USSD reply: a handset cannot show the PIN
 * prompt while a USSD session is still open on it.
 */
class UssdMenu
{
    private const LIST_SIZE = 5;

    private const MAX_QUANTITY = 10;

    public function __construct(
        private OrderService $orders,
        private ContributionService $contributions,
        private PaymentService $payments,
    ) {}

    /**
     * @param  array{sessionid:string,msisdn:string,state:string,message?:?string}  $request
     */
    public function handle(array $request): UssdReply
    {
        $key = 'ussd:'.$request['sessionid'];
        $session = $request['state'] === 'START' ? null : Cache::get($key);

        // A session id belongs to the caller who opened it; anything else starts over.
        if (! $session || $session['msisdn'] !== $request['msisdn']) {
            $session = ['msisdn' => $request['msisdn'], 'step' => 'main'];
            $reply = $this->mainMenu();
        } else {
            $reply = $this->advance($session, $this->lastInput($request['message'] ?? ''));
        }

        if ($reply->state === 'END') {
            Cache::forget($key);
        } else {
            Cache::put($key, $session, now()->addMinutes(config('ticketflow.ussd.session_minutes')));
        }

        return $reply;
    }

    /**
     * Upcoming events their organizers switched on for USSD. The plan limit is applied here
     * too, so an organizer who downgrades keeps only as many events on USSD as the new
     * plan allows (the soonest ones), and none on a plan without USSD.
     *
     * @return Collection<int, Event>
     */
    private function eventsOnUssd(): Collection
    {
        return Event::public()->upcoming()
            ->where('sell_on_ussd', true)
            ->whereHas('ticketTypes', fn ($query) => $query->where('status', 'active')->where('is_hidden', false))
            ->with('organization')
            ->orderBy('start_at')
            ->get()
            ->groupBy('organization_id')
            ->flatMap(function (Collection $events) {
                $plan = $events->first()->organization->currentPlan();
                $limit = $plan ? $plan->ussd_event_limit : 0;

                return $limit === null ? $events : $events->take($limit);
            })
            ->sortBy('start_at')
            ->values();
    }

    /** Route the caller's input to the screen they are on. The session is updated in place. */
    private function advance(array &$session, string $input): UssdReply
    {
        return match ($session['step']) {
            'main' => $this->fromMainMenu($session, $input),
            'event' => $this->fromEventList($session, $input),
            'ticket_type' => $this->fromTicketTypeList($session, $input),
            'quantity' => $this->fromQuantity($session, $input),
            'buyer_name' => $this->fromBuyerName($session, $input),
            'confirm_order' => $this->fromOrderConfirmation($session, $input),
            'campaign' => $this->fromCampaignList($session, $input),
            'amount' => $this->fromAmount($session, $input),
            'contributor_name' => $this->fromContributorName($session, $input),
            'confirm_contribution' => $this->fromContributionConfirmation($session, $input),
            default => $this->mainMenu(),
        };
    }

    private function mainMenu(string $notice = ''): UssdReply
    {
        return UssdReply::continue($notice."Welcome to TUVIO\n1. Buy tickets\n2. My tickets\n3. Contribute");
    }

    private function fromMainMenu(array &$session, string $input): UssdReply
    {
        return match ($input) {
            '1' => $this->showEvents($session),
            '2' => $this->myTickets($session['msisdn']),
            '3' => $this->showCampaigns($session),
            default => $this->mainMenu("Invalid choice.\n"),
        };
    }

    private function showEvents(array &$session): UssdReply
    {
        $events = $this->eventsOnUssd()->take(self::LIST_SIZE);

        if ($events->isEmpty()) {
            return UssdReply::end('There are no events on sale right now. Please check again soon.');
        }

        $session['step'] = 'event';
        $session['options'] = $events->pluck('id')->all();

        return UssdReply::continue("Choose an event\n".$this->numbered(
            $events->map(fn (Event $event) => Str::limit($event->title, 22).' '.$event->start_at->format('j M'))
        )."\n0. Back");
    }

    private function fromEventList(array &$session, string $input): UssdReply
    {
        if ($input === '0') {
            $session['step'] = 'main';

            return $this->mainMenu();
        }

        $event = $this->chosen($session, $input, fn (int $id) => $this->eventsOnUssd()->firstWhere('id', $id));
        if (! $event) {
            return $this->showEvents($session);
        }

        $types = $event->ticketTypes()->orderBy('price_minor')->get()
            ->filter(fn (TicketType $type) => $type->isOnSale())
            ->take(self::LIST_SIZE)
            ->values();

        if ($types->isEmpty()) {
            return UssdReply::end("Tickets for {$event->title} are not on sale right now.");
        }

        $session['step'] = 'ticket_type';
        $session['event_id'] = $event->id;
        $session['options'] = $types->pluck('id')->all();

        return UssdReply::continue(Str::limit($event->title, 30)."\n".$this->numbered(
            $types->map(fn (TicketType $type) => Str::limit($type->name, 18).' '.$this->money($type->price_minor, $type->currency))
        )."\n0. Back");
    }

    private function fromTicketTypeList(array &$session, string $input): UssdReply
    {
        if ($input === '0') {
            return $this->showEvents($session);
        }

        $type = $this->chosen($session, $input, fn (int $id) => TicketType::where('event_id', $session['event_id'])->find($id));
        if (! $type?->isOnSale()) {
            return UssdReply::continue("Invalid choice.\n0. Back");
        }

        $session['step'] = 'quantity';
        $session['ticket_type_id'] = $type->id;

        return UssdReply::continue("How many tickets? (1-{$this->maxQuantity($type)})");
    }

    private function fromQuantity(array &$session, string $input): UssdReply
    {
        $type = TicketType::findOrFail($session['ticket_type_id']);
        $max = $this->maxQuantity($type);

        if (! ctype_digit($input) || (int) $input < 1 || (int) $input > $max) {
            return UssdReply::continue("Enter a number from 1 to {$max}.");
        }

        $session['step'] = 'buyer_name';
        $session['quantity'] = (int) $input;

        return UssdReply::continue('Enter your full name');
    }

    private function fromBuyerName(array &$session, string $input): UssdReply
    {
        if (mb_strlen($input) < 2) {
            return UssdReply::continue('Enter your full name');
        }

        $session['step'] = 'confirm_order';
        $session['name'] = Str::limit($input, 120, '');

        $type = TicketType::with('event')->findOrFail($session['ticket_type_id']);
        $subtotal = $type->price_minor * $session['quantity'];
        $total = $subtotal + $type->event->buyerCharges($subtotal);

        return UssdReply::continue(Str::limit($type->event->title, 30)."\n"
            ."{$session['quantity']} x ".Str::limit($type->name, 20)."\n"
            .'Total '.$this->money($total, $type->currency)."\n"
            .($total > 0 ? "1. Pay with Mobile Money\n2. Cancel" : "1. Confirm\n2. Cancel"));
    }

    private function fromOrderConfirmation(array &$session, string $input): UssdReply
    {
        if ($input !== '1') {
            return UssdReply::end('Cancelled. No tickets were reserved.');
        }

        $type = TicketType::with('event')->findOrFail($session['ticket_type_id']);
        if (! $type->isFree() && ! $this->mobileMoneyAvailable()) {
            return UssdReply::end('Mobile Money is not available right now. Please try again later or buy on tuvio.org.');
        }

        try {
            $order = $this->orders->create(
                $type->event,
                ['name' => $session['name'], 'email' => null, 'phone' => '+'.$session['msisdn']],
                [['ticket_type_id' => $type->id, 'quantity' => $session['quantity']]],
            );
        } catch (RuntimeException $e) {
            return UssdReply::end($e->getMessage());
        }

        if ($order->total_minor <= 0) {
            $this->payments->fulfillFree($order);

            return UssdReply::end("Your tickets are confirmed. Order {$order->reference}.\nDial again and choose My tickets to see your ticket codes.");
        }

        dispatch(function () use ($order) {
            $this->payments->initiateForOrder($order, route('checkout.callback', $order->reference), method: 'mobile_money');
            $this->settleDemoPayment(Payment::where('order_id', $order->id)->latest('id')->first());
        })->afterResponse();

        return UssdReply::end('Approve the Mobile Money prompt of '.$this->money($order->total_minor, $order->currency)
            ." with your PIN. Order {$order->reference}.\nThen dial again and choose My tickets.");
    }

    /** Tickets for upcoming events bought with this number, plus any purchase still awaiting payment. */
    private function myTickets(string $msisdn): UssdReply
    {
        $orderIds = Order::query()
            ->whereIn(DB::raw("replace(replace(replace(buyer_phone, ' ', ''), '-', ''), '+', '')"), $this->phoneVariants($msisdn))
            ->pluck('id');

        $tickets = Ticket::with(['event', 'ticketType'])
            ->whereIn('order_id', $orderIds)
            ->where('status', 'issued')
            ->whereHas('event', fn ($query) => $query->where('start_at', '>=', now()->subDay()))
            ->get()
            ->sortBy(fn (Ticket $ticket) => $ticket->event->start_at)
            ->take(self::LIST_SIZE);

        $pending = Order::whereIn('id', $orderIds)
            ->where('status', 'awaiting_payment')
            ->where('reserved_until', '>', now())
            ->count();

        if ($tickets->isEmpty()) {
            return UssdReply::end($pending > 0
                ? 'Your payment is still being confirmed. Please check again in a few minutes.'
                : 'No upcoming tickets were found for this number.');
        }

        $lines = $tickets->map(fn (Ticket $ticket) => Str::limit($ticket->event->title, 18).' '
            .$ticket->event->start_at->format('j M')."\n".Str::limit($ticket->ticketType->name, 12).': '.$ticket->code);

        return UssdReply::end("Your tickets\n".$lines->implode("\n")
            .($pending > 0 ? "\n1 payment still pending" : '')
            ."\nShow the code at the gate.");
    }

    private function showCampaigns(array &$session): UssdReply
    {
        $campaigns = Campaign::where('status', 'active')
            ->where(fn ($query) => $query->whereNull('deadline_at')->orWhere('deadline_at', '>', now()))
            ->latest()
            ->limit(self::LIST_SIZE)
            ->get();

        if ($campaigns->isEmpty()) {
            return UssdReply::end('There are no campaigns accepting contributions right now.');
        }

        $session['step'] = 'campaign';
        $session['options'] = $campaigns->pluck('id')->all();

        return UssdReply::continue("Choose a campaign\n".$this->numbered(
            $campaigns->map(fn (Campaign $campaign) => Str::limit($campaign->title, 28))
        )."\n0. Back");
    }

    private function fromCampaignList(array &$session, string $input): UssdReply
    {
        if ($input === '0') {
            $session['step'] = 'main';

            return $this->mainMenu();
        }

        $campaign = $this->chosen($session, $input, fn (int $id) => Campaign::where('status', 'active')->find($id));
        if (! $campaign) {
            return $this->showCampaigns($session);
        }

        $session['step'] = 'amount';
        $session['campaign_id'] = $campaign->id;

        return UssdReply::continue(Str::limit($campaign->title, 30)."\nEnter amount in {$campaign->currency}");
    }

    private function fromAmount(array &$session, string $input): UssdReply
    {
        $amount = str_replace(',', '', $input);
        $minimum = config('ticketflow.ussd.minimum_contribution');

        if (! ctype_digit($amount) || (int) $amount < $minimum) {
            return UssdReply::continue('Enter a whole amount of at least '.number_format($minimum).'.');
        }

        $session['step'] = 'contributor_name';
        $session['amount_minor'] = (int) $amount * 100;

        return UssdReply::continue("Enter your name\n0. Stay anonymous");
    }

    private function fromContributorName(array &$session, string $input): UssdReply
    {
        $session['step'] = 'confirm_contribution';
        $session['name'] = $input === '0' ? null : Str::limit($input, 120, '');

        $campaign = Campaign::findOrFail($session['campaign_id']);

        return UssdReply::continue(Str::limit($campaign->title, 30)."\n"
            .'Contribute '.$this->money($session['amount_minor'], $campaign->currency)."\n"
            ."1. Pay with Mobile Money\n2. Cancel");
    }

    private function fromContributionConfirmation(array &$session, string $input): UssdReply
    {
        if ($input !== '1') {
            return UssdReply::end('Cancelled. Nothing was charged.');
        }

        if (! $this->mobileMoneyAvailable()) {
            return UssdReply::end('Mobile Money is not available right now. Please try again later or give on tuvio.org.');
        }

        try {
            $contribution = $this->contributions->create(
                Campaign::findOrFail($session['campaign_id']),
                ['name' => $session['name'], 'email' => null, 'phone' => '+'.$session['msisdn']],
                $session['amount_minor'],
                ['is_anonymous' => $session['name'] === null],
            );
        } catch (RuntimeException $e) {
            return UssdReply::end($e->getMessage());
        }

        dispatch(function () use ($contribution) {
            $this->payments->initiateForContribution($contribution, route('contribute.callback', $contribution->reference), method: 'mobile_money');
            $this->settleDemoPayment(Payment::where('contribution_id', $contribution->id)->latest('id')->first());
        })->afterResponse();

        return UssdReply::end('Approve the Mobile Money prompt of '.$this->money($contribution->amount_minor, $contribution->currency)
            .' with your PIN. Thank you for your support.');
    }

    /** Demo mode settles fake payments instantly, as the web checkout does. */
    private function settleDemoPayment(?Payment $payment): void
    {
        if ($payment && config('ticketflow.demo_mode') && config('ticketflow.payments.default') === 'fake') {
            $this->payments->verifyAndFulfill($payment);
        }
    }

    /** Matches the web checkout: Mobile Money is off when Paytota has no Mobile Money terminal. */
    private function mobileMoneyAvailable(): bool
    {
        return config('ticketflow.payments.default') !== 'paytota'
            || config('ticketflow.payments.paytota.mobile_money');
    }

    private function maxQuantity(TicketType $type): int
    {
        return max(1, min(self::MAX_QUANTITY, $type->max_per_order, $type->availableQuantity() ?? self::MAX_QUANTITY));
    }

    /**
     * Resolve a numbered menu choice to the record shown at that position.
     *
     * @param  callable(int): mixed  $find
     */
    private function chosen(array $session, string $input, callable $find): mixed
    {
        $id = ctype_digit($input) ? ($session['options'][(int) $input - 1] ?? null) : null;

        return $id ? $find($id) : null;
    }

    /** @param  Collection<int,string>  $labels */
    private function numbered(Collection $labels): string
    {
        return $labels->values()->map(fn (string $label, int $index) => ($index + 1).'. '.$label)->implode("\n");
    }

    private function money(int $amountMinor, string $currency): string
    {
        return $currency.' '.number_format($amountMinor / 100);
    }

    /**
     * Gateways send either the latest keypress or the whole path ("1*2*3"); use the latest.
     */
    private function lastInput(string $message): string
    {
        return trim((string) Str::afterLast($message, '*'));
    }

    /**
     * The ways a buyer may have typed this number at the web checkout, without spaces,
     * dashes or "+": 256770123456 and 0770123456.
     *
     * @return list<string>
     */
    private function phoneVariants(string $msisdn): array
    {
        $dialingCode = config('ticketflow.payments.paytota.dialing_code', '256');

        return str_starts_with($msisdn, $dialingCode)
            ? [$msisdn, '0'.substr($msisdn, strlen($dialingCode))]
            : [$msisdn];
    }
}
