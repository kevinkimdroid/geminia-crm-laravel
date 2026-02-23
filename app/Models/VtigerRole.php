<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VtigerRole extends Model
{
    protected $connection = 'vtiger';
    protected $table = 'vtiger_role';

    protected $primaryKey = 'roleid';

    public $incrementing = false;

    protected $fillable = [
        'rolename',
        'parentrole',
        'depth',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            VtigerUser::class,
            'vtiger_user2role',
            'roleid',
            'userid',
            'roleid',
            'id'
        );
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(
            VtigerProfile::class,
            'vtiger_role2profile',
            'roleid',
            'profileid',
            'roleid',
            'profileid'
        );
    }
}
