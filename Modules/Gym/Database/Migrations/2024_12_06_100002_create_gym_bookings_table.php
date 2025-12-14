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
        Schema::create('gym_bookings', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            $table->integer('business_id')->unsigned();
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            
            $table->integer('location_id')->unsigned()->nullable();
            $table->foreign('location_id')->references('id')->on('business_locations')->onDelete('set null');
            
            // Member / Contact
            $table->integer('contact_id')->unsigned();
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            
            // Subscription (optional) - uses integer to match transactions.id
            $table->integer('subscription_id')->unsigned()->nullable();
            $table->foreign('subscription_id')->references('id')->on('transactions')->onDelete('set null');
            
            // Class being booked (GYM, PADEL 1-4, PILATES, etc.)
            $table->unsignedBigInteger('gym_class_id')->nullable();
            $table->foreign('gym_class_id')->references('id')->on('gym_classes')->onDelete('set null');
            
            // Court/Resource selection (for PADEL courts, equipment, etc.)
            $table->unsignedBigInteger('court_id')->nullable();
            $table->foreign('court_id')->references('id')->on('gym_courts')->onDelete('set null');
            
            // PIC / Agent / Coach (optional)
            $table->integer('agent_id')->unsigned()->nullable();
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('set null');
            
            // Booking time
            $table->datetime('booking_start');
            $table->datetime('booking_end');
            
            // Duration in minutes
            $table->integer('duration_minutes')->default(60);
            
            // Booking status: pending, confirmed, completed, cancelled, no_show
            $table->enum('booking_status', ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])->default('confirmed');
            
            // Hours deducted from subscription (if using subscription)
            $table->decimal('hours_deducted', 8, 2)->default(0);
            
            // Reschedule tracking
            $table->integer('reschedule_count')->default(0);
            $table->integer('max_reschedule')->default(2);
            $table->date('reschedule_deadline')->nullable();
            
            // Notes
            $table->text('booking_note')->nullable();
            
            // Created by user
            $table->integer('created_by')->unsigned();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['business_id', 'booking_start', 'booking_end']);
            $table->index(['contact_id', 'booking_start']);
            $table->index(['gym_class_id', 'booking_start']);
            $table->index(['court_id', 'booking_start']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gym_bookings');
    }
};
