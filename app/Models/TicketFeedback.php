<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFeedback extends Model
{
    protected $table = 'ticket_feedback';

    protected $fillable = ['ticket_id', 'contact_id', 'rating', 'comment'];

    public const RATING_HAPPY = 'happy';
    public const RATING_NOT_HAPPY = 'not_happy';

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(\stdClass::class, 'ticket_id', 'ticketid');
    }

    public static function ratings(): array
    {
        return [
            'happy' => 'Yes, I was happy with the service',
            'not_happy' => 'No, I was not satisfied',
        ];
    }
}
