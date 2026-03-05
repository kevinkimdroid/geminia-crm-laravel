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
            ->leftJoin('vtiger_users as u', 'e.smownerid', '=', 'u.id')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
            ->select(
                'vtiger_troubletickets.*',
                'e.description',
                'e.createdtime',
                'e.modifiedtime',
                'e.smownerid',
                'e.source',
                'u.first_name as owner_first',
                'u.last_name as owner_last',
                'u.user_name as owner_username'
            );
    }

    /** Assignee display name (from smownerid). */
    public function getAssignedToNameAttribute(): ?string
    {
        $first = $this->attributes['owner_first'] ?? null;
        $last = $this->attributes['owner_last'] ?? null;
        $name = trim(($first ?? '') . ' ' . ($last ?? ''));
        return $name !== '' ? $name : ($this->attributes['owner_username'] ?? null);
    }

    /** Return description without the embedded Resolution section. */
    public function getDescriptionAttribute($value): ?string
    {
        $raw = $value ?? '';
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }
        if (str_contains($raw, '--- Resolution ---')) {
            $parts = explode('--- Resolution ---', $raw, 2);
            return trim($parts[0]);
        }
        return trim($raw);
    }

    /** Parse solution from description when stored as "--- Resolution ---\n{solution}". */
    public function getSolutionAttribute($value): ?string
    {
        if ($value !== null && trim((string) $value) !== '') {
            return trim((string) $value);
        }
        $raw = $this->attributes['description'] ?? '';
        if (is_string($raw) && str_contains($raw, '--- Resolution ---')) {
            $parts = explode('--- Resolution ---', $raw, 2);
            return isset($parts[1]) ? trim($parts[1]) : null;
        }
        return null;
    }
}
