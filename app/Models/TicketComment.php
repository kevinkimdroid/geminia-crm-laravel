<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    protected $table = 'ticket_comments';

    protected $fillable = ['ticket_id', 'user_id', 'author_name', 'body'];

    /** Display name for the comment author. */
    public function getAuthorDisplayAttribute(): string
    {
        return $this->author_name ?: 'Unknown';
    }
}
