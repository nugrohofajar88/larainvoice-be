<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Branch;

class BranchBankAccount extends Model
{
    protected $fillable = [
        'branch_id',
        'bank_name',
        'account_number',
        'account_holder',
        'bank_code',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
