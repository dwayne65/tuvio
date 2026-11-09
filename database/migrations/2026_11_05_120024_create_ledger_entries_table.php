<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();

            $table->string('direction');              // debit | credit
            $table->bigInteger('amount_minor');       // always positive; direction gives sign
            $table->char('currency', 3)->default('UGX');
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index('ledger_transaction_id');
            $table->index(['ledger_account_id', 'direction']);
        });

        // Ledger entries are immutable and append-only. Updates/deletes are blocked at the
        // DB level so posted financial history can never be silently rewritten.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION ledger_entries_immutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'ledger_entries is append-only; % is not permitted', TG_OP;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER ledger_entries_no_update
                    BEFORE UPDATE ON ledger_entries
                    FOR EACH ROW EXECUTE FUNCTION ledger_entries_immutable();

                CREATE TRIGGER ledger_entries_no_delete
                    BEFORE DELETE ON ledger_entries
                    FOR EACH ROW EXECUTE FUNCTION ledger_entries_immutable();
            SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_no_update ON ledger_entries;');
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_no_delete ON ledger_entries;');
            DB::unprepared('DROP FUNCTION IF EXISTS ledger_entries_immutable();');
        }
        Schema::dropIfExists('ledger_entries');
    }
};
