<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Branch;
use App\Models\MachineType;
use App\Models\Component;

class Machine extends Model
{
    protected $fillable = [
        'machine_number',
        'machine_type_id',
        'branch_id',
        'base_price',
        'weight',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function type()
    {
        // Mendefinisikan FK secara eksplisit karena nama method (type) 
        // berbeda dengan nama kolom (machine_type_id).
        return $this->belongsTo(MachineType::class, 'machine_type_id');
    }

    public function files()
    {
        return $this->hasMany(MachineFile::class);
    }

    public function machineComponents()
    {
        return $this->hasMany(MachineComponent::class);
    }

    public function components()
    {
        return $this->belongsToMany(Component::class, 'machine_components')
            ->withPivot('qty')
            ->withTimestamps();
    }

    public function machineOrders()
    {
        return $this->hasMany(MachineOrder::class);
    }
}
