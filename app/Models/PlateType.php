<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PlateVariant;

class PlateType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
    ];

    public function variants()
    {
        return $this->hasMany(PlateVariant::class);
    }
}
