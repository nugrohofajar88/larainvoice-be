<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComponentCategory extends Model
{
    protected $fillable = [
        'name',
    ];

    public function components()
    {
        return $this->hasMany(Component::class);
    }
}
