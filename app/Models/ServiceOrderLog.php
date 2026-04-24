<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrderLog extends Model
{
    protected $fillable = [
        'service_order_id',
        'user_id',
        'action_type',
        'from_status',
        'to_status',
        'note',
        'meta',
    ];

    protected $casts = [
        'service_order_id' => 'integer',
        'user_id' => 'integer',
        'meta' => 'array',
    ];

    public function serviceOrder()
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
