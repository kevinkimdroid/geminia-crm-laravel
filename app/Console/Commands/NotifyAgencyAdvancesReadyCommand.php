<?php

namespace App\Console\Commands;

use App\Services\PlainTextMailSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Agency advances (AGNADV) missing bank branch on cheque, matched to LMS agency by payee name.
 *
 * Equivalent SQL:
 *
 * SELECT c.cqr_no, c.cqr_bbr_code, c.cqr_cpy_acc_no, a.agn_bank_acc_no, a.agn_bbr_code
 *   FROM fms_cheques c, lms_agencies a
 *  WHERE c.cqr_pmt_type = 'AGNADV'
 *    AND c.cqr_bbr_code IS NULL
 *    AND TO_CHAR(c.cqr_ref_date, 'RRRR') = :year
 *    AND c.cqr_cst_status = 'AC'
 *    AND a.agn_name = c.cqr_payee
 */
class NotifyAgencyAdvancesReadyCommand extends Command
{
    protected $signature = 'finance:notify-agency-advances
                            {--email= : Recipient (defaults to config erp.agency_advances_notify_recipient)}
                            {--year= : Reference year (RRRR), default current year}
                            {--dry-run : List rows and skip sending email}
                            {--force : Send email even when there are no rows (testing)}';

    protected $description = 'Query ERP for agency advances ready for bank details; email recipient when rows exist';

    public function handle(PlainTextMailSender $mail): int
    {
        if (! extension_loaded('oci8')) {
            $this->error('OCI8 is not loaded; cannot query ERP.');

            return self::FAILURE;
        }
        if (! config('erp.enabled', true)) {
            $this->error('ERP is disabled (ERP_ENABLED=false).');

            return self::FAILURE;
        }

        $year = (string) ($this->option('year') ?: now()->year);
        $recipient = trim((string) ($this->option('email') ?: config('erp.agency_advances_notify_recipient', 'kelvin.kimutai@geminialife.co.ke')));
        if ($recipient === '') {
            $this->error('No recipient email. Set --email or FINANCE_AGENCY_ADVANCES_NOTIFY_RECIPIENT.');

            return self::FAILURE;
        }

        $rows = DB::connection('erp')
            ->table('fms_cheques as c')
            ->join('lms_agencies as a', 'a.agn_name', '=', 'c.cqr_payee')
            ->where('c.cqr_pmt_type', 'AGNADV')
            ->whereNull('c.cqr_bbr_code')
            ->whereRaw("TO_CHAR(c.cqr_ref_date, 'RRRR') = ?", [$year])
            ->where('c.cqr_cst_status', 'AC')
            ->select([
                'c.cqr_no',
                'c.cqr_bbr_code',
                'c.cqr_cpy_acc_no',
                'a.agn_bank_acc_no',
                'a.agn_bbr_code',
            ])
            ->get();

        $count = $rows->count();
        $this->info("Rows for year {$year}: {$count}");

        if ($count === 0) {
            if ($this->option('dry-run')) {
                $this->comment('Dry run: no data to display.');

                return self::SUCCESS;
            }
            if (! $this->option('force')) {
                $this->comment('No email sent (no matching records). Use --force to send an empty notice.');

                return self::SUCCESS;
            }
        }

        $lines = [];
        $csvLines = ['cqr_no,cqr_bbr_code,cqr_cpy_acc_no,agn_bank_acc_no,agn_bbr_code'];
        foreach ($rows as $row) {
            $flat = $this->normalizeRow($row);
            $lines[] = sprintf(
                '  %-20s  bbr:%s  cpy_acc:%s  agn_bank:%s  agn_bbr:%s',
                $flat['cqr_no'],
                $flat['cqr_bbr_code'] !== '' ? $flat['cqr_bbr_code'] : 'NULL',
                $flat['cqr_cpy_acc_no'],
                $flat['agn_bank_acc_no'],
                $flat['agn_bbr_code']
            );
            $csvLines[] = implode(',', array_map([$this, 'csvEscape'], [
                $flat['cqr_no'],
                $flat['cqr_bbr_code'],
                $flat['cqr_cpy_acc_no'],
                $flat['agn_bank_acc_no'],
                $flat['agn_bbr_code'],
            ]));
        }

        if ($this->option('dry-run')) {
            foreach ($lines as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        }

        $body = "Agency advances (AGNADV) with cqr_bbr_code NULL — reference year {$year}.\n"
            . "Record count: {$count}\n\n";
        if ($count > 0) {
            $body .= implode("\n", $lines) . "\n";
        } else {
            $body .= "(No rows; this message was sent because --force was used.)\n";
        }

        $subject = "[Finance] Agency advances ready ({$count} record" . ($count === 1 ? '' : 's') . ", year {$year})";
        $attachments = [];
        if ($count > 0) {
            $attachments[] = [
                'name' => 'agency_advances_' . $year . '_' . now()->format('Ymd_His') . '.csv',
                'contentType' => 'text/csv',
                'content' => implode("\n", $csvLines) . "\n",
            ];
        }

        $ok = $mail->send($recipient, null, $subject, $body, $attachments);
        if (! $ok) {
            $this->error('Mail send failed: ' . ($mail->getLastError() ?? 'unknown'));

            return self::FAILURE;
        }

        $this->info("Email sent to {$recipient}.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeRow(object $row): array
    {
        $a = array_change_key_case((array) $row, CASE_LOWER);

        return [
            'cqr_no' => trim((string) ($a['cqr_no'] ?? '')),
            'cqr_bbr_code' => trim((string) ($a['cqr_bbr_code'] ?? '')),
            'cqr_cpy_acc_no' => trim((string) ($a['cqr_cpy_acc_no'] ?? '')),
            'agn_bank_acc_no' => trim((string) ($a['agn_bank_acc_no'] ?? '')),
            'agn_bbr_code' => trim((string) ($a['agn_bbr_code'] ?? '')),
        ];
    }

    private function csvEscape(string $v): string
    {
        if (strpbrk($v, ",\"\n\r") !== false) {
            return '"' . str_replace('"', '""', $v) . '"';
        }

        return $v;
    }
}
