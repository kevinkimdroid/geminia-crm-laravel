<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Resolve CRM contact IDs from an uploaded spreadsheet (Excel or CSV).
 * Expected: header row with one of Contact ID / contactid, Email, Policy / policy number, Mobile, Phone.
 */
class BroadcastRecipientImportService
{
    public function __construct(
        protected CrmService $crm
    ) {}

    /**
     * @return array{ids: array<int, int>, warnings: array<int, string>}
     */
    public function resolveContactIdsFromUpload(UploadedFile $file): array
    {
        $maxRows = max(100, (int) config('mass_broadcast.excel_max_rows', 5000));
        $warnings = [];
        $ids = [];

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return ['ids' => [], 'warnings' => ['Could not read uploaded file.']];
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            Log::warning('BroadcastRecipientImportService: load failed', ['error' => $e->getMessage()]);

            return ['ids' => [], 'warnings' => ['Could not open Excel/CSV: ' . $e->getMessage()]];
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        if ($rows === [] || ! is_array($rows)) {
            return ['ids' => [], 'warnings' => ['The file appears empty.']];
        }

        $headerRow = array_shift($rows);
        if (! is_array($headerRow)) {
            return ['ids' => [], 'warnings' => ['Missing header row.']];
        }

        $norm = function ($v): string {
            return strtolower(preg_replace('/\s+/', ' ', trim((string) $v)));
        };

        $headerMap = [];
        foreach ($headerRow as $colIdx => $label) {
            $key = $norm($label);
            if ($key === '') {
                continue;
            }
            if (in_array($key, ['contact id', 'contactid', 'contact_id', 'id', 'crmid'], true)) {
                $headerMap['contact_id'] = $colIdx;
            } elseif (in_array($key, ['email', 'e-mail', 'email address'], true)) {
                $headerMap['email'] = $colIdx;
            } elseif (str_contains($key, 'policy') || $key === 'policy no' || $key === 'policy number' || $key === 'policy_no') {
                $headerMap['policy'] = $colIdx;
            } elseif (in_array($key, ['mobile', 'cell', 'phone', 'telephone', 'msisdn'], true)) {
                $headerMap['phone'] = $colIdx;
            }
        }

        if ($headerMap === []) {
            return ['ids' => [], 'warnings' => [
                'No recognised columns. Add a header row with one or more of: Contact ID, Email, Policy / Policy number, Mobile / Phone.',
            ]];
        }

        $rowNum = 1;
        foreach ($rows as $row) {
            $rowNum++;
            if (! is_array($row)) {
                continue;
            }
            if (count($ids) >= $maxRows) {
                $warnings[] = "Stopped after {$maxRows} resolved contacts (row {$rowNum}).";

                break;
            }

            $allEmpty = true;
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    $allEmpty = false;

                    break;
                }
            }
            if ($allEmpty) {
                continue;
            }

            $resolved = null;
            if (isset($headerMap['contact_id'])) {
                $raw = trim((string) ($row[$headerMap['contact_id']] ?? ''));
                if ($raw !== '' && ctype_digit($raw)) {
                    $resolved = (int) $raw;
                }
            }
            if ($resolved === null && isset($headerMap['policy'])) {
                $pol = trim((string) ($row[$headerMap['policy']] ?? ''));
                if ($pol !== '') {
                    $c = $this->crm->findContactByPolicyNumber($pol);
                    $resolved = $c ? (int) $c->contactid : null;
                    if ($resolved === null) {
                        $warnings[] = "Row {$rowNum}: no contact for policy {$pol}";
                    }
                }
            }
            if ($resolved === null && isset($headerMap['email'])) {
                $em = trim((string) ($row[$headerMap['email']] ?? ''));
                if ($em !== '') {
                    $c = $this->crm->findContactByPhoneOrEmail(null, $em);
                    $resolved = $c ? (int) $c->contactid : null;
                    if ($resolved === null) {
                        $warnings[] = "Row {$rowNum}: no contact for email {$em}";
                    }
                }
            }
            if ($resolved === null && isset($headerMap['phone'])) {
                $ph = trim((string) ($row[$headerMap['phone']] ?? ''));
                if ($ph !== '') {
                    $digits = preg_replace('/\D/', '', $ph);
                    $c = $this->crm->findContactByPhoneOrEmail($digits !== '' ? $digits : $ph, null);
                    $resolved = $c ? (int) $c->contactid : null;
                    if ($resolved === null) {
                        $warnings[] = "Row {$rowNum}: no contact for phone {$ph}";
                    }
                }
            }

            if ($resolved !== null && $resolved > 0) {
                $ids[$resolved] = $resolved;
            }
        }

        return ['ids' => array_values($ids), 'warnings' => $warnings];
    }
}
