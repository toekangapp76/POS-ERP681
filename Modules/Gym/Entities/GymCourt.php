<?php

namespace Modules\Gym\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GymCourt extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'price_per_hour' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function gymClass()
    {
        return $this->belongsTo(\Modules\Gym\Entities\GymClass::class, 'gym_class_id');
    }

    public function bookings()
    {
        return $this->hasMany(\Modules\Gym\Entities\GymBooking::class, 'court_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where('gym_class_id', $classId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get dropdown for a specific class
     */
    public static function forDropdown($businessId, $classId = null)
    {
        $query = self::where('business_id', $businessId)
            ->where('is_active', true);

        if ($classId) {
            $query->where('gym_class_id', $classId);
        }

        return $query->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * Check if court is available at given time
     */
    public function isAvailableAt($start, $end, $excludeBookingId = null): bool
    {
        $conflict = GymBooking::hasConflict(
            $this->business_id,
            $start,
            $end,
            null,
            $this->id,
            $excludeBookingId
        );

        return $conflict === null;
    }

    /**
     * Get available courts for a time slot
     */
    public static function getAvailable($businessId, $classId, $start, $end)
    {
        $courts = self::where('business_id', $businessId)
            ->where('gym_class_id', $classId)
            ->where('is_active', true)
            ->get();

        return $courts->filter(function ($court) use ($start, $end) {
            return $court->isAvailableAt($start, $end);
        });
    }
}
