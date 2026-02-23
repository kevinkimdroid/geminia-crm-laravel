<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PbxCall extends Model
{
    protected $fillable = [
        'call_status',
        'direction',
        'customer_number',
        'reason_for_calling',
        'customer_name',
        'user_name',
        'recording_url',
        'recording_path',
        'duration_sec',
        'start_time',
        'external_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
    ];

    public function hasRecording(): bool
    {
        return ! empty($this->recording_url) || ! empty($this->recording_path);
    }
}
