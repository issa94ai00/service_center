<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    public $timestamps = false;

    protected $fillable = ['document_type', 'prefix', 'last_value'];

    protected function casts(): array
    {
        return [
            'last_value' => 'integer',
        ];
    }
}
