<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'contact_id',
        'erp_message_id',
        'erp_policy_no',
        'phone',
        'message',
        'status',
        'delivery_status',
        'advanta_message_id',
        'advanta_status',
        'advanta_delivery_tat',
        'error_message',
        'provider_response',
        'user_id',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'provider_response' => 'array',
    ];
}
