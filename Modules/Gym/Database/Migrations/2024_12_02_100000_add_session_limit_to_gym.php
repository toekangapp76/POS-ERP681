<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add session limit / usage timer feature to gym module
     *
     * @return void
     */
    public function up()
    {
        // Add session limit fields to gym_packages
        Schema::table('gym_packages', function (Blueprint $table) {
            $table->boolean('session_limit_enabled')->default(false)->after('enabled');
            $table->integer('session_limit_minutes')->nullable()->after('session_limit_enabled'); // Total minutes allowed per package
        });

        // Add session tracking fields to transactions (gym subscriptions)
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('gym_used_minutes')->default(0)->after('gym_package_id'); // Total minutes used
            $table->integer('gym_remaining_minutes')->nullable()->after('gym_used_minutes'); // Remaining minutes (null = unlimited)
            $table->string('gym_session_status')->nullable()->after('gym_remaining_minutes'); // active, exhausted, expired
            $table->integer('gym_priority')->default(0)->after('gym_session_status'); // Priority order for multiple subscriptions
        });

        // Add session tracking fields to gym_attendances
        Schema::table('gym_attendances', function (Blueprint $table) {
            $table->integer('duration_minutes')->nullable()->after('out_time'); // Calculated duration
            $table->foreignId('transaction_id')->nullable()->after('duration_minutes'); // Link to subscription
            $table->boolean('session_deducted')->default(false)->after('transaction_id'); // Whether session was deducted
            $table->text('session_notes')->nullable()->after('session_deducted'); // Notes (overtime, etc)
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gym_packages', function (Blueprint $table) {
            $table->dropColumn(['session_limit_enabled', 'session_limit_minutes']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['gym_used_minutes', 'gym_remaining_minutes', 'gym_session_status', 'gym_priority']);
        });

        Schema::table('gym_attendances', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'transaction_id', 'session_deducted', 'session_notes']);
        });
    }
};
