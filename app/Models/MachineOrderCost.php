<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineOrderCost extends Model
{
    protected $fillable = [
        'machine_order_id',
        'cost_type_id',
        'cost_name_snapshot',
        'description',
        'qty',
        'price',
        'total',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function machineOrder()
    {
        return $this->belongsTo(MachineOrder::class);
    }

    public function costType()
    {
        return $this->belongsTo(CostType::class);
    }
}
