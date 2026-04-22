<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;

class PaymentFile extends Model
{
    protected $fillable = [
        'payment_id',
        'file_path',
        'file_name',
        'file_extension',
        'file_size',
        'mime_type',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
