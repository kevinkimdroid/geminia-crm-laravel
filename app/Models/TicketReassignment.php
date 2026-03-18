<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketReassignment extends Model
{
    protected $table = 'ticket_reassignments';

    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'from_user_id',
        'from_user_name',
        'to_user_id',
        'to_user_name',
        'reassigned_by_user_id',
        'reassigned_by_name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
