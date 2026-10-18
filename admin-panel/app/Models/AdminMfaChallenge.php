<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminMfaChallenge extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected $hidden = ['payload', 'otp_hash', 'password_hash', 'session_hash'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'expires_at' => 'datetime', 'verified_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
