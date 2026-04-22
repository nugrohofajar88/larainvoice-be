<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatePriceHistory extends Model
{
    protected $fillable = [
        'plate_variant_id',
        'old_price',
        'new_price',
        'type', // 'SELL' or 'BUY'
        'user_id'
    ];

     public function getTypeAttribute($value)
    {
        return $value === 'SELL' ? 'SELL' : ($value === 'BUY' ? 'BUY' : null);
    }

    public function plateVariant()
    {
        return $this->belongsTo(PlateVariant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
