<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComponentPriceHistory extends Model
{
    protected $fillable = [
        'component_id',
        'old_price',
        'new_price',
        'type',
        'user_id',
    ];

    public function getTypeAttribute($value)
    {
        return $value === 'SELL' ? 'SELL' : ($value === 'BUY' ? 'BUY' : null);
    }

    public function component()
    {
        return $this->belongsTo(Component::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
