<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineOrderAssignment extends Model
{
    protected $fillable = [
        'machine_order_id',
        'user_id',
        'role',
        'notes',
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
