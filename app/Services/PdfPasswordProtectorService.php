<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\Tcpdf\Fpdi;
use Throwable;

/**
 * Password-protects an existing PDF (re-encodes via FPDI + TCPDF).
 *
 * @see \TCPDF::setProtection For $blockPermissions (listed permissions are restricted) and $encryptionMode.
 */
class PdfPasswordProtectorService
{
    public const ENCRYPTION_RC4_40 = 0;

    public const ENCRYPTION_RC4_128 = 1;

    public const ENCRYPTION_AES_128 = 2;

    public const ENCRYPTION_AES_256 = 3;

    /** @var list<string> */
    public const PERMISSION_FLAGS = [
        'print', 'modify', 'copy', 'annot-forms',
        'fill-forms', 'extract', 'assemble', 'print-high',
    ];

    /**
     * @param  list<string>  $blockPermissions  TCPDF: restrict these actions after unlock (empty = no extra restrictions).
     *
     * @throws PdfProtectionException
     */
    public function protect(
        string $pdfBinary,
        string $userPassword,
        ?string $ownerPassword = null,
        int $encryptionMode = self::ENCRYPTION_AES_128,
        array $blockPermissions = []
    ): string {
        $userPassword = trim($userPassword);
        if ($userPassword === '') {
            throw new PdfProtectionException('User (open) password cannot be empty.');
        }

        if ($encryptionMode < 0 || $encryptionMode > 3) {
            throw new PdfProtectionException('Invalid encryption mode.');
        }

        $ownerPassword = $ownerPassword !== null && trim($ownerPassword) === ''
            ? null
            : ($ownerPassword !== null ? trim($ownerPassword) : null);

        $blockPermissions = array_values(array_intersect(
            array_map('strtolower', $blockPermissions),
            array_map('strtolower', self::PERMISSION_FLAGS)
        ));

        $tmp = tempnam(sys_get_temp_dir(), 'pdfenc');
        if ($tmp === false) {
            throw new PdfProtectionException('Could not create temporary file for PDF encryption.');
        }

        try {
            file_put_contents($tmp, $pdfBinary);
            $pdf = new Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pageCount = $pdf->setSourceFile($tmp);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $pdf->AddPage();
                $pdf->useTemplate($pdf->importPage($pageNo));
            }
            $pdf->setProtection($blockPermissions, $userPassword, $ownerPassword, $encryptionMode);
            $out = $pdf->Output('', 'S');
            if (! is_string($out) || $out === '') {
                throw new PdfProtectionException('Encrypted PDF output was empty.');
            }

            return $out;
        } catch (PdfProtectionException $e) {
            throw $e;
        } catch (PdfParserException $e) {
            Log::warning('PdfPasswordProtectorService: parse error', ['error' => $e->getMessage()]);
            throw new PdfProtectionException('This PDF could not be processed (invalid or already protected in an incompatible way).', 0, $e);
        } catch (Throwable $e) {
            Log::warning('PdfPasswordProtectorService: failed', ['error' => $e->getMessage()]);
            throw new PdfProtectionException('PDF encryption failed: ' . $e->getMessage(), 0, $e);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Strong defaults: AES-256, random owner password (TCPDF), no permission locks after open.
     * Falls back to AES-128 if this PHP build cannot use AES-256.
     *
     * @throws PdfProtectionException
     */
    public function protectWithBestDefaults(string $pdfBinary, string $userPassword): string
    {
        try {
            return $this->protect($pdfBinary, $userPassword, null, self::ENCRYPTION_AES_256, []);
        } catch (PdfProtectionException $e) {
            $msg = $e->getMessage();
            $prevMsg = $e->getPrevious() ? $e->getPrevious()->getMessage() : '';
            if (stripos($msg, 'AES') !== false || stripos($msg, '256') !== false
                || stripos($prevMsg, 'AES') !== false || stripos($prevMsg, 'openssl') !== false) {
                Log::info('PdfPasswordProtectorService: using AES-128 fallback (AES-256 not available)');
                return $this->protect($pdfBinary, $userPassword, null, self::ENCRYPTION_AES_128, []);
            }
            throw $e;
        }
    }
}
