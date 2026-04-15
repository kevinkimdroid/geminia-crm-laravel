<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserReportingLine extends Model
{
    protected $fillable = [
        'user_id',
        'manager_id',
    ];
}
