<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ResTable extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'capacity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($table) {
            if (empty($table->qr_token)) {
                $table->qr_token = Str::random(32);
            }
        });
    }

    public function selfOrderUrl(): string
    {
        return url('/self-order/' . $this->qr_token);
    }
}
