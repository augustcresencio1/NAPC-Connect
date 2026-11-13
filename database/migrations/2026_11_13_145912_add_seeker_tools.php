<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seeker_listing_states', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('saved_at')->nullable();
            $table->jsonb('checked_documents')->nullable();
            $table->date('reminder_read_deadline')->nullable();
            $table->primary(['user_id', 'listing_id']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->jsonb('ui_preferences')->nullable();
        });
        Schema::table('applications', function (Blueprint $table): void {
            $table->jsonb('activity')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn(['activity', 'reviewed_at', 'withdrawn_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('ui_preferences');
        });
        Schema::dropIfExists('seeker_listing_states');
    }
};
