<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTicketUpdate extends Model
{
    protected $fillable = [
        'work_ticket_id',
        'user_id',
        'update_text',
        'progress_percent',
        'time_spent_minutes',
        'is_blocked',
        'work_mode',
        'status_after_update',
        'blocker_reason',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(WorkTicket::class, 'work_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(VtigerUser::class, 'user_id');
    }
}
