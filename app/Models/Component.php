<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Branch;
use App\Models\ComponentCategory;
use App\Models\Supplier;

class Component extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'type_size',
        'weight',
        'supplier_id',
        'component_category_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function componentCategory()
    {
        return $this->belongsTo(ComponentCategory::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(ComponentStockMovement::class, 'component_id');
    }

    public function componentPriceHistories()
    {
        return $this->hasMany(ComponentPriceHistory::class, 'component_id');
    }

    public function machineComponents()
    {
        return $this->hasMany(MachineComponent::class, 'component_id');
    }

    public function machineOrderComponents()
    {
        return $this->hasMany(MachineOrderComponent::class, 'component_id');
    }

    public function machines()
    {
        return $this->belongsToMany(Machine::class, 'machine_components')
            ->withPivot('qty')
            ->withTimestamps();
    }
}
