<?php

namespace Tests\Feature;

use Database\Seeders\EventCategorySeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seed(EventCategorySeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);

        $this->get('/')->assertOk();
    }
}
