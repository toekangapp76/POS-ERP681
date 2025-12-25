<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGymCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gym_categories', function (Blueprint $table) {
            $table->id();
            $table->integer('business_id')->unsigned();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamps();
            
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
        });
        
        // Add category_id to gym_packages table
        Schema::table('gym_packages', function (Blueprint $table) {
            $table->unsignedBigInteger('gym_category_id')->nullable()->after('business_id');
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
            $table->dropColumn('gym_category_id');
        });
        
        Schema::dropIfExists('gym_categories');
    }
}
