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
        Schema::table('gym_packages', function (Blueprint $table) {
            // Member Deposit Account (Liability) untuk deferred revenue
            $table->unsignedBigInteger('deposit_account_id')->nullable()->after('tax_account_id');
            
            // Tax rate untuk perhitungan PPN (default 11% = 0.11)
            $table->decimal('tax_rate', 5, 2)->default(11.00)->after('deposit_account_id');
            
            // Enable/disable deferred revenue recognition
            $table->boolean('enable_deferred_revenue')->default(true)->after('tax_rate');
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
            $table->dropColumn(['deposit_account_id', 'tax_rate', 'enable_deferred_revenue']);
        });
    }
};
