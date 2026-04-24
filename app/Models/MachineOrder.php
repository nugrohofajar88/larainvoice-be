<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MachineOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'order_number',
        'order_date',
        'customer_id',
        'sales_id',
        'machine_id',
        'qty',
        'machine_name_snapshot',
        'base_price',
        'discount_type',
        'discount_value',
        'subtotal',
        'additional_cost_total',
        'grand_total',
        'paid_total',
        'remaining_total',
        'estimated_start_date',
        'estimated_finish_date',
        'actual_finish_date',
        'notes',
        'internal_notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'estimated_start_date' => 'date',
        'estimated_finish_date' => 'date',
        'actual_finish_date' => 'date',
        'base_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'additional_cost_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'remaining_total' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function costs()
    {
        return $this->hasMany(MachineOrderCost::class);
    }

    public function payments()
    {
        return $this->hasMany(MachineOrderPayment::class);
    }

    public function assignments()
    {
        return $this->hasMany(MachineOrderAssignment::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'machine_order_assignments')
            ->withPivot(['role', 'notes'])
            ->withTimestamps();
    }

    public function components()
    {
        return $this->hasMany(MachineOrderComponent::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'source_id')
            ->where('source_type', 'machine_order');
    }

    public function logs()
    {
        return $this->hasMany(MachineOrderLog::class)->oldest('id');
    }
}
