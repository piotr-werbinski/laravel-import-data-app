<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $fillable = [
        'import_id', 'transaction_id', 'error_message'
    ];

    public function import()
    {
        return $this->belongsTo(Import::class);
    }
}
