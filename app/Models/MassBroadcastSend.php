<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MassBroadcastSend extends Model
{
    protected $table = 'mass_broadcast_sends';

    protected $fillable = [
        'contact_id',
        'channel',
        'content_digest',
        'subject_snapshot',
        'user_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
