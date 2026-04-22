<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Customer;
use App\Models\Machine;
use App\Models\User;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Branch;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'branch_id',
        'customer_id',
        'machine_id',
        'user_id',
        'transaction_date',
        'status',
        'total_amount',
        'discount_pct',
        'discount_amount',
        'grand_total',
        'notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount_pct' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class)->withDefault([
            'machine_number' => '-',
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
