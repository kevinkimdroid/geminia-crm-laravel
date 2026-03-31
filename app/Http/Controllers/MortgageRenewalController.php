<?php

namespace App\Http\Controllers;

use App\Exports\MortgageRenewalsExport;
use App\Services\ErpClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MortgageRenewalController extends Controller
{
    /** Allowed “due within” periods (days). Matches API mendr_window_days cap (120). */
    public const RENEWAL_WINDOWS = [7, 14, 30, 90, 120];

    public function __construct(protected ErpClientService $erp) {}

    protected function normalizeWindow(Request $request): int
    {
        $w = (int) $request->get('window', 30);
        if (in_array($w, self::RENEWAL_WINDOWS, true)) {
            return $w;
        }

        return 30;
    }

    /**
     * Only mortgages with a renewal date in the next N calendar days (not the full mortgage register).
     */
    public function index(Request $request): View
    {
        $mortgageConfigured = trim((string) config('erp.clients_mortgage_view')) !== '';
        // This page talks to erp-clients-api only; do not require CLIENTS_VIEW_SOURCE=erp_http if the URL is set.
        $useHttp = ! empty(config('erp.clients_http_url'));

        $window = $this->normalizeWindow($request);
        $renewalDateStart = now()->startOfDay();
        $renewalDateEnd = now()->startOfDay()->addDays($window);
        $fromStr = $renewalDateStart->format('Y-m-d');
        $toStr = $renewalDateEnd->format('Y-m-d');

        $page = max(1, (int) $request->get('page', 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $error = null;
        $rows = collect();
        $total = 0;
        if (! $mortgageConfigured) {
            $error = 'Mortgage renewals are not configured yet. Ask an administrator to set the mortgage view in the system configuration.';
        } elseif (! $useHttp) {
            $error = 'Mortgage renewals need a live connection to the policy system. Ask an administrator to enable ERP HTTP client access.';
        } else {
            // Send mendr_window_days (Oracle SYSDATE) and mendr_renewal_from/to so older APIs still apply the date range.
            $countRes = $this->erp->getClientsFromHttpApi(1, 0, null, 25, true, 'mortgage', null, $fromStr, $toStr, true, $window);
            $error = $countRes['error'] ?? null;
            $total = (int) ($countRes['total'] ?? 0);

            $dataRes = $this->erp->getClientsFromHttpApi($perPage, $offset, null, 45, false, 'mortgage', null, $fromStr, $toStr, true, $window);
            if (! $error && ! empty($dataRes['error'])) {
                $error = $dataRes['error'];
            }
            $rows = $dataRes['data'] instanceof Collection ? $dataRes['data'] : collect($dataRes['data'] ?? []);
        }

        $paginator = new LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('support.mortgage-renewals', [
            'customers' => $paginator,
            'renewalDateStart' => $renewalDateStart,
            'renewalDateEnd' => $renewalDateEnd,
            'window' => $window,
            'pageError' => $error,
            'mortgageConfigured' => $mortgageConfigured,
            'useHttp' => $useHttp,
        ]);
    }

    /**
     * Export all mortgages due for renewal in the selected window (same filter as the list, not paginated).
     */
    public function export(Request $request): RedirectResponse|BinaryFileResponse
    {
        $mortgageConfigured = trim((string) config('erp.clients_mortgage_view')) !== '';
        $useHttp = ! empty(config('erp.clients_http_url'));
        $window = $this->normalizeWindow($request);
        $renewalDateStart = now()->startOfDay();
        $renewalDateEnd = now()->startOfDay()->addDays($window);
        $fromStr = $renewalDateStart->format('Y-m-d');
        $toStr = $renewalDateEnd->format('Y-m-d');

        if (! $mortgageConfigured) {
            return redirect()
                ->route('support.mortgage-renewals', ['window' => $window])
                ->with('error', 'Mortgage renewals are not configured.');
        }
        if (! $useHttp) {
            return redirect()
                ->route('support.mortgage-renewals', ['window' => $window])
                ->with('error', 'ERP HTTP URL is not configured.');
        }

        $countRes = $this->erp->getClientsFromHttpApi(1, 0, null, 60, true, 'mortgage', null, $fromStr, $toStr, true, $window);
        if (! empty($countRes['error'])) {
            return redirect()
                ->route('support.mortgage-renewals', ['window' => $window])
                ->with('error', $countRes['error']);
        }

        $total = (int) ($countRes['total'] ?? 0);
        $all = collect();
        $pageSize = 100;
        $offset = 0;
        $guard = 0;
        while ($offset < $total && $guard < 200) {
            $guard++;
            $dataRes = $this->erp->getClientsFromHttpApi($pageSize, $offset, null, 120, false, 'mortgage', null, $fromStr, $toStr, true, $window);
            if (! empty($dataRes['error'])) {
                return redirect()
                    ->route('support.mortgage-renewals', ['window' => $window])
                    ->with('error', $dataRes['error']);
            }
            $chunk = $dataRes['data'] instanceof Collection ? $dataRes['data'] : collect($dataRes['data'] ?? []);
            if ($chunk->isEmpty()) {
                break;
            }
            $all = $all->merge($chunk);
            $offset += $pageSize;
        }

        $filename = 'mortgage-renewals-'.$window.'d-'.now()->format('Y-m-d-His');

        return Excel::download(new MortgageRenewalsExport($all), $filename.'.xlsx');
    }
}
