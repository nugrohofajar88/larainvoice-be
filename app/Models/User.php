<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone_number',
        'role_id',
        'branch_id',
        'deleted_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesProfile()
    {
        return $this->hasOne(SalesProfile::class);
    }

    public function componentStockMovements()
    {
        return $this->hasMany(ComponentStockMovement::class);
    }

    public function componentPriceHistories()
    {
        return $this->hasMany(ComponentPriceHistory::class);
    }

    public function machineOrdersAsSales()
    {
        return $this->hasMany(MachineOrder::class, 'sales_id');
    }

    public function createdMachineOrders()
    {
        return $this->hasMany(MachineOrder::class, 'created_by');
    }

    public function updatedMachineOrders()
    {
        return $this->hasMany(MachineOrder::class, 'updated_by');
    }

    public function receivedMachineOrderPayments()
    {
        return $this->hasMany(MachineOrderPayment::class, 'received_by');
    }

    public function paymentsHandled()
    {
        return $this->hasMany(Payment::class);
    }

    public function isSuperAdmin()
    {
        return in_array($this->role->name, ['administrator', 'admin pusat']);
    }

    public function isAdmin()
    {
        return in_array($this->role->name, ['administrator', 'admin pusat', 'admin cabang']);
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
