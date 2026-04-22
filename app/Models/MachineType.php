<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Machine;

class MachineType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
    ];

    public function machines()
    {
        return $this->hasMany(Machine::class);
    }
}
