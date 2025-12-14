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
        // Drop foreign key first
        \DB::statement('ALTER TABLE gym_bookings DROP FOREIGN KEY gym_bookings_contact_id_foreign');
        
        // Make contact_id nullable to support walk-in customers
        \DB::statement('ALTER TABLE gym_bookings MODIFY contact_id INT UNSIGNED NULL');
        
        // Re-add foreign key with SET NULL on delete
        \DB::statement('ALTER TABLE gym_bookings ADD CONSTRAINT gym_bookings_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop foreign key
        \DB::statement('ALTER TABLE gym_bookings DROP FOREIGN KEY gym_bookings_contact_id_foreign');
        
        // Make contact_id NOT NULL again
        \DB::statement('ALTER TABLE gym_bookings MODIFY contact_id INT UNSIGNED NOT NULL');
        
        // Re-add foreign key with CASCADE delete
        \DB::statement('ALTER TABLE gym_bookings ADD CONSTRAINT gym_bookings_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE');
    }
};
