<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gym_bookings', function (Blueprint $table) {
            // Walk-in customer fields (when booking without member account)
            $table->string('walkin_name')->nullable()->after('contact_id');
            $table->string('walkin_phone')->nullable()->after('walkin_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gym_bookings', function (Blueprint $table) {
            $table->dropColumn(['walkin_name', 'walkin_phone']);
        });
    }
};
