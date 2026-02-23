<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VtigerProfile extends Model
{
    protected $connection = 'vtiger';
    protected $table = 'vtiger_profile';

    protected $primaryKey = 'profileid';

    protected $fillable = [
        'profilename',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            VtigerRole::class,
            'vtiger_role2profile',
            'profileid',
            'roleid',
            'profileid',
            'roleid'
        );
    }

    public function tabs(): BelongsToMany
    {
        return $this->belongsToMany(
            VtigerTab::class,
            'vtiger_profile2tab',
            'profileid',
            'tabid',
            'profileid',
            'tabid'
        );
    }

    /**
     * Get allowed app module keys for this profile.
     */
    public function getAllowedAppModules(): array
    {
        $tabNames = $this->tabs()->pluck('name')->toArray();
        $allowed = [];
        foreach (config('modules.app_to_vtiger', []) as $appKey => $vtigerName) {
            if ($vtigerName === null || in_array($vtigerName, $tabNames, true)) {
                $allowed[] = $appKey;
            }
        }
        return $allowed;
    }
}
