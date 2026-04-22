<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PlateVariant;

class StockMovement extends Model
{
    protected $fillable = [
        'plate_variant_id',
        'qty',
        'type', // 'in' or 'out' or 'adjustment'
        'description',
        'reference_id',
        'user_id'
    ];

     public function getTypeAttribute($value)
    {
        return $value === 'in' ? 'in' : ($value === 'out' ? 'out' : 'adjustment');
    }

    public function plateVariant()
    {
        return $this->belongsTo(PlateVariant::class);
    }
}
