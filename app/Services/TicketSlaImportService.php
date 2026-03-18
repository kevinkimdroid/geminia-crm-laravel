<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Imports department TAT (Turnaround Time) from Excel.
 * Expects structure: sheets per department, with "Defined Time frame" column (e.g. "1 day", "5 days", "24 Hours").
 */
class TicketSlaImportService
{
    /** Sheet names to skip (e.g. Summary has no TAT definitions) */
    protected array $skipSheets = ['Summary'];

    /** Map ambiguous department names to canonical form */
    protected array $departmentAliases = [
        'risk & comp.' => 'Risk & Compliance',
        'risk & compliance' => 'Risk & Compliance',
        'customer service' => 'Operations',
        'human resource' => 'HR',
        'bd retail' => 'BD Retail',
    ];

    /**
     * Parse a time frame string to hours.
     * Examples: "1 day"→24, "2 days"→48, "5 working days"→120, "24 Hours"→24, "4 Hours"→4.
     */
    public function parseTimeFrameToHours(?string $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $s = strtolower(trim((string) $value));

        // Skip non-duration values
        if (preg_match('/^(n\/a|na|as needed|annually|quarterly|quartely|bi-annual|monthly|≥|100%|80%|by \d|every|schedule|=)/', $s)) {
            return null;
        }
        if ($s === 'immediately') {
            return 4;
        }
        if (str_contains($s, 'quarter') || str_contains($s, 'annual') || str_contains($s, 'month') && !preg_match('/\d+\s*month/', $s)) {
            return null;
        }

        // "X Hours" or "X hours"
        if (preg_match('/^(\d+)\s*hours?$/i', $s, $m)) {
            return (int) $m[1];
        }

        // "X day" or "X days" or "X working days"
        if (preg_match('/^(\d+)\s*(working\s+)?days?$/i', $s, $m)) {
            return (int) $m[1] * 24;
        }

        // "X day" at end of longer string e.g. "within 1 day"
        if (preg_match('/(\d+)\s+day\b/i', $s, $m)) {
            return (int) $m[1] * 24;
        }

        // "X-Y days" or "3-5 days" – use the lower bound (stricter)
        if (preg_match('/^(\d+)\s*-\s*(\d+)\s*days?$/i', $s, $m)) {
            return (int) min($m[1], $m[2]) * 24;
        }

        // "3-5 days" without "days" at end
        if (preg_match('/^(\d+)\s*-\s*(\d+)\s*$/i', $s, $m)) {
            return (int) min($m[1], $m[2]) * 24;
        }

        // "X days" in the middle of text e.g. "30 days after Quarter end"
        if (preg_match('/(\d+)\s*days?/i', $s, $m)) {
            return (int) $m[1] * 24;
        }

        // "1 Month" or "1 month"
        if (preg_match('/^(\d+)\s*month[s]?$/i', $s, $m)) {
            return (int) $m[1] * 30 * 24;
        }

        return null;
    }

    /**
     * Import from Excel file. Returns [ 'imported' => [...], 'skipped' => [...], 'errors' => [...] ].
     */
    public function importFromFile(string $path): array
    {
        $imported = [];
        $skipped = [];
        $errors = [];

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return ['imported' => [], 'skipped' => [], 'errors' => ['Could not read file: ' . $e->getMessage()]];
        }

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetName = $sheet->getTitle();
            if (in_array($sheetName, $this->skipSheets, true)) {
                continue;
            }

            $highestRow = $sheet->getHighestRow();
            $highestCol = $sheet->getHighestColumn();
            if ($highestRow < 3) {
                continue;
            }

            // Find column indices from header row (usually row 2)
            $deptCol = null;
            $timeFrameCol = null;
            for ($c = 'A'; $c <= $highestCol; $c++) {
                $val = (string) $sheet->getCell($c . '2')->getValue();
                $valLower = strtolower($val);
                if (str_contains($valLower, 'defined') && str_contains($valLower, 'time')) {
                    $timeFrameCol = $c;
                }
                if (str_contains($valLower, 'department') || str_contains($valLower, 'dep') && !str_contains($valLower, 'dep.')) {
                    $deptCol = $c;
                }
                if ($valLower === 'dep') {
                    $deptCol = $c;
                }
            }

            if (!$timeFrameCol) {
                $timeFrameCol = 'E';
            }
            if (!$deptCol) {
                $deptCol = 'D';
            }

            $departmentHours = [];
            $sheetDept = $this->normalizeDepartment($sheetName);

            for ($r = 3; $r <= $highestRow; $r++) {
                $cell = $sheet->getCell($timeFrameCol . $r);
                $timeVal = $cell->getValue();
                if ($timeVal instanceof \DateTimeInterface) {
                    $h = (int) $timeVal->format('H') ?: 24;
                    if ($h >= 1 && $h <= 24) {
                        $departmentHours[] = $h;
                    }
                    continue;
                }
                $timeStr = is_numeric($timeVal) ? (string) (int) $timeVal : (string) $timeVal;
                if (strpos($timeStr, '=') === 0) {
                    continue;
                }
                $hours = $this->parseTimeFrameToHours($timeStr);
                if ($hours !== null && $hours >= 1 && $hours <= 8760) {
                    $departmentHours[] = $hours;
                }
            }

            if (empty($departmentHours)) {
                $skipped[] = $sheetDept . ' (no parseable TAT in sheet)';
                continue;
            }

            // Use minimum (strictest SLA) when multiple TATs per department
            $tatHours = min($departmentHours);
            $tatHours = max(1, min(720, $tatHours));
            $imported[] = ['department' => $sheetDept, 'tat_hours' => $tatHours];
        }

        if (!empty($imported)) {
            DB::beginTransaction();
            try {
                foreach ($imported as $row) {
                    $sla = app(TicketSlaService::class);
                    $sla->setDepartmentTat($row['department'], $row['tat_hours']);
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
                return ['imported' => [], 'skipped' => $skipped, 'errors' => $errors];
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    protected function normalizeDepartment(string $name): string
    {
        $key = strtolower(trim($name));
        return $this->departmentAliases[$key] ?? ucwords(strtolower(trim($name)));
    }
}
