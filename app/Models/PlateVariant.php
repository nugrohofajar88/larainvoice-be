<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Branch;
use App\Models\PlateType;
use App\Models\Size;

class PlateVariant extends Model
{
    protected $fillable = [
        'branch_id',
        'plate_type_id',
        'size_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function plateType()
    {
        return $this->belongsTo(PlateType::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'plate_variant_id');
    }

    public function platePriceHistories()
    {
        return $this->hasMany(PlatePriceHistory::class, 'plate_variant_id');
    }
}
