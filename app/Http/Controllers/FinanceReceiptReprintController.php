<?php

namespace App\Http\Controllers;

use App\Services\Receipts\ReceiptDataSource;
use App\Services\Receipts\ReceiptXmlBuilder;
use App\Services\Receipts\RtfReceiptRenderer;
use App\Services\UserDepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Finance > Receipt reprint. Searches FMS receipts (via the shared ERP Oracle
 * connection) and reprints the official premium receipt as PDF/RTF, or exposes
 * the underlying XML data document. Restricted to Finance department users and
 * Administrators (same gate as the rest of the Finance section).
 */
class FinanceReceiptReprintController extends Controller
{
    public function __construct(
        private ReceiptDataSource $receipts,
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

    public function index(): View|RedirectResponse
    {
        if ($deny = $this->denyUnlessFinance()) {
            return $deny;
        }

        return view('finance.receipts.search', [
            'query' => '',
            'type' => 'receipt',
            'results' => null,
            'error' => null,
        ]);
    }

    public function search(Request $request): View|RedirectResponse
    {
        if ($deny = $this->denyUnlessFinance()) {
            return $deny;
        }

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:receipt,policy,client'],
        ]);

        $results = null;
        $error = null;

        try {
            $results = $this->receipts->search($validated['query'], $validated['type']);
        } catch (Throwable $e) {
            Log::error('Receipt search failed', ['exception' => $e]);
            $error = $this->friendlyDataSourceError($e);
        }

        return view('finance.receipts.search', [
            'query' => $validated['query'],
            'type' => $validated['type'],
            'results' => $results,
            'error' => $error,
        ]);
    }

    public function preview(string $receiptNo, Request $request): View|RedirectResponse
    {
        if ($deny = $this->denyUnlessFinance()) {
            return $deny;
        }

        $branch = $request->query('branch');

        try {
            $receipt = $this->receipts->find($receiptNo, $branch);
        } catch (Throwable $e) {
            Log::error('Receipt lookup failed', ['receipt' => $receiptNo, 'branch' => $branch, 'exception' => $e]);

            return view('finance.receipts.search', [
                'query' => $receiptNo,
                'type' => 'receipt',
                'results' => null,
                'error' => $this->friendlyDataSourceError($e),
            ]);
        }

        abort_if($receipt === null, 404, "Receipt {$receiptNo} was not found.");

        return view('finance.receipts.preview', [
            'header' => $receipt['header'],
            'lines' => $receipt['lines'],
        ]);
    }

    public function reprint(string $receiptNo, Request $request, RtfReceiptRenderer $renderer): BinaryFileResponse|RedirectResponse
    {
        if ($deny = $this->denyUnlessFinance()) {
            return $deny;
        }

        $receipt = $this->receipts->find($receiptNo, $request->query('branch'));

        abort_if($receipt === null, 404, "Receipt {$receiptNo} was not found.");

        $output = $renderer->render($receipt);

        return response()
            ->download($output['path'], $output['filename'], [
                'Content-Type' => $output['mime'],
            ])
            ->deleteFileAfterSend(false);
    }

    public function xml(string $receiptNo, Request $request, ReceiptXmlBuilder $builder): \Illuminate\Http\Response|RedirectResponse
    {
        if ($deny = $this->denyUnlessFinance()) {
            return $deny;
        }

        $receipt = $this->receipts->find($receiptNo, $request->query('branch'));

        abort_if($receipt === null, 404, "Receipt {$receiptNo} was not found.");

        $xml = $builder->build($receipt);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="receipt_'.$receiptNo.'.xml"',
        ]);
    }

    /**
     * Turn a low-level Oracle/connection exception into a short, safe,
     * staff-friendly message (full detail is written to the log).
     */
    protected function friendlyDataSourceError(Throwable $e): string
    {
        $raw = $e->getMessage();

        if (str_contains($raw, 'ORA-00942')) {
            return 'The receipt data views are not available on the database yet. '
                .'Please ask the DBA to create the configured views and grant SELECT access.';
        }

        $connectionDropped = str_contains($raw, 'ORA-03113')
            || str_contains($raw, 'ORA-03114')
            || str_contains($raw, 'ORA-12')          // listener / TNS / connect errors
            || str_contains($raw, 'ORA-01017')        // invalid credentials
            || stripos($raw, 'Lost connection') !== false
            || stripos($raw, 'no reconnector') !== false;

        if ($connectionDropped) {
            return 'Could not read from the receipt database. The connection was refused or dropped '
                .'(this often means a database/network firewall is blocking access from this server, '
                .'or the credentials/TNS details are incorrect). Please contact the DBA / infrastructure team.';
        }

        return 'The receipt database is currently unavailable. Please try again later or contact support.';
    }
}
