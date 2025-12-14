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
        Schema::table('gym_classes', function (Blueprint $table) {
            // Class type: gym, court (padel), class (pilates, yoga, etc.)
            $table->enum('class_type', ['gym', 'court', 'class'])->default('gym')->after('description');
            
            // Default duration in minutes
            $table->integer('default_duration')->default(60)->after('class_type');
            
            // Capacity (max participants for group classes)
            $table->integer('capacity')->nullable()->after('default_duration');
            
            // Has courts/resources (for PADEL, etc.)
            $table->boolean('has_courts')->default(false)->after('capacity');
            
            // Price per hour (for non-subscription bookings)
            $table->decimal('price_per_hour', 22, 4)->default(0)->after('has_courts');
            
            // Booking settings
            $table->integer('min_booking_hours')->default(1)->after('price_per_hour');
            $table->integer('max_booking_hours')->default(4)->after('min_booking_hours');
            
            // Advance booking limit (days)
            $table->integer('advance_booking_days')->default(7)->after('max_booking_hours');
            
            // Cancellation policy (hours before booking)
            $table->integer('cancellation_hours')->default(24)->after('advance_booking_days');
            
            // Reschedule policy
            $table->integer('max_reschedule')->default(2)->after('cancellation_hours');
            $table->integer('reschedule_deadline_days')->default(1)->after('max_reschedule');
            
            // Color for calendar display
            $table->string('color', 7)->default('#667eea')->after('reschedule_deadline_days');
            
            // Active status
            $table->boolean('is_active')->default(true)->after('color');
            
            // Sort order
            $table->integer('sort_order')->default(0)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gym_classes', function (Blueprint $table) {
            $table->dropColumn([
                'class_type',
                'default_duration',
                'capacity',
                'has_courts',
                'price_per_hour',
                'min_booking_hours',
                'max_booking_hours',
                'advance_booking_days',
                'cancellation_hours',
                'max_reschedule',
                'reschedule_deadline_days',
                'color',
                'is_active',
                'sort_order'
            ]);
        });
    }
};
