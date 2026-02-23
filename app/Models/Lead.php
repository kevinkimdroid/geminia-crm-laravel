<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps to vtiger_leaddetails + vtiger_crmentity.
 */
class Lead extends Model
{
    protected $connection = 'vtiger';
    protected $table = 'vtiger_leaddetails';

    protected $primaryKey = 'leadid';

    public $timestamps = false;

    protected $fillable = [
        'firstname',
        'lastname',
        'company',
        'email',
        'designation',
        'leadstatus',
        'leadsource',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->firstname . ' ' . $this->lastname) ?: $this->company ?: 'Unknown';
    }

    /**
     * Phone is stored in vtiger_leadaddress (mobile). Expose as 'phone' for consistency.
     */
    public function getPhoneAttribute(): ?string
    {
        return $this->attributes['mobile'] ?? null;
    }

    public static function listQuery()
    {
        return static::query()
            ->join('vtiger_crmentity as e', 'vtiger_leaddetails.leadid', '=', 'e.crmid')
            ->leftJoin('vtiger_leadaddress as la', 'vtiger_leaddetails.leadid', '=', 'la.leadaddressid')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['Leads', 'Lead'])
            ->select('vtiger_leaddetails.*', 'e.createdtime', 'e.modifiedtime', 'e.smownerid', 'la.mobile');
    }
}
