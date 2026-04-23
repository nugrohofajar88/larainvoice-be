<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileDeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'branch_id',
        'provider',
        'platform',
        'token',
        'token_hash',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
