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
        Schema::create('gym_deferred_revenues', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('transaction_id'); // gym_subscription transaction
            $table->unsignedInteger('contact_id'); // member
            $table->unsignedBigInteger('gym_package_id');
            
            // Schedule info
            $table->date('recognition_date'); // tanggal pengakuan (akhir bulan)
            $table->date('period_start'); // awal periode yang diakui
            $table->date('period_end'); // akhir periode yang diakui
            $table->integer('period_days'); // jumlah hari dalam periode
            $table->integer('active_days'); // hari aktif dalam periode
            
            // Amounts
            $table->decimal('total_amount', 22, 4); // total member deposit (excl tax)
            $table->decimal('monthly_amount', 22, 4); // nilai per bulan penuh
            $table->decimal('recognition_amount', 22, 4); // nilai yang diakui di periode ini
            
            // Status
            $table->enum('status', ['pending', 'recognized', 'cancelled'])->default('pending');
            $table->dateTime('recognized_at')->nullable();
            $table->unsignedInteger('recognized_by')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable(); // link ke JE yang dibuat
            
            // Accounting accounts (from package)
            $table->unsignedBigInteger('deposit_account_id'); // Member Deposit (Liability)
            $table->unsignedBigInteger('revenue_account_id'); // Membership Revenue
            
            $table->unsignedInteger('created_by');
            $table->timestamps();
            
            // Indexes
            $table->index(['business_id', 'status', 'recognition_date']);
            $table->index(['transaction_id']);
            $table->index(['contact_id']);
            
            // Foreign keys
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('gym_package_id')->references('id')->on('gym_packages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gym_deferred_revenues');
    }
};
