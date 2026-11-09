<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use Illuminate\Database\Seeder;

class EventCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Music & Concerts', 'music', 'music', '#7557E8'],
            ['Business & Conferences', 'business', 'briefcase', '#2563EB'],
            ['Education & Workshops', 'education', 'graduation-cap', '#0EA5E9'],
            ['Sports', 'sports', 'trophy', '#F59E0B'],
            ['Technology', 'technology', 'cpu', '#10B981'],
            ['Festivals', 'festivals', 'party-popper', '#EC4899'],
            ['Religious', 'religious', 'church', '#8B5CF6'],
            ['Charity & Fundraising', 'charity', 'heart-handshake', '#EF4444'],
        ];

        foreach ($categories as $i => [$name, $slug, $icon, $color]) {
            EventCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'icon' => $icon, 'color' => $color, 'sort' => $i + 1, 'is_active' => true],
            );
        }
    }
}
