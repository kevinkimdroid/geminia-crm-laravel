<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkTicket extends Model
{
    protected $fillable = [
        'ticket_no',
        'title',
        'description',
        'status',
        'priority',
        'assignee_id',
        'reporting_manager_id',
        'created_by',
        'due_date',
        'started_at',
        'completed_at',
    ];

    public function updates(): HasMany
    {
        return $this->hasMany(WorkTicketUpdate::class)->latest();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(VtigerUser::class, 'assignee_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(VtigerUser::class, 'reporting_manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(VtigerUser::class, 'created_by');
    }
}
