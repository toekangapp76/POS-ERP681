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
        Schema::create('gym_hour_topups', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            $table->integer('business_id')->unsigned();
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            
            // Link to subscription - uses integer to match transactions.id
            $table->integer('subscription_id')->unsigned();
            $table->foreign('subscription_id')->references('id')->on('transactions')->onDelete('cascade');
            
            // Member
            $table->integer('contact_id')->unsigned();
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            
            // Hours added
            $table->decimal('hours_added', 8, 2);
            
            // Amount paid
            $table->decimal('amount', 22, 4)->default(0);
            
            // Payment reference (transaction_payment_id if paid)
            $table->unsignedBigInteger('payment_id')->nullable();
            
            // Note
            $table->text('note')->nullable();
            
            // Created by
            $table->integer('created_by')->unsigned();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->timestamps();
        });
        
        // Add topup hours tracking to subscriptions if not exists
        // This will be handled in the subscription transaction
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gym_hour_topups');
    }
};
