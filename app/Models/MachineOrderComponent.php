<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineOrderComponent extends Model
{
    protected $fillable = [
        'machine_order_id',
        'component_id',
        'component_name_snapshot',
        'qty',
        'notes',
        'is_optional',
        'stock_deducted_qty',
    ];

    protected $casts = [
        'is_optional' => 'boolean',
    ];

    public function machineOrder()
    {
        return $this->belongsTo(MachineOrder::class);
    }

    public function component()
    {
        return $this->belongsTo(Component::class);
    }
}
