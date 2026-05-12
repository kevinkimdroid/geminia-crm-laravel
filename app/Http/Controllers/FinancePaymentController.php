<?php

namespace App\Http\Controllers;

use App\Services\FinanceErpHttpClient;
use App\Services\UserDepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class FinancePaymentController extends Controller
{
    public function __construct(
        private FinanceErpHttpClient $financeErpHttp,
    ) {}

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
                ->with('error', 'You cannot open Finance links: your profile is not in the Finance department (and you are not an Administrator). Ask an admin to assign Finance access or add your user to the Finance department.');
        }

        return null;
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($deny = $this->denyUnlessFinance()) {
            return $deny;
        }

        $blockingError = $this->financeEnvironmentError();
        $erpError = null;

        $search = trim((string) $request->get('search', ''));
        $source = $request->filled('source') ? (int) $request->get('source') : null;
        $dateFrom = trim((string) $request->get('date_from', ''));
        $dateTo = trim((string) $request->get('date_to', ''));

        $emptyStats = [
            'total_count' => 0,
            'total_amount' => 0.0,
            'today_count' => 0,
            'distinct_payees' => 0,
        ];

        if ($blockingError !== null) {
            return view('finance.payments.index', [
                'payments' => $this->emptyFinancePaymentsPaginator($request),
                'search' => $search,
                'source' => $source,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'stats' => $emptyStats,
                'sourceOptions' => collect(),
                'blockingError' => $blockingError,
                'erpError' => null,
            ]);
        }

        if ($this->financeErpHttp->isConfigured()) {
            $bundle = $this->financeErpHttp->fetchPaymentsIndex($request);

            return view('finance.payments.index', [
                'payments' => $bundle['payments'],
                'search' => $search,
                'source' => $source,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'stats' => $bundle['stats'],
                'sourceOptions' => $bundle['sourceOptions'],
                'blockingError' => null,
                'erpError' => $bundle['erpError'],
            ]);
        }

        try {
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
        } catch (Throwable $e) {
            Log::warning('Finance cheques ERP query failed', ['error' => $e->getMessage()]);
            $erpError = $this->formatErpFailureMessage($e, 'Finance cheques');
            $payments = $this->emptyFinancePaymentsPaginator($request);
            $stats = $emptyStats;
            $sourceOptions = collect();
        }

        return view('finance.payments.index', [
            'payments' => $payments,
            'search' => $search,
            'source' => $source,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => $stats,
            'sourceOptions' => $sourceOptions,
            'blockingError' => null,
            'erpError' => $erpError ?? null,
        ]);
    }

    /**
     * AGNADV cheques missing bank branch (cqr_bbr_code), matched to LMS agency by payee — same scope as finance:notify-agency-advances.
     */
    public function agencyAdvances(Request $request): View|RedirectResponse
    {
        if ($deny = $this->denyUnlessFinance()) {
            return $deny;
        }

        $year = (int) $request->get('year', now()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $blockingError = $this->financeEnvironmentError();
        $rows = [];
        $loadError = null;

        if ($blockingError === null) {
            if ($this->financeErpHttp->isConfigured()) {
                $adv = $this->financeErpHttp->fetchAgencyAdvances($year);
                $rows = $adv['rows'];
                $loadError = $adv['loadError'];
            } else {
                try {
                    $raw = DB::connection('erp')
                        ->table('fms_cheques as c')
                        ->join('lms_agencies as a', 'a.agn_name', '=', 'c.cqr_payee')
                        ->where('c.cqr_pmt_type', 'AGNADV')
                        ->whereNull('c.cqr_bbr_code')
                        ->whereRaw("TO_CHAR(c.cqr_ref_date, 'RRRR') = ?", [(string) $year])
                        ->where('c.cqr_cst_status', 'AC')
                        ->select([
                            'c.cqr_no',
                            'c.cqr_payee',
                            'c.cqr_ref_date',
                            'c.cqr_bbr_code',
                            'c.cqr_cpy_acc_no',
                            'a.agn_bank_acc_no',
                            'a.agn_bbr_code',
                        ])
                        ->orderByDesc('c.cqr_ref_date')
                        ->get();

                    foreach ($raw as $row) {
                        $a = array_change_key_case((array) $row, CASE_LOWER);
                        $rows[] = [
                            'cqr_no' => isset($a['cqr_no']) ? trim((string) $a['cqr_no']) : '',
                            'cqr_payee' => isset($a['cqr_payee']) ? trim((string) $a['cqr_payee']) : '',
                            'cqr_ref_date' => $a['cqr_ref_date'] ?? null,
                            'cqr_bbr_code' => isset($a['cqr_bbr_code']) ? trim((string) $a['cqr_bbr_code']) : '',
                            'cqr_cpy_acc_no' => isset($a['cqr_cpy_acc_no']) ? trim((string) $a['cqr_cpy_acc_no']) : '',
                            'agn_bank_acc_no' => isset($a['agn_bank_acc_no']) ? trim((string) $a['agn_bank_acc_no']) : '',
                            'agn_bbr_code' => isset($a['agn_bbr_code']) ? trim((string) $a['agn_bbr_code']) : '',
                        ];
                    }
                } catch (Throwable $e) {
                    Log::warning('Finance agency advances ERP query failed', ['error' => $e->getMessage()]);
                    $loadError = $this->formatErpFailureMessage($e, 'Agency advances');
                }
            }
        }

        return view('finance.agency-advances.index', [
            'rows' => $rows,
            'year' => $year,
            'loadError' => $loadError,
            'blockingError' => $blockingError,
        ]);
    }

    public function createTicket(Request $request): RedirectResponse
    {
        if ($deny = $this->denyUnlessFinance()) {
            return $deny;
        }

        $envErr = $this->financeEnvironmentError();
        if ($envErr !== null) {
            return redirect()->route('finance.payments.index')->with('error', $envErr);
        }

        $validated = $request->validate([
            'ref' => 'required|string|max:120',
            'source' => 'required|integer',
        ]);

        if ($this->financeErpHttp->isConfigured()) {
            $got = $this->financeErpHttp->fetchChequeForTicket($validated['ref'], (int) $validated['source']);
            if ($got['error'] !== null) {
                return redirect()->route('finance.payments.index')->with('error', $got['error']);
            }
            $record = $got['record'];
        } else {
            try {
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
            } catch (Throwable $e) {
                Log::warning('Finance create-ticket ERP lookup failed', ['error' => $e->getMessage()]);

                return redirect()->route('finance.payments.index')
                    ->with('error', $this->formatErpFailureMessage($e, 'Could not load that cheque from ERP'));
            }
        }

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

    /**
     * Human-readable reason Finance cannot talk to Oracle, or null if prerequisites look OK.
     */
    private function financeEnvironmentError(): ?string
    {
        if ($this->financeErpHttp->isConfigured()) {
            return null;
        }
        if (! extension_loaded('oci8')) {
            return 'Finance cannot load: the PHP OCI8 extension is not enabled on this server, and no Finance HTTP API is configured. '
                . 'Either install Oracle Instant Client, enable the oci8 extension in php.ini, and restart Apache/PHP-FPM, '
                . 'or set FINANCE_ERP_HTTP_BASE (or use ERP_CLIENTS_HTTP_URL with the same host as erp-clients-api) so Finance can load cheques over HTTP.';
        }
        if (! config('erp.enabled', true)) {
            return 'Finance cannot load: ERP is disabled (ERP_ENABLED=false in .env). '
                . 'Set ERP_ENABLED=true and configure Oracle (ERP_HOST, ERP_SERVICE_NAME or ERP_TNS, ERP_USERNAME, ERP_PASSWORD).';
        }

        return null;
    }

    private function formatErpFailureMessage(Throwable $e, string $context): string
    {
        $msg = $context . ' — the Oracle (ERP) query failed. Typical causes: network/firewall to the DB, wrong credentials, or a missing table/column. ';
        if (config('app.debug')) {
            return $msg . 'Technical detail: ' . $e->getMessage();
        }

        return $msg . 'Ask your administrator to check storage/logs/laravel.log.';
    }

    private function emptyFinancePaymentsPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            20,
            max(1, (int) $request->input('page', 1)),
            ['path' => $request->url(), 'query' => $request->query()]
        );
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
