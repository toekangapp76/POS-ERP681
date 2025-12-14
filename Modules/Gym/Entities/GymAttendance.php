<?php

namespace Modules\Gym\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Transaction;
use App\Contact;

class GymAttendance extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'session_deducted' => 'boolean',
        'duration_minutes' => 'integer',
    ];

    /**
     * Get the transaction/subscription associated with this attendance
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * Get the contact/member associated with this attendance
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Calculate duration in minutes between in_time and out_time
     *
     * @return int|null
     */
    public function calculateDuration(): ?int
    {
        if (!$this->in_time || !$this->out_time) {
            return null;
        }

        $inTime = \Carbon\Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->in_time);
        $outTime = \Carbon\Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->out_time);

        // Handle case where out_time is past midnight
        if ($outTime < $inTime) {
            $outTime->addDay();
        }

        return $outTime->diffInMinutes($inTime);
    }

    /**
     * Get formatted duration
     *
     * @return string
     */
    public function getDurationFormatted(): string
    {
        $minutes = $this->duration_minutes ?? $this->calculateDuration();
        
        if (!$minutes) {
            return '-';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$mins}m";
        }
    }
}
