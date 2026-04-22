<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostType extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function machineOrderCosts()
    {
        return $this->hasMany(MachineOrderCost::class);
    }
}
