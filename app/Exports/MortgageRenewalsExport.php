<?php

namespace App\Exports;

use App\Exports\Concerns\WithExcelDateValueBinder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MortgageRenewalsExport implements FromCollection, WithHeadings, WithStyles, WithCustomValueBinder
{
    use WithExcelDateValueBinder;

    public function __construct(protected Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            $r = is_object($row) ? $row : (object) $row;
            $renewRaw = $r->mendr_renewal_date ?? $r->maturity ?? null;
            $renewStr = '';
            if ($renewRaw !== null && $renewRaw !== '') {
                try {
                    $renewStr = Carbon::parse($renewRaw)->format('Y-m-d');
                } catch (\Throwable) {
                    $renewStr = (string) $renewRaw;
                }
            }

            return [
                $r->policy_number ?? $r->policy_no ?? '',
                $r->life_assur ?? $r->client_name ?? '',
                $r->product ?? '',
                $r->status ?? '',
                $renewStr,
                $r->intermediary ?? $r->pol_prepared_by ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return ['Policy', 'Life assured', 'Product', 'Status', 'Renewal date', 'Intermediary / unit'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8EEF4']]],
        ];
    }
}
