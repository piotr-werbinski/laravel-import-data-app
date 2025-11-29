<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    protected $fillable = [
        'file_name', 'total_records', 'successful_records', 'failed_records', 'status'
    ];

    public function logs()
    {
        return $this->hasMany(ImportLog::class);
    }
}
