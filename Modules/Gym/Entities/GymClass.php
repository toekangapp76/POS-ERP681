<?php

namespace Modules\Gym\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GymClass extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];

    /**
     * Class types
     */
    const TYPE_GYM = 'gym';
    const TYPE_COURT = 'court';
    const TYPE_CLASS = 'class';

    protected $casts = [
        'has_courts' => 'boolean',
        'is_active' => 'boolean',
        'price_per_hour' => 'decimal:4',
    ];

    /**
     * Get class types for dropdown
     */
    public static function getClassTypes()
    {
        return [
            self::TYPE_GYM => __('gym::lang.gym'),
            self::TYPE_COURT => __('gym::lang.court'),
            self::TYPE_CLASS => __('gym::lang.class_type'),
        ];
    }

    /**
     * Relationships
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function courts()
    {
        return $this->hasMany(\Modules\Gym\Entities\GymCourt::class, 'gym_class_id');
    }

    public function bookings()
    {
        return $this->hasMany(\Modules\Gym\Entities\GymBooking::class, 'gym_class_id');
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('class_type', $type);
    }

    /**
     * Get dropdown for a business
     */
    public static function forDropdown($businessId, $activeOnly = true)
    {
        $query = self::where('business_id', $businessId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * Get available time slots for a date
     */
    public function getAvailableSlots($date, $excludeBookingId = null): array
    {
        $slots = [];
        
        // Generate time slots based on class settings
        $startHour = 6; // Start at 6 AM
        $endHour = 22; // End at 10 PM
        
        for ($hour = $startHour; $hour < $endHour; $hour++) {
            $start = $date . ' ' . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00:00';
            $end = $date . ' ' . str_pad($hour + 1, 2, '0', STR_PAD_LEFT) . ':00:00';
            
            // Check if slot is available
            $conflict = GymBooking::hasConflict(
                $this->business_id,
                $start,
                $end,
                $this->id,
                null,
                $excludeBookingId
            );
            
            $slots[] = [
                'start' => $start,
                'end' => $end,
                'hour' => $hour,
                'available' => $conflict === null,
            ];
        }
        
        return $slots;
    }

    /**
     * Check if class requires court selection
     */
    public function requiresCourtSelection(): bool
    {
        return $this->has_courts && $this->courts()->active()->count() > 0;
    }

    /**
     * Get formatted duration options
     */
    public function getDurationOptions(): array
    {
        $options = [];
        $minHours = $this->min_booking_hours ?? 1;
        $maxHours = $this->max_booking_hours ?? 4;

        for ($h = $minHours; $h <= $maxHours; $h++) {
            $minutes = $h * 60;
            $label = $h == 1 ? "1 " . __('gym::lang.hour') : "{$h} " . __('gym::lang.hours');
            $options[$minutes] = $label;
        }

        return $options;
    }
}

