<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_no',
        'customer_name',
        'policy_number',
        'phone',
        'amount',
        'currency',
        'payment_method',
        'status',
        'expected_at',
        'paid_at',
        'tat_due_at',
        'tat_breached_at',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expected_at' => 'date',
        'paid_at' => 'datetime',
        'tat_due_at' => 'datetime',
        'tat_breached_at' => 'datetime',
    ];
}
