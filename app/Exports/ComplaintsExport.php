<?php

namespace App\Exports;

use App\Exports\Concerns\WithExcelDateValueBinder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ComplaintsExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithCustomValueBinder
{
    use WithExcelDateValueBinder;

    public function __construct(protected array $rows)
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Reference',
            'Date Received',
            'Complainant Name',
            'Complainant Phone',
            'Complainant Email',
            'Linked Contact',
            'Policy Number',
            'Nature',
            'Source',
            'Status',
            'Priority',
            'Assigned To',
            'Date Resolved',
            'Complaint Description',
            'Resolution Notes',
            'Created At',
            'Last Updated',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,  // Reference
            'B' => 14,  // Date Received
            'C' => 22,  // Complainant Name
            'D' => 16,  // Phone
            'E' => 30,  // Email
            'F' => 22,  // Linked Contact
            'G' => 16,  // Policy Number
            'H' => 18,  // Nature
            'I' => 14,  // Source
            'J' => 18,  // Status
            'K' => 12,  // Priority
            'L' => 18,  // Assigned To
            'M' => 14,  // Date Resolved
            'N' => 60,  // Description - wide for full text
            'O' => 60,  // Resolution Notes - wide for full text
            'P' => 16,  // Created At
            'Q' => 16,  // Last Updated
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8EEF4']]],
        ];
    }
}
