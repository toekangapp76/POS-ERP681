<?php

namespace Modules\Gym\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GymPackage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'session_limit_enabled' => 'boolean',
        'session_limit_minutes' => 'integer',
        'session_count_enabled' => 'boolean',
        'session_count_limit' => 'integer',
        'enabled' => 'boolean',
    ];

    /**
     * Check if this package has session time limit enabled (hours/minutes)
     */
    public function hasSessionLimit(): bool
    {
        return $this->session_limit_enabled && $this->session_limit_minutes > 0;
    }

    /**
     * Check if this package has session count limit enabled (per visit)
     */
    public function hasSessionCountLimit(): bool
    {
        return $this->session_count_enabled && $this->session_count_limit > 0;
    }

    /**
     * Get session limit in hours and minutes format
     *
     * @return string
     */
    public function getSessionLimitFormatted(): string
    {
        if (!$this->hasSessionLimit()) {
            return __('gym::lang.unlimited');
        }

        $hours = floor($this->session_limit_minutes / 60);
        $minutes = $this->session_limit_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }
}
