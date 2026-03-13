<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PbxExtensionMapping extends Model
{
    protected $fillable = [
        'extension',
        'vtiger_user_id',
        'user_name',
    ];
}
