<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchSynonym extends Model
{
    protected $fillable = [
        'keyword',
        'replacement',
    ];
}
