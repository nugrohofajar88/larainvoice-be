<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class SalesProfile extends Model
{
    protected $fillable = [
        'user_id',
        'nik',
        'email',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
