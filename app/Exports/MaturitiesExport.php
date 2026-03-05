<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MaturitiesExport implements FromCollection, WithHeadings, WithStyles
{
    public function __construct(protected Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            $r = is_object($row) ? $row : (object) $row;
            return [
                $r->policy_number ?? $r->policy_no ?? '',
                $r->life_assured ?? $r->life_assur ?? '',
                $r->product ?? '',
                $r->maturity ?? $r->maturity_date ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return ['Policy Number', 'Life Assured', 'Product', 'Maturity Date'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8EEF4']]],
        ];
    }
}
