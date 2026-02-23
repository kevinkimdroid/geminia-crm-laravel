<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $connection = 'vtiger';

    protected $fillable = [
        'campaign_name',
        'campaign_type',
        'campaign_status',
        'expected_revenue',
        'expected_close_date',
        'assigned_to',
        'list_name',
        'tags',
    ];

    protected $casts = [
        'expected_close_date' => 'date',
        'expected_revenue' => 'decimal:2',
    ];
}
