<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Starter and Professional charge 4.5% per paid ticket (was 10%); Enterprise keeps 3.7%. */
    public function up(): void
    {
        DB::table('subscription_plans')->whereIn('slug', ['starter', 'professional'])->update(['commission_bps' => 450]);
    }

    public function down(): void
    {
        DB::table('subscription_plans')->whereIn('slug', ['starter', 'professional'])->update(['commission_bps' => 1000]);
    }
};
