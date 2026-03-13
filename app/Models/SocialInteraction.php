<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialInteraction extends Model
{
    protected $fillable = [
        'social_account_id',
        'platform',
        'post_external_id',
        'external_id',
        'type',
        'author_name',
        'author_handle',
        'author_email',
        'author_phone',
        'content',
        'post_url',
        'lead_id',
        'metadata',
        'interaction_at',
    ];

    protected $casts = [
        'interaction_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const TYPE_COMMENT = 'comment';
    public const TYPE_REPLY = 'reply';
    public const TYPE_LIKE = 'like';
    public const TYPE_MENTION = 'mention';
    public const TYPE_DM = 'dm';

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
