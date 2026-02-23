<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactFollowup extends Model
{
    protected $connection = 'vtiger';

    protected $fillable = [
        'contact_id',
        'user_id',
        'note',
        'followup_date',
        'status',
    ];

    protected $casts = [
        'followup_date' => 'date',
    ];
}
