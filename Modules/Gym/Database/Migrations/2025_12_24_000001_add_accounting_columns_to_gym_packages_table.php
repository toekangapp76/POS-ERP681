<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccountingColumnsToGymPackagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('gym_packages', function (Blueprint $table) {
            // Revenue account (Credit) - untuk pendapatan membership
            $table->unsignedBigInteger('revenue_account_id')->nullable()->after('notes');
            // Bank/Cash account (Debit) - untuk penerimaan kas/bank
            $table->unsignedBigInteger('bank_account_id')->nullable()->after('revenue_account_id');
            // Tax account (Credit) - untuk PPN jika ada
            $table->unsignedBigInteger('tax_account_id')->nullable()->after('bank_account_id');
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
            $table->dropColumn(['revenue_account_id', 'bank_account_id', 'tax_account_id']);
        });
    }
}
