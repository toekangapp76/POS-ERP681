<?php

namespace Modules\Gym\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GymHourTopup extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'hours_added' => 'decimal:2',
        'amount' => 'decimal:4',
    ];

    /**
     * Relationships
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class, 'transaction_id');
    }

    public function subscription()
    {
        return $this->belongsTo(\App\Transaction::class, 'subscription_id');
    }

    public function member()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Get total topup hours for a subscription
     */
    public static function getTotalForSubscription($subscriptionId): float
    {
        return self::where('subscription_id', $subscriptionId)->sum('hours_added');
    }
}
