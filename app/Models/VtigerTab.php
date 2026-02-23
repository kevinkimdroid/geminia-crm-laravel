<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VtigerTab extends Model
{
    protected $connection = 'vtiger';
    protected $table = 'vtiger_tab';

    protected $primaryKey = 'tabid';

    public $timestamps = false;
}
