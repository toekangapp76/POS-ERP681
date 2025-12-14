<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gym_courts', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            $table->integer('business_id')->unsigned();
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            
            // Link to gym class (PADEL, etc.)
            $table->unsignedBigInteger('gym_class_id')->nullable();
            $table->foreign('gym_class_id')->references('id')->on('gym_classes')->onDelete('cascade');
            
            $table->string('name'); // e.g., "PADEL 1", "PADEL 2", "Court A"
            $table->string('code')->nullable(); // Short code for display
            $table->text('description')->nullable();
            
            // Pricing per hour (optional, for non-subscription bookings)
            $table->decimal('price_per_hour', 22, 4)->default(0);
            
            // Capacity
            $table->integer('capacity')->default(1);
            
            // Status
            $table->boolean('is_active')->default(true);
            
            // Display order
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gym_courts');
    }
};
