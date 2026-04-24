<?php

namespace App\Exports;

use App\Exports\Concerns\WithExcelDateValueBinder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReassignmentAuditExport implements FromArray, WithHeadings, WithStyles, WithCustomValueBinder
{
    use WithExcelDateValueBinder;

    public function __construct(
        protected array $rows,
        protected array $headings = []
    )
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        if ($this->headings !== []) {
            return $this->headings;
        }

        return ['Ticket Number'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8EEF4']]],
        ];
    }
}
