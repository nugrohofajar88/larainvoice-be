<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'order_number',
        'order_type',
        'order_date',
        'customer_id',
        'status',
        'title',
        'category',
        'description',
        'location',
        'planned_start_date',
        'duration_days',
        'actual_start_date',
        'actual_finish_date',
        'completion_notes',
        'notes',
        'internal_notes',
        'invoice_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'planned_start_date' => 'date',
        'actual_start_date' => 'date',
        'actual_finish_date' => 'date',
        'duration_days' => 'integer',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function assignments()
    {
        return $this->hasMany(ServiceOrderAssignment::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'service_order_assignments')
            ->withPivot(['role', 'notes'])
            ->withTimestamps();
    }

    public function components()
    {
        return $this->hasMany(ServiceOrderComponent::class);
    }

    public function logs()
    {
        return $this->hasMany(ServiceOrderLog::class)->oldest('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
