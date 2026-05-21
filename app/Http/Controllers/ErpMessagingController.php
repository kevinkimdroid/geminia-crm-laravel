<?php

namespace App\Http\Controllers;

use App\Jobs\SendErpSmsMessagesJob;
use App\Services\ErpSmsMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ErpMessagingController extends Controller
{
    public function index(ErpSmsMessageService $messages): View
    {
        $pending = collect();
        $previewCount = null;
        $totalPending = null;
        $loadError = null;
        $canLoad = $messages->canLoadPendingMessages();
        $previewLimit = 50;

        try {
            if ($canLoad) {
                $pending = $messages->pendingMessages($previewLimit);
                $previewCount = $pending->count();
                $totalPending = $messages->pendingCount();
            } else {
                $loadError = 'ERP SMS cannot be loaded. Set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE (erp-clients-api with /messages/sms routes), or enable OCI8 and ERP Oracle on this server.';
            }
        } catch (\Throwable $e) {
            $loadError = $e->getMessage();
        }

        return view('tools.erp-messaging', [
            'pending' => $pending,
            'previewCount' => $previewCount,
            'totalPending' => $totalPending,
            'previewLimit' => $previewLimit,
            'loadError' => $loadError,
            'canLoad' => $canLoad,
            'canSendLive' => $messages->isReady(),
            'autoSendEnabled' => (bool) config('erp.messages_auto_send_enabled', false),
            'erpSmsTransport' => $messages->activeTransport(),
        ]);
    }

    public function sent(Request $request, ErpSmsMessageService $messages): View
    {
        $filter = (string) $request->query('filter', 'all');
        $rows = collect();
        $counts = [
            'total' => 0,
            'sent' => 0,
            'delivered' => 0,
            'read' => 0,
            'not_read' => 0,
            'pending_delivery' => 0,
        ];
        $loadError = null;

        try {
            if ($messages->canLoadPendingMessages()) {
                $result = $messages->sentMessagesWithTracking(200, $filter);
                $rows = $result['rows'];
                $counts = $result['counts'];
            } else {
                $loadError = 'ERP SMS cannot be loaded. Set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE (erp-clients-api with /messages/sms routes), or enable OCI8 and ERP Oracle on this server.';
            }
        } catch (\Throwable $e) {
            $loadError = $e->getMessage();
        }

        return view('tools.erp-messaging-sent', [
            'rows' => $rows,
            'counts' => $counts,
            'filter' => $filter,
            'loadError' => $loadError,
            'erpSmsTransport' => $messages->activeTransport(),
        ]);
    }

    public function send(Request $request, ErpSmsMessageService $messages): RedirectResponse
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:500',
            'dry_run' => 'nullable|boolean',
        ]);

        $limit = max(1, min(500, (int) ($validated['limit'] ?? 50)));
        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $userId = Auth::guard('vtiger')->id();
        $useQueue = ! $dryRun
            && config('erp.messages_send_via_queue', true)
            && config('queue.default') !== 'sync';

        if ($useQueue) {
            SendErpSmsMessagesJob::dispatch($limit, false, $userId);

            return redirect()
                ->route('tools.erp-messaging')
                ->with(
                    'success',
                    "Sending up to {$limit} message(s) in the background. Refresh in a minute to see the queue shrink."
                );
        }

        if (config('queue.default') === 'sync') {
            $this->warnSyncQueueOnce();
        }

        try {
            $summary = $messages->sendPending($limit, $dryRun, $userId);
        } catch (\Throwable $e) {
            return redirect()
                ->route('tools.erp-messaging')
                ->with('error', 'ERP messaging failed: ' . $e->getMessage());
        }

        $message = sprintf(
            'Draft SMS: processed %d, sent %d, failed %d, skipped %d.',
            $summary['processed'],
            $summary['sent'],
            $summary['failed'],
            $summary['skipped']
        );

        return redirect()
            ->route('tools.erp-messaging')
            ->with($summary['failed'] > 0 ? 'warning' : 'success', $message)
            ->with('erp_sms_summary', $summary);
    }

    private function warnSyncQueueOnce(): void
    {
        if (session()->has('erp_sms_sync_queue_warned')) {
            return;
        }
        session()->flash(
            'warning',
            'SMS sending is running in this browser request (QUEUE_CONNECTION=sync). Large batches may time out — set QUEUE_CONNECTION=database and run php artisan queue:work.'
        );
        session()->put('erp_sms_sync_queue_warned', true);
    }
}
