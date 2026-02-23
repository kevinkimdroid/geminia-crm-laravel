<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'template_name',
        'subject',
        'description',
        'module_name',
        'body',
    ];
}
