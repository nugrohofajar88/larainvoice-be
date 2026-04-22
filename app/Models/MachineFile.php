<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineFile extends Model
{
    protected $fillable = [
        'machine_id',
        'file_path',
        'file_name',
        'file_extension',
        'file_size',
        'mime_type',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
}
