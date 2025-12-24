<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccountingColumnsToCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            // Account for sales revenue (Credit when selling)
            $table->unsignedBigInteger('sales_account_id')->nullable()->after('parent_id');
            // Account for purchase/COGS (Debit when purchasing)  
            $table->unsignedBigInteger('purchase_account_id')->nullable()->after('sales_account_id');
            
            // Note: These will be used for auto-mapping transactions to accounting
            // When a product from this category is sold, it will credit the sales_account
            // When a product from this category is purchased, it will debit the purchase_account
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['sales_account_id', 'purchase_account_id']);
        });
    }
}
