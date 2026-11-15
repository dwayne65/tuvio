<?php

namespace Tests\Unit;

use App\Models\Ticket;
use App\Models\TicketType;
use App\Support\TicketDesign;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketDesignTest extends TestCase
{
    /** @return array<string, array{?string, string, string, string}> */
    public static function ticketTypes(): array
    {
        return [
            'chosen general is ordinary' => ['general', 'Regular', 'ordinary', 'ORDINARY'],
            'chosen vip wins over name' => ['vip', 'Backstage Club', 'vip', 'VIP'],
            'chosen vvip' => ['vvip', 'Table for 6', 'vvip', 'VVIP'],
            'chosen guest' => ['guest', 'Press', 'guest', 'GUEST'],
            'other shows its own name' => ['other', 'Early Bird', 'ordinary', 'EARLY BIRD'],
            'legacy vip inferred from name' => [null, 'VIP Lounge', 'vip', 'VIP'],
            'legacy vvip is not vip' => [null, 'VVIP Table', 'vvip', 'VVIP'],
            'legacy guest list inferred from name' => [null, 'Guest List', 'guest', 'GUEST'],
            'legacy plain name' => [null, 'General Admission', 'ordinary', 'ORDINARY'],
        ];
    }

    #[DataProvider('ticketTypes')]
    public function test_ticket_design_follows_the_ticket_type_tier(?string $tier, string $name, string $expectedDesign, string $expectedLabel): void
    {
        $design = TicketDesign::forTicket($this->ticket(new TicketType(['tier' => $tier, 'name' => $name, 'price_minor' => 5000000])));

        $this->assertSame($expectedDesign, $design['key']);
        $this->assertSame($expectedLabel, $design['label']);
    }

    public function test_a_free_guest_ticket_is_marked_complimentary(): void
    {
        $free = TicketDesign::forTicket($this->ticket(new TicketType(['tier' => 'guest', 'name' => 'Guest', 'price_minor' => 0])));
        $paid = TicketDesign::forTicket($this->ticket(new TicketType(['tier' => 'guest', 'name' => 'Guest', 'price_minor' => 1000000])));

        $this->assertTrue($free['complimentary']);
        $this->assertSame('Complimentary guest', $free['badge']);
        $this->assertSame('Guest admission', $paid['badge']);
    }

    private function ticket(TicketType $type): Ticket
    {
        return (new Ticket)->setRelation('ticketType', $type)->setRelation('orderItem', null);
    }
}
