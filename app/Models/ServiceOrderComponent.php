<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrderComponent extends Model
{
    protected $fillable = [
        'service_order_id',
        'component_id',
        'component_name_snapshot',
        'qty',
        'notes',
        'billable',
    ];

    protected $casts = [
        'billable' => 'boolean',
    ];

    public function serviceOrder()
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function component()
    {
        return $this->belongsTo(Component::class);
    }
}
