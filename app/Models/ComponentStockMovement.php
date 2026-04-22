<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Component;

class ComponentStockMovement extends Model
{
    protected $fillable = [
        'component_id',
        'qty',
        'type', // 'in' or 'out' or 'adjustment'
        'description',
        'reference_id',
        'user_id',
    ];

     public function getTypeAttribute($value)
    {
        return $value === 'in' ? 'in' : ($value === 'out' ? 'out' : 'adjustment');
    }

    public function component()
    {
        return $this->belongsTo(Component::class);
    }
}
