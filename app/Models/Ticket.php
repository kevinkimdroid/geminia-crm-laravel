<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps to vtiger_troubletickets (HelpDesk) + vtiger_crmentity.
 * Some Vtiger versions use vtiger_ticket instead.
 */
class Ticket extends Model
{
    protected $connection = 'vtiger';
    protected $table = 'vtiger_troubletickets';

    protected $primaryKey = 'ticketid';

    public $timestamps = false;

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'ticket_no',
    ];

    public static function listQuery()
    {
        return static::query()
            ->join('vtiger_crmentity as e', 'vtiger_troubletickets.ticketid', '=', 'e.crmid')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
            ->select('vtiger_troubletickets.*', 'e.createdtime', 'e.modifiedtime', 'e.smownerid', 'e.source');
    }
}
