<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Component;
use App\Models\Customer;
use App\Models\Machine;
use App\Models\MachineOrder;
use App\Models\Supplier;
use App\Models\BranchSetting;
use App\Models\BranchBankAccount;
use App\Models\BranchInvoiceCounter;


class Branch extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'city',
        'address',
        'phone',
        'email',
        'website',
    ];
    
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function machines()
    {
        return $this->hasMany(Machine::class);
    }

    public function machineOrders()
    {
        return $this->hasMany(MachineOrder::class);
    }

    public function components()
    {
        return $this->hasMany(Component::class);
    }

    public function setting()
    {
        return $this->hasOne(BranchSetting::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(BranchBankAccount::class);
    }

    public function invoiceCounter()
    {
        return $this->hasOne(BranchInvoiceCounter::class);
    }
}
