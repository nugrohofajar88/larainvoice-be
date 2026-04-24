<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Invoice;
use App\Models\PlateVariant;
use App\Models\CuttingPrice;
use App\Models\Component;
use App\Models\CostType;
use App\Models\InvoiceItemFile;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_type',
        'item_type',
        'source_type',
        'source_id',
        'description',
        'unit',
        'plate_variant_id',
        'cutting_price_id',
        'component_id',
        'cost_type_id',
        'pricing_mode',
        'qty',
        'minutes',
        'price',
        'discount_pct',
        'discount_amount',
        'subtotal',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'price' => 'decimal:2',
        'discount_pct' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function plateVariant()
    {
        return $this->belongsTo(PlateVariant::class);
    }

    public function cuttingPrice()
    {
        return $this->belongsTo(CuttingPrice::class);
    }

    public function component()
    {
        return $this->belongsTo(Component::class);
    }

    public function costType()
    {
        return $this->belongsTo(CostType::class);
    }

    public function files()
    {
        return $this->hasMany(InvoiceItemFile::class);
    }
}
