<?php

namespace Modules\Gym\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class GymBooking extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'booking_start' => 'datetime',
        'booking_end' => 'datetime',
        'reschedule_deadline' => 'date',
        'hours_deducted' => 'decimal:2',
    ];

    /**
     * Booking statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW = 'no_show';

    /**
     * Get all booking statuses
     */
    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING => __('gym::lang.pending'),
            self::STATUS_CONFIRMED => __('gym::lang.confirmed'),
            self::STATUS_COMPLETED => __('gym::lang.completed'),
            self::STATUS_CANCELLED => __('restaurant.cancelled'),
            self::STATUS_NO_SHOW => __('gym::lang.no_show'),
        ];
    }

    /**
     * Relationships
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function location()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }

    public function member()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    public function subscription()
    {
        return $this->belongsTo(\App\Transaction::class, 'subscription_id');
    }

    public function gymClass()
    {
        return $this->belongsTo(\Modules\Gym\Entities\GymClass::class, 'gym_class_id');
    }

    public function court()
    {
        return $this->belongsTo(\Modules\Gym\Entities\GymCourt::class, 'court_id');
    }

    public function agent()
    {
        return $this->belongsTo(\App\User::class, 'agent_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\User::class, 'created_by');
    }

    /**
     * Scopes
     */
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeForMember($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    public function scopeForClass($query, $classId)
    {
        return $query->where('gym_class_id', $classId);
    }

    public function scopeForCourt($query, $courtId)
    {
        return $query->where('court_id', $courtId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('booking_status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('booking_start', '>=', now());
    }

    public function scopeToday($query)
    {
        return $query->whereDate('booking_start', today());
    }

    public function scopeInDateRange($query, $start, $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('booking_start', [$start, $end])
              ->orWhereBetween('booking_end', [$start, $end])
              ->orWhere(function ($q2) use ($start, $end) {
                  $q2->where('booking_start', '<=', $start)
                     ->where('booking_end', '>=', $end);
              });
        });
    }

    /**
     * Check if booking can be rescheduled
     */
    public function canReschedule(): bool
    {
        if ($this->booking_status !== self::STATUS_CONFIRMED) {
            return false;
        }

        if ($this->reschedule_count >= $this->max_reschedule) {
            return false;
        }

        if ($this->reschedule_deadline && now()->greaterThan($this->reschedule_deadline)) {
            return false;
        }

        return true;
    }

    /**
     * Check if booking can be cancelled
     */
    public function canCancel(): bool
    {
        if (!in_array($this->booking_status, [self::STATUS_PENDING, self::STATUS_CONFIRMED])) {
            return false;
        }

        // Check cancellation policy
        if ($this->gymClass && $this->gymClass->cancellation_hours) {
            $cancellationDeadline = $this->booking_start->subHours($this->gymClass->cancellation_hours);
            if (now()->greaterThan($cancellationDeadline)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get duration in hours
     */
    public function getDurationHoursAttribute(): float
    {
        return $this->duration_minutes / 60;
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours} " . __('gym::lang.hours');
        } else {
            return "{$minutes} " . __('gym::lang.minutes');
        }
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->booking_status) {
            self::STATUS_PENDING => 'bg-warning',
            self::STATUS_CONFIRMED => 'bg-success',
            self::STATUS_COMPLETED => 'bg-info',
            self::STATUS_CANCELLED => 'bg-danger',
            self::STATUS_NO_SHOW => 'bg-secondary',
            default => 'bg-secondary',
        };
    }

    /**
     * Create a new booking
     */
    public static function createBooking(array $input): self
    {
        $data = [
            'business_id' => $input['business_id'],
            'location_id' => $input['location_id'] ?? null,
            'contact_id' => $input['contact_id'] ?? null,
            'walkin_name' => $input['walkin_name'] ?? null,
            'walkin_phone' => $input['walkin_phone'] ?? null,
            'subscription_id' => $input['subscription_id'] ?? null,
            'gym_class_id' => $input['gym_class_id'] ?? null,
            'court_id' => $input['court_id'] ?? null,
            'agent_id' => $input['agent_id'] ?? null,
            'booking_start' => $input['booking_start'],
            'booking_end' => $input['booking_end'],
            'duration_minutes' => $input['duration_minutes'] ?? 60,
            'booking_status' => $input['booking_status'] ?? self::STATUS_CONFIRMED,
            'hours_deducted' => $input['hours_deducted'] ?? 0,
            'max_reschedule' => $input['max_reschedule'] ?? 2,
            'reschedule_deadline' => $input['reschedule_deadline'] ?? null,
            'booking_note' => $input['booking_note'] ?? null,
            'created_by' => $input['created_by'],
        ];

        return self::create($data);
    }

    /**
     * Check for conflicting bookings
     */
    public static function hasConflict(
        int $businessId,
        string $bookingStart,
        string $bookingEnd,
        ?int $classId = null,
        ?int $courtId = null,
        ?int $excludeBookingId = null
    ): ?self {
        $query = self::where('business_id', $businessId)
            ->whereIn('booking_status', [self::STATUS_PENDING, self::STATUS_CONFIRMED])
            ->where(function ($q) use ($bookingStart, $bookingEnd) {
                $q->whereBetween('booking_start', [$bookingStart, $bookingEnd])
                  ->orWhereBetween('booking_end', [$bookingStart, $bookingEnd])
                  ->orWhere(function ($q2) use ($bookingStart, $bookingEnd) {
                      $q2->where('booking_start', '<=', $bookingStart)
                         ->where('booking_end', '>=', $bookingEnd);
                  });
            });

        if ($courtId) {
            $query->where('court_id', $courtId);
        } elseif ($classId) {
            $query->where('gym_class_id', $classId);
        }

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->first();
    }
}
