<?php

namespace App\Models;

use Database\Factories\PageViewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    /** @use HasFactory<PageViewFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    /** How long raw page views are kept before `model:prune` deletes them. */
    public const KEEP_DAYS = 400;

    protected $fillable = ['visited_on', 'path', 'visitor', 'is_entry', 'referrer_host', 'device'];

    protected function casts(): array
    {
        return [
            'visited_on' => 'date',
            'is_entry' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('visited_on', '<', now()->subDays(self::KEEP_DAYS)->toDateString());
    }
}
