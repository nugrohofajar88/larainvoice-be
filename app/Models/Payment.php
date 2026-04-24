<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Invoice;
use App\Models\Branch;
use App\Models\BranchBankAccount;
use App\Models\PaymentFile;
use App\Models\User;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'branch_id',
        'bank_account_id',
        'user_id',
        'amount',
        'payment_method',
        'payment_type',
        'is_dp',
        'payment_date',
        'proof_image',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_dp' => 'boolean',
        'payment_date' => 'date',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BranchBankAccount::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function files()
    {
        return $this->hasMany(PaymentFile::class);
    }
}
