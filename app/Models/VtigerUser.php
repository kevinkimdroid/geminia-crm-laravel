<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
     */
    public function getPrimaryRoleAttribute(): ?VtigerRole
    {
        return $this->roles()->first();
    }

    /**
     * Get allowed app module keys for this user based on their role's profile.
     * Administrators always see everything.
     */
    public function getAllowedModules(): array
    {
        $role = $this->primary_role;
        if (!$role) {
            return array_keys(config('modules.app_to_vtiger', []));
        }
        if (strcasecmp($role->rolename ?? '', 'Administrator') === 0) {
            return array_keys(config('modules.app_to_vtiger', []));
        }
        $profile = $role->profiles()->first();
        if (!$profile) {
            return array_keys(config('modules.app_to_vtiger', []));
        }
        return $profile->getAllowedAppModules();
    }
}
