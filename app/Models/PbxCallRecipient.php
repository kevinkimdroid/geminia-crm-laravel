<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PbxCallRecipient extends Model
{
    protected $fillable = [
        'call_source',
        'call_id',
        'received_by_user_id',
        'received_by_user_name',
    ];

    public const SOURCE_VTIGER = 'vtiger';
    public const SOURCE_LOCAL = 'local';
}
