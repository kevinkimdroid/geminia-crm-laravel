<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reassign pension email tickets to the escalation owner after SLA (TAT) is breached.
 */
class PensionTicketSlaEscalationService
{
    public function __construct(
        protected PensionAutoTicketFromEmailService $pensionTickets,
        protected TicketSlaService $sla,
        protected TicketNotificationService $notifier
    ) {}

    /**
     * @return array{escalated: int, skipped: int, errors: list<string>}
     */
    public function escalateBreachedTickets(): array
    {
        $config = config('pension.auto_ticket', []);
        if (empty($config['enabled'])) {
            return ['escalated' => 0, 'skipped' => 0, 'errors' => []];
        }

        $category = trim((string) ($config['category'] ?? 'Pension Administration'));
        $source = trim((string) ($config['source'] ?? 'Pension Email'));
        $tatHours = max(1, (int) ($config['tat_hours'] ?? 24));
        $assignEmail = (string) ($config['assign_to_email'] ?? '');
        $escalateEmail = (string) ($config['escalate_to_email'] ?? '');

        $assignId = $this->pensionTickets->resolveUserIdByEmail($assignEmail);
        $escalateId = $this->pensionTickets->resolveUserIdByEmail($escalateEmail);

        if (! $assignId || ! $escalateId) {
            return [
                'escalated' => 0,
                'skipped' => 0,
                'errors' => [trim(
                    (! $assignId ? "Assignee not found: {$assignEmail}. " : '')
                    . (! $escalateId ? "Escalation user not found: {$escalateEmail}." : '')
                )],
            ];
        }

        $this->sla->setDepartmentTat($category, $tatHours);

        $cutoff = now()->subHours($tatHours)->format('Y-m-d H:i:s');
        $inactive = strtolower((string) config('tickets.inactive_status', 'Inactive'));

        $tickets = DB::connection('vtiger')
            ->table('vtiger_troubletickets as t')
            ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
            ->where('e.deleted', 0)
            ->where('e.smownerid', $assignId)
            ->where('t.category', $category)
            ->where('e.source', $source)
            ->where('e.createdtime', '<=', $cutoff)
            ->whereRaw("LOWER(TRIM(COALESCE(t.status, ''))) NOT IN ('closed', 'resolved', ?)", [$inactive])
            ->select('t.ticketid', 't.ticket_no', 't.title', 't.status', 'e.createdtime')
            ->orderBy('e.createdtime')
            ->limit(100)
            ->get();

        $escalated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($tickets as $ticket) {
            $cacheKey = 'pension_ticket_escalated_' . $ticket->ticketid;
            if (Cache::get($cacheKey)) {
                $skipped++;
                continue;
            }

            try {
                $now = now()->format('Y-m-d H:i:s');
                DB::connection('vtiger')->transaction(function () use ($ticket, $escalateId, $now) {
                    DB::connection('vtiger')->table('vtiger_crmentity')
                        ->where('crmid', $ticket->ticketid)
                        ->update([
                            'smownerid' => $escalateId,
                            'modifiedtime' => $now,
                        ]);
                });

                $this->notifier->sendPensionSlaEscalationNotification(
                    (int) $ticket->ticketid,
                    (string) $ticket->ticket_no,
                    (string) ($ticket->title ?? 'Pension ticket'),
                    $escalateId,
                    $tatHours,
                    $assignEmail
                );

                Cache::forever($cacheKey, true);
                $escalated++;
            } catch (\Throwable $e) {
                $errors[] = "Ticket {$ticket->ticket_no}: {$e->getMessage()}";
                Log::error('PensionTicketSlaEscalationService', ['ticket_id' => $ticket->ticketid, 'error' => $e->getMessage()]);
            }
        }

        return ['escalated' => $escalated, 'skipped' => $skipped, 'errors' => $errors];
    }
}
