<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $connection = 'vtiger';

    protected $table = 'complaints';

    protected $fillable = [
        'complaint_ref',
        'date_received',
        'complainant_name',
        'complainant_phone',
        'complainant_email',
        'contact_id',
        'policy_number',
        'nature',
        'description',
        'source',
        'status',
        'priority',
        'assigned_to',
        'date_resolved',
        'resolution_notes',
    ];

    protected $casts = [
        'date_received' => 'date',
        'date_resolved' => 'date',
    ];

    public const NATURES = [
        'Claim delay' => 'Claim delay',
        'Settlement dispute' => 'Settlement dispute',
        'Settlement amount' => 'Settlement amount',
        'Premium billing' => 'Premium billing',
        'Policy servicing' => 'Policy servicing',
        'Documentation' => 'Documentation',
        'Product mis-selling' => 'Product mis-selling',
        'Other' => 'Other',
    ];

    public const SOURCES = [
        'Phone' => 'Phone',
        'Email' => 'Email',
        'In person' => 'In person',
        'Written letter' => 'Written letter',
        'IRA referral' => 'IRA referral',
        'Other' => 'Other',
    ];

    public const STATUSES = [
        'Received' => 'Received',
        'Under Investigation' => 'Under Investigation',
        'Pending Response' => 'Pending Response',
        'Resolved' => 'Resolved',
        'Escalated to IRA' => 'Escalated to IRA',
        'Closed' => 'Closed',
    ];

    public const PRIORITIES = [
        'Low' => 'Low',
        'Medium' => 'Medium',
        'High' => 'High',
    ];

    public static function generateRef(): string
    {
        $year = date('Y');
        $last = static::where('complaint_ref', 'like', "CMP-{$year}-%")
            ->orderByDesc('id')
            ->value('complaint_ref');
        $seq = 1;
        if ($last && preg_match('/CMP-\d{4}-(\d+)/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }
        return sprintf('CMP-%s-%04d', $year, $seq);
    }
}
