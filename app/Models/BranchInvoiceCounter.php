<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Branch;

class BranchInvoiceCounter extends Model
{
    protected $fillable = [
        'branch_id',
        'prefix',
        'month',
        'year',
        'last_number',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
