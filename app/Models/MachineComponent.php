<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineComponent extends Model
{
    protected $fillable = [
        'machine_id',
        'component_id',
        'qty',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function component()
    {
        return $this->belongsTo(Component::class);
    }
}
