<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccountingColumnsToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Revenue account (Credit) - untuk pendapatan penjualan produk
            $table->unsignedBigInteger('revenue_account_id')->nullable()->after('warranty_id');
            // Inventory/COGS account (Debit) - untuk persediaan atau HPP
            $table->unsignedBigInteger('inventory_account_id')->nullable()->after('revenue_account_id');
            // Tax account (Credit) - untuk PPN jika ada
            $table->unsignedBigInteger('tax_account_id')->nullable()->after('inventory_account_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['revenue_account_id', 'inventory_account_id', 'tax_account_id']);
        });
    }
}
