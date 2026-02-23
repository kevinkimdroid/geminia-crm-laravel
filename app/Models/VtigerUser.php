<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Maps to vtiger_users table.
 * Vtiger uses MD5/crypt for passwords - we handle verification separately.
 */
class VtigerUser extends Authenticatable
{
    use Notifiable;

    protected $connection = 'vtiger';
    protected $table = 'vtiger_users';

    protected $fillable = [
        'user_name',
        'first_name',
        'last_name',
        'email1',
        'status',
    ];

    protected $hidden = [
        'user_password',
        'remember_token',
    ];

    public function getAuthPassword()
    {
        return $this->user_password;
    }

    public function getAuthIdentifierName()
    {
        return 'id';
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name) ?: $this->user_name;
    }

    /**
     * Verify Vtiger password (MD5 or crypt).
     */
    public static function verifyPassword(string $plain, string $hashed): bool
    {
        if (strlen($hashed) === 32 && ctype_xdigit($hashed)) {
            return md5($plain) === $hashed;
        }
        return password_verify($plain, $hashed) || crypt($plain, $hashed) === $hashed;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            VtigerRole::class,
            'vtiger_user2role',
            'userid',
            'roleid',
            'id',
            'roleid'
        );
    }

    /**
     * Get the primary role (first assigned role).
     * Cached to avoid repeated DB queries.
     */
    public function getPrimaryRoleAttribute(): ?VtigerRole
    {
        if (array_key_exists('primary_role', $this->relations)) {
            return $this->getRelation('primary_role');
        }
        $role = $this->roles()->limit(1)->first();
        $this->setRelation('primary_role', $role);
        return $role;
    }

    /**
     * Get allowed app module keys for this user based on their role's profile.
     * Administrators always see everything.
     * Cached per user for 10 min to avoid repeated remote DB queries.
     */
    public function getAllowedModules(): array
    {
        return Cache::remember('geminia_allowed_modules_' . $this->id, 600, function () {
            return $this->fetchAllowedModules();
        });
    }

    /**
     * Fetch allowed modules (uncached). Used internally.
     */
    protected function fetchAllowedModules(): array
    {
        $allKeys = array_keys(config('modules.app_to_vtiger', []));

        try {
            $role = DB::connection('vtiger')
                ->table('vtiger_user2role')
                ->join('vtiger_role', 'vtiger_user2role.roleid', '=', 'vtiger_role.roleid')
                ->where('vtiger_user2role.userid', $this->id)
                ->orderBy('vtiger_user2role.roleid')
                ->select('vtiger_user2role.roleid', 'vtiger_role.rolename')
                ->first();
            if (!$role) {
                return $allKeys;
            }
            if (strcasecmp($role->rolename ?? '', 'Administrator') === 0) {
                return $allKeys;
            }

            $profileId = DB::connection('vtiger')
                ->table('vtiger_role2profile')
                ->where('roleid', $role->roleid)
                ->orderBy('profileid')
                ->value('profileid');
            if (!$profileId) {
                return $allKeys;
            }

            $tabNames = DB::connection('vtiger')
                ->table('vtiger_profile2tab')
                ->join('vtiger_tab', 'vtiger_profile2tab.tabid', '=', 'vtiger_tab.tabid')
                ->where('vtiger_profile2tab.profileid', $profileId)
                ->pluck('vtiger_tab.name')
                ->toArray();

            $allowed = [];
            foreach (config('modules.app_to_vtiger', []) as $appKey => $vtigerName) {
                if ($vtigerName === null || in_array($vtigerName, $tabNames, true)) {
                    $allowed[] = $appKey;
                }
            }
            return $allowed;
        } catch (\Throwable $e) {
            return $allKeys;
        }
    }
}
