<?php

namespace App\Http\Controllers;

use App\Services\UserDepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinancePaymentController extends Controller
{
    private function denyUnlessFinance(): ?RedirectResponse
    {
        $user = Auth::guard('vtiger')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (method_exists($user, 'isAdministrator') && $user->isAdministrator()) {
            return null;
        }

        $department = strtolower(trim((string) app(UserDepartmentService::class)->getDepartment((int) $user->id)));
        $roleName = strtolower(trim((string) ($user->primary_role->rolename ?? '')));
        $email = strtolower(trim((string) ($user->email1 ?? '')));
        $isFinance = str_contains($department, 'finance')
            || str_contains($roleName, 'finance')
            || str_contains($email, 'finance');

        if (!$isFinance) {
            return redirect()->route('dashboard')
                ->with('error', 'Access denied: Finance module can only be accessed by Finance department users and Administrators.');
        }

        return null;
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($deny = $this->denyUnlessFinance()) {
            return $deny;
        }
        if (! extension_loaded('oci8')) {
            return redirect()->route('dashboard')
                ->with('error', 'Finance payments require direct Oracle access (OCI8 extension is not enabled on this server).');
        }
        if (! config('erp.enabled', true)) {
            return redirect()->route('dashboard')
                ->with('error', 'Finance payments require ERP/Oracle. Set ERP_ENABLED=true and enable OCI8 to use this module.');
        }

        $search = trim((string) $request->get('search', ''));
        $source = $request->filled('source') ? (int) $request->get('source') : null;
        $dateFrom = trim((string) $request->get('date_from', ''));
        $dateTo = trim((string) $request->get('date_to', ''));

        $query = $this->financeChequesBaseQuery();
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('c.cqr_ref', 'like', '%' . $search . '%')
                    ->orWhere('c.cqr_payee', 'like', '%' . $search . '%')
                    ->orWhere('c.cqr_narrative', 'like', '%' . $search . '%')
                    ->orWhere('c.cqr_fms_remarks', 'like', '%' . $search . '%');
            });
        }
        if ($source !== null) {
            $query->where('c.cqr_source', $source);
        }
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $query->whereRaw("TRUNC(c.cqr_ref_date) >= TO_DATE(?, 'YYYY-MM-DD')", [$dateFrom]);
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $query->whereRaw("TRUNC(c.cqr_ref_date) <= TO_DATE(?, 'YYYY-MM-DD')", [$dateTo]);
        }

        $payments = $query->selectRaw('
                s.sys_name as sys_source,
                c.cqr_source,
                c.cqr_ref,
                c.cqr_ref_date,
                c.cqr_narrative,
                c.cqr_brh_code,
                c.cqr_fms_remarks,
                c.cqr_amount,
                c.cqr_payee
            ')
            ->orderByDesc('c.cqr_ref_date')
            ->paginate(20)
            ->withQueryString();

        $statsBase = $this->financeChequesBaseQuery();
        if ($source !== null) {
            $statsBase->where('c.cqr_source', $source);
        }
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $statsBase->whereRaw("TRUNC(c.cqr_ref_date) >= TO_DATE(?, 'YYYY-MM-DD')", [$dateFrom]);
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $statsBase->whereRaw("TRUNC(c.cqr_ref_date) <= TO_DATE(?, 'YYYY-MM-DD')", [$dateTo]);
        }

        $stats = [
            'total_count' => (clone $statsBase)->count(),
            'total_amount' => (float) ((clone $statsBase)->sum('c.cqr_amount') ?? 0),
            'today_count' => (clone $statsBase)
                ->whereRaw("TRUNC(c.cqr_ref_date) = TO_DATE(?, 'YYYY-MM-DD')", [now()->toDateString()])
                ->count(),
            'distinct_payees' => (clone $statsBase)->distinct('c.cqr_payee')->count('c.cqr_payee'),
        ];

        $sourceOptions = $this->financeChequesBaseQuery()
            ->selectRaw('c.cqr_source as source_code, s.sys_name as source_name')
            ->groupBy('c.cqr_source', 's.sys_name')
            ->orderBy('s.sys_name')
            ->get();

        return view('finance.payments.index', [
            'payments' => $payments,
            'search' => $search,
            'source' => $source,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => $stats,
            'sourceOptions' => $sourceOptions,
        ]);
    }

    public function createTicket(Request $request): RedirectResponse
    {
        if ($deny = $this->denyUnlessFinance()) {
            return $deny;
        }
        if (! extension_loaded('oci8')) {
            return redirect()->route('dashboard')
                ->with('error', 'Finance payments require direct Oracle access (OCI8 extension is not enabled on this server).');
        }
        if (! config('erp.enabled', true)) {
            return redirect()->route('dashboard')
                ->with('error', 'Finance payments require ERP/Oracle. Set ERP_ENABLED=true and enable OCI8 to use this module.');
        }

        $validated = $request->validate([
            'ref' => 'required|string|max:120',
            'source' => 'required|integer',
        ]);

        $record = $this->financeChequesBaseQuery()
            ->selectRaw('
                s.sys_name as sys_source,
                c.cqr_ref,
                c.cqr_ref_date,
                c.cqr_narrative,
                c.cqr_brh_code,
                c.cqr_fms_remarks,
                c.cqr_amount,
                c.cqr_payee
            ')
            ->where('c.cqr_ref', $validated['ref'])
            ->where('c.cqr_source', $validated['source'])
            ->first();

        if (!$record) {
            return redirect()->route('finance.payments.index')->with('error', 'Cheque reference not found in Finance source.');
        }

        $title = 'Finance cheque ' . trim((string) $record->cqr_ref) . ' - ' . trim((string) ($record->cqr_payee ?: 'Payee'));
        $clientName = trim((string) ($record->cqr_payee ?? ''));
        $contactId = $this->resolveContactIdFromPayee($clientName);
        $description = trim(
            "Finance source: " . ($record->sys_source ?? 'Unknown') . "\n" .
            "Cheque ref: " . ($record->cqr_ref ?? '') . "\n" .
            "Cheque date: " . (!empty($record->cqr_ref_date) ? date('Y-m-d', strtotime((string) $record->cqr_ref_date)) : 'N/A') . "\n" .
            "Payee: " . ($record->cqr_payee ?? 'N/A') . "\n" .
            "Amount: " . number_format((float) ($record->cqr_amount ?? 0), 2) . "\n" .
            "Branch code: " . ($record->cqr_brh_code ?? 'N/A') . "\n" .
            "Narrative: " . ($record->cqr_narrative ?? 'N/A') . "\n" .
            "FMS Remarks: " . ($record->cqr_fms_remarks ?? 'N/A')
        );

        return redirect()->route('tickets.create', [
            'title' => $title,
            'description' => $description,
            'client_name' => $clientName,
            'contact_id' => $contactId,
            'from' => 'finance',
        ])->with('success', $contactId
            ? 'Finance cheque loaded into ticket form with matched client from CQR_PAYEE.'
            : 'Finance cheque loaded into ticket form. CQR_PAYEE set as client name; select or create the client.');
    }

    private function financeChequesBaseQuery()
    {
        return DB::connection('erp')
            ->table('fms_cheques as c')
            ->join('tqc_branches as b', 'c.cqr_brh_code', '=', 'b.brn_code')
            ->join('TQ_CRM.TQC_SYSTEMS as s', 'c.cqr_source', '=', 's.sys_code')
            ->where('c.cqr_cst_status', 'AC')
            ->where('b.brn_reg_code', 28)
            ->whereRaw('EXTRACT(YEAR FROM c.cqr_ref_date) > 2025');
    }

    private function resolveContactIdFromPayee(string $payee): ?int
    {
        $payee = trim($payee);
        if ($payee === '') {
            return null;
        }

        $upper = mb_strtoupper($payee);
        $exactMatches = DB::connection('vtiger')
            ->table('vtiger_contactdetails as c')
            ->join('vtiger_crmentity as e', 'c.contactid', '=', 'e.crmid')
            ->where('e.deleted', 0)
            ->whereIn('e.setype', ['Contacts', 'Contact'])
            ->whereRaw("UPPER(TRIM(c.firstname || ' ' || c.lastname)) = ?", [$upper])
            ->pluck('c.contactid')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // Safety first: only auto-select when there is exactly one exact match.
        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }

        return null;
    }
}
