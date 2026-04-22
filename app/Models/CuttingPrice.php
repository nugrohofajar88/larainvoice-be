<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuttingPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_type_id',
        'plate_type_id',
        'size_id',
        'price_easy',
        'price_medium',
        'price_difficult',
        'price_per_minute',
        'discount_pct',
        'is_active',
    ];

    protected $casts = [
        'price_easy' => 'decimal:2',
        'price_medium' => 'decimal:2',
        'price_difficult' => 'decimal:2',
        'price_per_minute' => 'decimal:2',
        'discount_pct' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function machineType()
    {
        return $this->belongsTo(MachineType::class);
    }

    public function plateType()
    {
        return $this->belongsTo(PlateType::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }
}
