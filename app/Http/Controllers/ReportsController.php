<?php

namespace App\Http\Controllers;

use App\Exports\PipelineByStageExport;
use App\Exports\SalesByPersonExport;
use App\Exports\SlaBrokenExport;
use App\Exports\TicketAgingExport;
use App\Services\CrmService;
use App\Services\TicketSlaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportsController
{
    public function index(CrmService $crm): View
    {
        return view('reports', $crm->getReportsIndexData(120));
    }

    public function slaBroken(TicketSlaService $sla): View
    {
        $tickets = $sla->getBrokenSlaTickets(100);
        return view('reports.sla-broken', ['tickets' => $tickets]);
    }

    public function ticketAging(CrmService $crm, Request $request): View
    {
        $days = (int) $request->get('days', 7);
        $tickets = $crm->getTicketAgingReport($days, 200);
        return view('reports.ticket-aging', [
            'tickets' => $tickets,
            'days' => $days,
        ]);
    }

    public function contactsSummary(CrmService $crm, Request $request): View
    {
        $days = (int) $request->get('days', 30);
        $summary = $crm->getContactsSummaryReport($days);
        return view('reports.contacts-summary', $summary);
    }

    public function callsSummary(CrmService $crm): View
    {
        $data = $crm->getCallsSummaryReport();
        return view('reports.calls-summary', $data);
    }

    public function exportSlaBroken(TicketSlaService $sla, Request $request)
    {
        $tickets = $sla->getBrokenSlaTickets(500);
        $rows = $tickets->map(fn ($t) => [
            $t->ticket_no ?? 'TT' . $t->ticketid,
            $t->title ?? '',
            $t->category ?? 'General',
            $t->status ?? '',
            trim(($t->contact_first ?? '') . ' ' . ($t->contact_last ?? '')) ?: '',
            $t->createdtime ?? '',
            $t->tat_hours ?? 24,
            $t->hours_overdue ?? 0,
        ])->toArray();
        if ($request->get('format') === 'xlsx') {
            return Excel::download(new SlaBrokenExport($rows), 'broken-sla-report-' . date('Y-m-d') . '.xlsx');
        }
        return $this->csvResponse($rows, ['Ticket', 'Title', 'Department', 'Status', 'Contact', 'Created', 'TAT (h)', 'Hours Overdue'], 'broken-sla-report');
    }

    public function exportTicketAging(CrmService $crm, Request $request)
    {
        $days = (int) $request->get('days', 7);
        $tickets = $crm->getTicketAgingReport($days, 500);
        $rows = $tickets->map(fn ($t) => [
            $t->ticket_no ?? 'TT' . $t->ticketid,
            $t->title ?? '',
            $t->status ?? '',
            $t->category ?? 'General',
            trim(($t->firstname ?? '') . ' ' . ($t->lastname ?? '')) ?: '',
            $t->createdtime ?? '',
            trim(($t->owner_first ?? '') . ' ' . ($t->owner_last ?? '')) ?: 'Unassigned',
        ])->toArray();
        $filename = 'ticket-aging-' . $days . 'd-' . date('Y-m-d');
        if ($request->get('format') === 'xlsx') {
            return Excel::download(new TicketAgingExport($rows), $filename . '.xlsx');
        }
        return $this->csvResponse($rows, ['Ticket', 'Title', 'Status', 'Category', 'Contact', 'Created', 'Assigned To'], 'ticket-aging-' . $days . 'd');
    }

    public function exportSalesByPerson(CrmService $crm, Request $request)
    {
        $data = $crm->getSalesByPerson(100);
        $rows = $data->map(fn ($r) => [trim($r->name) ?: 'Unassigned', $r->total])->toArray();
        if ($request->get('format') === 'xlsx') {
            return Excel::download(new SalesByPersonExport($rows), 'sales-by-person-' . date('Y-m-d') . '.xlsx');
        }
        return $this->csvResponse(array_map(fn ($r) => [$r[0], number_format($r[1], 0)], $rows), ['Salesperson', 'Revenue (KES)']);
    }

    public function exportPipelineByStage(CrmService $crm, Request $request)
    {
        $data = $crm->getPipelineByStage();
        $rows = [];
        foreach ($data as $stage => $d) {
            $rows[] = [$stage, $d['count'], $d['amount']];
        }
        if ($request->get('format') === 'xlsx') {
            return Excel::download(new PipelineByStageExport($rows), 'pipeline-by-stage-' . date('Y-m-d') . '.xlsx');
        }
        return $this->csvResponse(array_map(fn ($r) => [$r[0], $r[1], number_format($r[2], 0)], $rows), ['Stage', 'Count', 'Amount (KES)']);
    }

    public function exportAllExcel(CrmService $crm, TicketSlaService $sla, Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filename = 'reports-all-' . date('Y-m-d') . '.xlsx';
        return Excel::download(new \App\Exports\ReportsAllExport($crm, $sla, (int) $request->get('days', 7)), $filename);
    }

    protected function csvResponse(array $rows, array $headers, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Response::streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename . '-' . date('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '-' . date('Y-m-d') . '.csv"',
        ]);
    }
}
