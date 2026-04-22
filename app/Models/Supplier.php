<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Branch;
use App\Models\Component;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'city',
        'notes',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function components()
    {
        return $this->hasMany(Component::class);
    }
}
