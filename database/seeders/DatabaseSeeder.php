<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data (safe to run repeatedly).
        $this->call([
            ChartOfAccountsSeeder::class,
            SubscriptionPlanSeeder::class,
            EventCategorySeeder::class,
        ]);

        // Platform administrator.
        User::updateOrCreate(
            ['email' => 'admin@ticketflow.test'],
            [
                'name' => 'TicketFlow Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
