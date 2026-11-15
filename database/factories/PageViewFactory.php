<?php

namespace Database\Factories;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visited_on' => now()->toDateString(),
            'path' => fake()->randomElement(['/', '/events', '/pricing']),
            'visitor' => fake()->md5(),
            'is_entry' => false,
            'referrer_host' => null,
            'device' => fake()->randomElement(['mobile', 'desktop']),
        ];
    }

    /** The first page of a visit, arriving from the given site (null = typed or bookmarked). */
    public function entry(?string $referrerHost = null): static
    {
        return $this->state(['is_entry' => true, 'referrer_host' => $referrerHost]);
    }
}
