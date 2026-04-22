<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Branch;

class BranchSetting extends Model
{
    protected $fillable = [
        'branch_id',
        'minimum_stock',
        'sales_commission_percentage',
        'invoice_header_name',
        'invoice_header_position',
        'invoice_footer_note',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
