<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPnlGroupToExpenseCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->string('pnl_group')->nullable()->after('code')->comment('P&L Bisnis group name for this expense category');
        });

        // Update existing data based on code prefix
        $mapping = [
            '01' => 'Gym',
            '02' => 'Padel',
            '03' => 'Pilates',
            '04' => 'Pro Shop',
            '05' => 'Sudest Cafe',
        ];

        foreach ($mapping as $code => $group) {
            \DB::table('expense_categories')
                ->where('code', $code)
                ->update(['pnl_group' => $group]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn('pnl_group');
        });
    }
}
