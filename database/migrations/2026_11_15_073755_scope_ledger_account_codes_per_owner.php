<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every organization has its own ORGANIZER_PAYABLE account, so a code is only unique
     * per owner and currency. The old platform-wide unique code let just one organization
     * ever record a sale; the next one's payment failed during fulfilment.
     */
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['code', 'owner_type', 'owner_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropUnique(['code', 'owner_type', 'owner_id', 'currency']);
            $table->unique('code');
        });
    }
};
