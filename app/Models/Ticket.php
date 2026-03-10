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
            ->leftJoin('vtiger_users as creator', 'e.smcreatorid', '=', 'creator.id')
            ->leftJoin('vtiger_users as modifier', 'e.modifiedby', '=', 'modifier.id')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['HelpDesk', 'Ticket'])
            ->select(
                'vtiger_troubletickets.*',
                'e.description',
                'e.createdtime',
                'e.modifiedtime',
                'e.smownerid',
                'e.smcreatorid',
                'e.modifiedby',
                'e.source',
                'u.first_name as owner_first',
                'u.last_name as owner_last',
                'u.user_name as owner_username',
                'creator.first_name as creator_first',
                'creator.last_name as creator_last',
                'creator.user_name as creator_username',
                'modifier.first_name as modifier_first',
                'modifier.last_name as modifier_last',
                'modifier.user_name as modifier_username'
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

    /** Creator display name (from smcreatorid). */
    public function getCreatedByNameAttribute(): ?string
    {
        $first = $this->attributes['creator_first'] ?? null;
        $last = $this->attributes['creator_last'] ?? null;
        $name = trim(($first ?? '') . ' ' . ($last ?? ''));
        return $name !== '' ? $name : ($this->attributes['creator_username'] ?? null);
    }

    /** Closer/modifier display name (from modifiedby). When status=Closed, this is who closed it. */
    public function getClosedByNameAttribute(): ?string
    {
        $first = $this->attributes['modifier_first'] ?? null;
        $last = $this->attributes['modifier_last'] ?? null;
        $name = trim(($first ?? '') . ' ' . ($last ?? ''));
        return $name !== '' ? $name : ($this->attributes['modifier_username'] ?? null);
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
