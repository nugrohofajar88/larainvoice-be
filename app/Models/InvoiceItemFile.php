<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\InvoiceItem;

class InvoiceItemFile extends Model
{
    protected $fillable = [
        'invoice_item_id',
        'file_path',
        'file_name',
        'file_extension',
        'file_size',
        'mime_type',
    ];

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
