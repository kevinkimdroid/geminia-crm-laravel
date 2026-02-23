<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps to vtiger_contactdetails + vtiger_crmentity.
 */
class Contact extends Model
{
    protected $connection = 'vtiger';
    protected $table = 'vtiger_contactdetails';

    protected $primaryKey = 'contactid';

    public $timestamps = false;

    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'phone',
        'mobile',
        'title',
        'department',
        'accountid',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->firstname . ' ' . $this->lastname) ?: 'Unknown';
    }

    public function entity()
    {
        return $this->belongsTo(CrmEntity::class, 'contactid', 'crmid');
    }

    public static function listQuery()
    {
        return static::query()
            ->join('vtiger_crmentity as e', 'vtiger_contactdetails.contactid', '=', 'e.crmid')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['Contacts', 'Contact'])
            ->select('vtiger_contactdetails.*', 'e.createdtime', 'e.modifiedtime', 'e.smownerid');
    }
}
