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

    protected function extractFirstValidEmail(?string $raw): ?string
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return null;
        }
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return $raw;
        }

        $parts = preg_split('/[;,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($parts as $part) {
            $candidate = trim((string) $part);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{
     *   ids: array<int, int>,
     *   warnings: array<int, string>,
     *   email_overrides: array<int, string>,
     *   email_recipients: array<int, array{email: string, first_name?: string, last_name?: string, name?: string}>
     * }
     */
    public function resolveContactIdsFromUpload(UploadedFile $file): array
    {
        $maxRows = max(100, (int) config('mass_broadcast.excel_max_rows', 5000));
        $warnings = [];
        $ids = [];
        $emailOverrides = [];
        $emailRecipients = [];
        $rowsToProcess = [];

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            return ['ids' => [], 'warnings' => ['Could not read uploaded file.'], 'email_overrides' => [], 'email_recipients' => []];
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            Log::warning('BroadcastRecipientImportService: load failed', ['error' => $e->getMessage()]);

            return ['ids' => [], 'warnings' => ['Could not open Excel/CSV: ' . $e->getMessage()], 'email_overrides' => [], 'email_recipients' => []];
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        if ($rows === [] || ! is_array($rows)) {
            return ['ids' => [], 'warnings' => ['The file appears empty.'], 'email_overrides' => [], 'email_recipients' => []];
        }

        $headerRow = array_shift($rows);
        if (! is_array($headerRow)) {
            return ['ids' => [], 'warnings' => ['Missing header row.'], 'email_overrides' => [], 'email_recipients' => []];
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
            } elseif (in_array($key, ['first name', 'firstname', 'first_name'], true)) {
                $headerMap['first_name'] = $colIdx;
            } elseif (in_array($key, ['last name', 'lastname', 'last_name', 'surname'], true)) {
                $headerMap['last_name'] = $colIdx;
            } elseif (in_array($key, ['name', 'full name', 'full_name'], true)) {
                $headerMap['name'] = $colIdx;
            }
        }

        if ($headerMap === []) {
            return ['ids' => [], 'warnings' => [
                'No recognised columns. Add a header row with one or more of: Contact ID, Email, Policy / Policy number, Mobile / Phone.',
            ], 'email_overrides' => [], 'email_recipients' => []];
        }

        $candidateContactIds = [];
        $rowNum = 1;
        foreach ($rows as $row) {
            $rowNum++;
            if (! is_array($row)) {
                continue;
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

            $contactIdRaw = isset($headerMap['contact_id'])
                ? trim((string) ($row[$headerMap['contact_id']] ?? ''))
                : '';
            $candidateContactId = (ctype_digit($contactIdRaw) && (int) $contactIdRaw > 0)
                ? (int) $contactIdRaw
                : null;
            if ($candidateContactId !== null) {
                $candidateContactIds[$candidateContactId] = $candidateContactId;
            }

            $rowsToProcess[] = [
                'rowNum' => $rowNum,
                'row' => $row,
                'candidateContactId' => $candidateContactId,
            ];
        }

        $existingContactIds = [];
        if ($candidateContactIds !== []) {
            $existingContactIds = $this->crm->getContactsByIds(array_values($candidateContactIds))
                ->pluck('contactid')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->flip()
                ->all();
        }

        foreach ($rowsToProcess as $entry) {
            if (count($ids) >= $maxRows) {
                $warnings[] = "Stopped after {$maxRows} resolved contacts (row {$entry['rowNum']}).";
                break;
            }

            $row = $entry['row'];
            $rowNum = (int) $entry['rowNum'];
            $optionalCell = function (string $key) use ($headerMap, $row): string {
                if (! isset($headerMap[$key])) {
                    return '';
                }
                return trim((string) ($row[$headerMap[$key]] ?? ''));
            };
            $resolved = null;
            $candidateContactId = $entry['candidateContactId'];
            if ($candidateContactId !== null) {
                if (isset($existingContactIds[$candidateContactId])) {
                    $resolved = $candidateContactId;
                } else {
                    $warnings[] = "Row {$rowNum}: Contact ID {$candidateContactId} was not found in CRM (tried other columns if present).";
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
                if (isset($headerMap['email'])) {
                    $rowEmail = $this->extractFirstValidEmail((string) ($row[$headerMap['email']] ?? ''));
                    if ($rowEmail !== null) {
                        // Uploaded value should help when CRM contact email is empty/stale.
                        $emailOverrides[$resolved] = $rowEmail;
                        $recipient = ['email' => $rowEmail];
                        $firstName = $optionalCell('first_name');
                        $lastName = $optionalCell('last_name');
                        $name = $optionalCell('name');
                        if ($firstName !== '') {
                            $recipient['first_name'] = $firstName;
                        }
                        if ($lastName !== '') {
                            $recipient['last_name'] = $lastName;
                        }
                        if ($name !== '') {
                            $recipient['name'] = $name;
                        }
                        $emailRecipients[$rowEmail] = $recipient;
                    }
                }
            } elseif (isset($headerMap['email'])) {
                // Allow "file only" email sends even when no CRM contact is matched.
                $rowEmail = $this->extractFirstValidEmail((string) ($row[$headerMap['email']] ?? ''));
                if ($rowEmail !== null) {
                    $recipient = ['email' => $rowEmail];
                    $firstName = $optionalCell('first_name');
                    $lastName = $optionalCell('last_name');
                    $name = $optionalCell('name');
                    if ($firstName !== '') {
                        $recipient['first_name'] = $firstName;
                    }
                    if ($lastName !== '') {
                        $recipient['last_name'] = $lastName;
                    }
                    if ($name !== '') {
                        $recipient['name'] = $name;
                    }
                    $emailRecipients[$rowEmail] = $recipient;
                }
            }
        }

        return [
            'ids' => array_values($ids),
            'warnings' => $warnings,
            'email_overrides' => $emailOverrides,
            'email_recipients' => array_values($emailRecipients),
        ];
    }
}
