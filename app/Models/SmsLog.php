<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'contact_id',
        'phone',
        'message',
        'status',
        'error_message',
        'user_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
