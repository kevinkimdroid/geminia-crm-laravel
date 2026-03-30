<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class DischargeVoucherPdfService
{
    /**
     * @param  array{
     *   policy_number:string,
     *   life_assured:?string,
     *   product:?string,
     *   maturity_display:string,
     *   maturity_iso:string,
     *   issue_date_display:string,
     *   email:?string,
     *   phone:?string,
     *   maturity_amount:?string
     * }  $data
     */
    public function renderPdfBinary(array $data): string
    {
        $html = View::make('pdf.discharge-voucher', ['v' => $data])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->output();
    }

    public function suggestedFilename(string $policyNumber): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $policyNumber);

        return 'discharge-voucher-'.($safe ?: 'policy').'.pdf';
    }
}
