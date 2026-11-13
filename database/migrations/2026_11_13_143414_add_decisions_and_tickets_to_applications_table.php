<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
            $table->string('ticket_code', 41)->nullable()->unique();
            $table->timestampTz('accepted_at')->nullable();
            $table->index(['listing_id', 'status']);
        });

        DB::table('applications')->whereIn('status', ['submitted', 'reviewed'])->update(['status' => 'pending']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['listing_id', 'status']);
            $table->dropUnique(['ticket_code']);
            $table->dropColumn(['ticket_code', 'accepted_at']);
            $table->string('status', 20)->default('submitted')->change();
        });

        DB::table('applications')->where('status', 'pending')->update(['status' => 'submitted']);
    }
};
