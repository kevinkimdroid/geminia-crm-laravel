<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base entity table - vtiger_crmentity.
 * Most Vtiger records link to this for createdtime, modifiedtime, smownerid, deleted.
 */
class CrmEntity extends Model
{
    protected $connection = 'vtiger';
    protected $table = 'vtiger_crmentity';

    protected $primaryKey = 'crmid';

    public $timestamps = false;

    protected $fillable = [
        'setype',
        'createdtime',
        'modifiedtime',
        'smownerid',
        'deleted',
    ];

    public function owner()
    {
        return $this->belongsTo(VtigerUser::class, 'smownerid', 'id');
    }
}
