<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineOrderLog extends Model
{
    protected $fillable = [
        'machine_order_id',
        'user_id',
        'action_type',
        'from_status',
        'to_status',
        'note',
        'meta',
    ];

    protected $casts = [
        'machine_order_id' => 'integer',
        'user_id' => 'integer',
        'meta' => 'array',
    ];

    public function machineOrder()
    {
        return $this->belongsTo(MachineOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
