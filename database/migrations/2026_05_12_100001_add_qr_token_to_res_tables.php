<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AddQrTokenToResTables extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('res_tables', 'qr_token')) {
            Schema::table('res_tables', function (Blueprint $table) {
                $table->string('qr_token', 64)->nullable()->unique()->after('pos_y');
            });
        }

        // Generate tokens for existing tables that don't have one yet
        DB::table('res_tables')->whereNull('qr_token')->orderBy('id')->each(function ($row) {
            DB::table('res_tables')
                ->where('id', $row->id)
                ->update(['qr_token' => Str::random(32)]);
        });
    }

    public function down()
    {
        Schema::table('res_tables', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }
}
