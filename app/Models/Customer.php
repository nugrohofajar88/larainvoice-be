<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Branch;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'full_name',
        'contact_name',
        'phone_number',
        'address',
        'branch_id',
        'sales_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function machineOrders()
    {
        return $this->hasMany(MachineOrder::class);
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
