<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    protected $fillable = [
        'user_id',
        'keyword',
        'clicked_result',
        'result_type',
        'result_id',
        'results_count',
        'device_type',
    ];
}
