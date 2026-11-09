<?php

namespace Tests\Unit;

use App\Models\Ticket;
use App\Models\TicketType;
use App\Support\TicketArtwork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketArtworkTest extends TestCase
{
    /** @return array<string, array{?string, string, string, string}> */
    public static function ticketTypes(): array
    {
        return [
            'chosen general' => ['general', 'Regular', 'standard', 'GENERAL'],
            'chosen vip wins over name' => ['vip', 'Backstage Club', 'vip', 'VIP'],
            'chosen vvip' => ['vvip', 'Table for 6', 'vvip', 'VVIP'],
            'other shows its own name' => ['other', 'Early Bird', 'standard', 'EARLY BIRD'],
            'legacy vip inferred from name' => [null, 'VIP Lounge', 'vip', 'VIP'],
            'legacy vvip is not vip' => [null, 'VVIP Table', 'vvip', 'VVIP'],
            'legacy plain name' => [null, 'General Admission', 'standard', 'GENERAL'],
        ];
    }

    #[DataProvider('ticketTypes')]
    public function test_ticket_design_follows_the_ticket_type_tier(?string $tier, string $name, string $expectedDesign, string $expectedLabel): void
    {
        $ticket = (new Ticket)->setRelation('ticketType', new TicketType(['tier' => $tier, 'name' => $name]));

        $design = (new TicketArtwork)->tier($ticket);

        $this->assertSame($expectedDesign, $design['key']);
        $this->assertSame($expectedLabel, $design['label']);
    }
}
