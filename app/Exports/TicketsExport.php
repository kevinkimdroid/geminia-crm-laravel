<?php

namespace App\Exports;

use App\Exports\Concerns\WithExcelDateValueBinder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TicketsExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithCustomValueBinder
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
            'Ticket No',
            'Title',
            'Contact',
            'Policy',
            'Status',
            'Priority',
            'Source',
            'Created By',
            'Assigned To',
            'Closed By',
            'Created',
            'Description',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 40,
            'C' => 28,
            'D' => 18,
            'E' => 14,
            'F' => 12,
            'G' => 12,
            'H' => 20,
            'I' => 20,
            'J' => 20,
            'K' => 14,
            'L' => 60,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8EEF4']]],
        ];
    }
}
