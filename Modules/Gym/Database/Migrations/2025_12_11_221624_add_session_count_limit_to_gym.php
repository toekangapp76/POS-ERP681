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
        // Add session count limit fields to gym_packages
        Schema::table('gym_packages', function (Blueprint $table) {
            $table->boolean('session_count_enabled')->default(false)->after('session_limit_minutes');
            $table->integer('session_count_limit')->nullable()->after('session_count_enabled');
        });

        // Add session count tracking to transactions (subscriptions)
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('gym_remaining_sessions')->nullable()->after('gym_remaining_minutes');
            $table->integer('gym_used_sessions')->default(0)->after('gym_remaining_sessions');
        });

        // Add booking_id to gym_attendances for auto-complete feature
        Schema::table('gym_attendances', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->after('contact_id');
            $table->foreign('booking_id')->references('id')->on('gym_bookings')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_attendances', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropColumn('booking_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['gym_remaining_sessions', 'gym_used_sessions']);
        });

        Schema::table('gym_packages', function (Blueprint $table) {
            $table->dropColumn(['session_count_enabled', 'session_count_limit']);
        });
    }
};
