<?php

namespace App\Http\Controllers;

use App\Services\TicketAutoCreateService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaturitiesController extends Controller
{
    public function __construct(
        protected TicketAutoCreateService $maturityService
    ) {}

    /**
     * List policies maturing within configured days. Enables ticketing for each.
     */
    public function index(Request $request): View
    {
        $days = max(7, min(90, (int) ($request->get('days') ?: config('tickets.auto_maturity.days_before', 30))));
        $policies = $this->maturityService->getMaturingPoliciesList($days);

        return view('support.maturities', [
            'policies' => $policies,
            'days' => $days,
        ]);
    }
}
