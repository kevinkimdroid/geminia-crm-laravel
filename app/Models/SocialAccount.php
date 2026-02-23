<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    protected $fillable = [
        'platform',
        'account_id',
        'account_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'metadata',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function platforms(): array
    {
        return ['facebook', 'instagram', 'twitter', 'youtube', 'tiktok'];
    }

    public function isExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }
        return $this->token_expires_at->isPast();
    }
}
