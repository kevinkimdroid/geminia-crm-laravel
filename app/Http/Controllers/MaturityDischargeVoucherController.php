<?php

namespace App\Http\Controllers;

use App\Services\DischargeVoucherPdfService;
use App\Services\ErpClientService;
use App\Services\PlainTextMailSender;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MaturityDischargeVoucherController extends Controller
{
    public function pdf(Request $request, ErpClientService $erp, DischargeVoucherPdfService $pdfSvc): Response
    {
        $validated = $request->validate([
            'policy_number' => 'required|string|max:64',
            'maturity' => 'required|date',
        ]);

        $data = $this->buildVoucherData($validated['policy_number'], $validated['maturity'], $erp);

        $binary = $pdfSvc->renderPdfBinary($data);
        $name = $pdfSvc->suggestedFilename($data['policy_number']);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    public function email(Request $request, ErpClientService $erp, DischargeVoucherPdfService $pdfSvc, PlainTextMailSender $mail)
    {
        $validated = $request->validate([
            'policy_number' => 'required|string|max:64',
            'maturity' => 'required|date',
            'to_email' => 'required|email|max:255',
            'to_name' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:5000',
        ]);

        $data = $this->buildVoucherData($validated['policy_number'], $validated['maturity'], $erp);
        $binary = $pdfSvc->renderPdfBinary($data);
        $attachName = $pdfSvc->suggestedFilename($data['policy_number']);

        $company = config('app.name', 'Geminia Life');
        $subject = 'Discharge voucher — Policy '.$data['policy_number'];
        $body = trim((string) ($validated['message'] ?? ''));
        if ($body === '') {
            $body = "Dear ".($validated['to_name'] ?: 'client').",\n\n"
                ."Please find attached your discharge voucher for policy {$data['policy_number']} (maturity {$data['maturity_display']}).\n\n"
                ."Kind regards,\n{$company}";
        } else {
            $body .= "\n\n—\nAttachment: discharge voucher (PDF) for policy {$data['policy_number']}.";
        }

        $ok = $mail->sendWithPdfAttachment(
            $validated['to_email'],
            $validated['to_name'] ?? null,
            $subject,
            $body,
            $attachName,
            $binary
        );

        if (! $ok) {
            return redirect()->back()->withInput()->with('error', 'Could not send email. Check mail configuration (Microsoft Graph or SMTP) and try again.');
        }

        return redirect()->back()->with('success', 'Discharge voucher emailed to '.$validated['to_email'].'.');
    }

    /**
     * @return array<string, string|null>
     */
    protected function buildVoucherData(string $policyNumber, string $maturityInput, ErpClientService $erp): array
    {
        $policyNumber = trim($policyNumber);
        $maturityCarbon = \Carbon\Carbon::parse($maturityInput)->startOfDay();
        $maturityIso = $maturityCarbon->format('Y-m-d');

        // The maturities report lists partial/full maturity events (PPM_EXPECTED_DATE), which can
        // legitimately differ from the policy's final maturity date (POL_MATURITY_DATE). So the
        // local maturities cache is the source of truth for the (policy, maturity-event) pair; the
        // live ERP policy record is used only to enrich the remaining voucher fields. The strict
        // maturity match is kept only for ad-hoc policies that are NOT in the maturities report.
        $cacheRow = $this->findMaturityInLocalCaches($policyNumber, $maturityIso);
        $erpRow = $erp->getPolicyDetails($policyNumber);

        if ($cacheRow === null && $erpRow === null) {
            abort(404, 'Policy not found. Check the policy number or sync maturities / ERP.');
        }

        if ($cacheRow === null && $erpRow !== null) {
            $rowMaturity = $erpRow['maturity'] ?? $erpRow['maturity_date'] ?? null;
            if ($rowMaturity) {
                $rowMat = \Carbon\Carbon::parse($rowMaturity)->format('Y-m-d');
                if ($rowMat !== $maturityIso) {
                    abort(422, 'Maturity date does not match policy record in ERP. Expected '.$rowMat.' for this policy.');
                }
            }
        }

        // Build the working row: prefer live ERP fields, fall back to cached values for anything
        // the ERP lookup did not supply (or when ERP has no record for this policy at all).
        $row = is_array($erpRow) ? $erpRow : ($erpRow !== null ? (array) $erpRow : []);
        if ($cacheRow !== null) {
            $cacheArr = is_array($cacheRow) ? $cacheRow : (array) $cacheRow;
            foreach ($cacheArr as $k => $v) {
                if (! array_key_exists($k, $row) || $row[$k] === null || $row[$k] === '') {
                    $row[$k] = $v;
                }
            }
        }

        $life = trim((string) ($row['life_assured'] ?? $row['life_assur'] ?? $row['name'] ?? ''));
        $product = trim((string) ($row['product'] ?? $row['prod_desc'] ?? ''));

        $rawAmount = $row['paid_mat_amt'] ?? $row['production_amt'] ?? $row['maturity_amount'] ?? null;
        $amount = null;
        if ($rawAmount !== null && $rawAmount !== '') {
            if (is_numeric($rawAmount)) {
                $amount = number_format((float) $rawAmount, 2);
            } else {
                $amount = trim((string) $rawAmount);
            }
        }

        $emailVal = trim((string) ($row['email_adr'] ?? $row['email'] ?? $row['client_email'] ?? ''));
        $phoneVal = trim((string) ($row['phone_no'] ?? $row['mobile'] ?? $row['client_contact'] ?? ''));

        return [
            'policy_number' => $policyNumber,
            'life_assured' => $life !== '' ? $life : null,
            'product' => $product !== '' ? $product : null,
            'maturity_display' => $maturityCarbon->format('d F Y'),
            'maturity_iso' => $maturityIso,
            'issue_date_display' => now()->format('d F Y'),
            'email' => $emailVal !== '' ? $emailVal : null,
            'phone' => $phoneVal !== '' ? $phoneVal : null,
            'maturity_amount' => $amount,
        ];
    }

    /**
     * @return object|array|null
     */
    protected function findMaturityInLocalCaches(string $policyNumber, string $maturityIso)
    {
        foreach (['maturities_cache', 'erp_clients_cache'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                $hit = DB::table($table)
                    ->where('policy_number', $policyNumber)
                    ->whereDate('maturity', $maturityIso)
                    ->first();
                if ($hit) {
                    return $hit;
                }
            } catch (\Throwable $e) {
                //
            }
        }

        return null;
    }
}
