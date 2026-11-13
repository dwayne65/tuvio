<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Support\EventPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventPresenterTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<array<string, mixed>>  $ticketTypes */
    private function eventWithTicketTypes(array $ticketTypes): Event
    {
        $org = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Acme', 'slug' => 'acme', 'default_currency' => 'UGX', 'status' => 'active']);
        $event = $org->events()->create([
            'title' => 'Gig', 'slug' => 'gig', 'status' => 'published', 'type' => 'physical', 'visibility' => 'public',
            'start_at' => now()->addWeek(), 'currency' => 'UGX',
        ]);
        foreach ($ticketTypes as $i => $attributes) {
            $event->ticketTypes()->create([
                'name' => "Type {$i}", 'kind' => 'paid', 'price_minor' => 1000000, 'currency' => 'UGX',
                'max_per_order' => 10, 'status' => 'active', ...$attributes,
            ]);
        }

        return $event->load(['category', 'organization', 'ticketTypes']);
    }

    /** @return array<string, array{list<array<string, mixed>>, string}> */
    public static function availabilityCases(): array
    {
        return [
            'plenty left' => [[['quantity_total' => 100, 'quantity_sold' => 50]], 'on_sale'],
            'unlimited capacity' => [[['quantity_total' => null, 'quantity_sold' => 900]], 'on_sale'],
            'a fifth left' => [[['quantity_total' => 100, 'quantity_sold' => 80]], 'selling_fast'],
            'reserved seats count as taken' => [[['quantity_total' => 100, 'quantity_sold' => 70, 'quantity_reserved' => 15]], 'selling_fast'],
            'every type gone' => [[['quantity_total' => 10, 'quantity_sold' => 10], ['quantity_total' => 5, 'quantity_sold' => 5]], 'sold_out'],
            'sales not open yet' => [[['quantity_total' => 100, 'sales_start_at' => now()->addDay()]], 'not_on_sale'],
            'no ticket types' => [[], 'not_on_sale'],
            'hidden types are ignored' => [[['quantity_total' => 10, 'quantity_sold' => 10], ['quantity_total' => 100, 'is_hidden' => true]], 'sold_out'],
        ];
    }

    /** @param  list<array<string, mixed>>  $ticketTypes */
    #[DataProvider('availabilityCases')]
    public function test_summary_reports_ticket_availability(array $ticketTypes, string $expected): void
    {
        $summary = EventPresenter::summary($this->eventWithTicketTypes($ticketTypes));

        $this->assertSame($expected, $summary['availability']);
    }
}
