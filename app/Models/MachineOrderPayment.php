<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineOrderPayment extends Model
{
    protected $fillable = [
        'machine_order_id',
        'payment_date',
        'payment_type',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'received_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function machineOrder()
    {
        return $this->belongsTo(MachineOrder::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
