<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps to vtiger_potential (Deals/Opportunities) + vtiger_crmentity.
 */
class Deal extends Model
{
    protected $connection = 'vtiger';
    protected $table = 'vtiger_potential';

    protected $primaryKey = 'potentialid';

    public $timestamps = false;

    protected $fillable = [
        'potentialname',
        'amount',
        'sales_stage',
        'closingdate',
        'related_to',
        'contact_id',
        'leadsource',
    ];

    public static function listQuery()
    {
        return static::query()
            ->join('vtiger_crmentity as e', 'vtiger_potential.potentialid', '=', 'e.crmid')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['Potentials', 'Opportunity'])
            ->select('vtiger_potential.*', 'e.createdtime', 'e.modifiedtime', 'e.smownerid');
    }
}
