<?php

namespace Modules\Gym\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GymCategory extends Model
{
    use HasFactory;

    protected $table = 'gym_categories';

    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the packages for this category
     */
    public function packages()
    {
        return $this->hasMany(GymPackage::class, 'gym_category_id');
    }

    /**
     * Scope a query to only include active categories.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get dropdown for categories
     */
    public static function forDropdown($business_id, $active_only = true)
    {
        $query = self::where('business_id', $business_id);
        
        if ($active_only) {
            $query->active();
        }
        
        return $query->pluck('name', 'id')->toArray();
    }
}
