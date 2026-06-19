<?php

namespace App\Services\Receipts;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Merges receipt data into an RTF template and (optionally) converts it to PDF.
 *
 * Template tokens:
 *   [[FIELD]]                  header fields (case-insensitive), e.g. [[RECEIPT_NO]]
 *   [[#LINES]] ... [[/LINES]]  repeating region rendered once per line item
 *   [[LINE.FIELD]]             line fields inside the region, e.g. [[LINE.AMOUNT]]
 *
 * If a LibreOffice 'soffice' binary is configured, the merged RTF is converted
 * to PDF. Otherwise the merged RTF itself is returned (still printable in Word).
 */
class RtfReceiptRenderer
{
    public function __construct(
        protected string $templatePath,
        protected string $outputPath,
        protected ?string $sofficePath = null,
    ) {
    }

    /**
     * Render a receipt to a file.
     *
     * @param  array{header: array<string,mixed>, lines: array<int, array<string,mixed>>}  $receipt
     * @return array{path: string, mime: string, filename: string, format: string}
     */
    public function render(array $receipt): array
    {
        $rtf = $this->merge($receipt);

        File::ensureDirectoryExists($this->outputPath);

        $receiptNo = (string) ($receipt['header']['receipt_no'] ?? 'receipt');
        $base = 'receipt_'.preg_replace('/[^A-Za-z0-9_-]+/', '_', $receiptNo);

        $rtfFile = $this->outputPath.DIRECTORY_SEPARATOR.$base.'.rtf';
        File::put($rtfFile, $rtf);

        if ($this->sofficePath && trim($this->sofficePath) !== '') {
            $pdfFile = $this->convertToPdf($rtfFile);

            return [
                'path' => $pdfFile,
                'mime' => 'application/pdf',
                'filename' => $base.'.pdf',
                'format' => 'pdf',
            ];
        }

        return [
            'path' => $rtfFile,
            'mime' => 'application/rtf',
            'filename' => $base.'.rtf',
            'format' => 'rtf',
        ];
    }

    /**
     * Merge receipt data into the RTF template and return the merged RTF string.
     *
     * @param  array{header: array<string,mixed>, lines: array<int, array<string,mixed>>}  $receipt
     */
    public function merge(array $receipt): string
    {
        $template = $this->loadTemplate();

        // 1) Expand the repeating LINES region.
        $template = preg_replace_callback(
            '/\[\[#LINES\]\](.*?)\[\[\/LINES\]\]/s',
            function (array $m) use ($receipt): string {
                $regionTemplate = $m[1];
                $out = '';

                foreach ($receipt['lines'] as $line) {
                    $out .= preg_replace_callback(
                        '/\[\[\s*LINE\.([A-Za-z0-9_]+)\s*\]\]/',
                        function (array $tm) use ($line): string {
                            $key = mb_strtolower($tm[1]);

                            return $this->escapeRtf($this->display($line[$key] ?? ''));
                        },
                        $regionTemplate
                    );
                }

                return $out;
            },
            $template
        );

        // 2) Replace header tokens.
        $tokens = $this->headerTokens($receipt['header']);

        $template = preg_replace_callback(
            '/\[\[\s*([A-Za-z0-9_]+)\s*\]\]/',
            function (array $m) use ($tokens): string {
                $key = mb_strtoupper($m[1]);

                return array_key_exists($key, $tokens)
                    ? $this->escapeRtf($tokens[$key])
                    : ''; // unknown token -> blank
            },
            $template
        );

        return $template;
    }

    /**
     * @param  array<string,mixed>  $header
     * @return array<string,string>
     */
    protected function headerTokens(array $header): array
    {
        $tokens = [];
        foreach ($header as $key => $value) {
            $tokens[mb_strtoupper($key)] = $this->display($value);
        }

        $tokens['COMPANY_NAME'] = (string) config('receipt.company_name');
        $tokens['GENERATED_AT'] = now()->format('d M Y H:i');
        $tokens['CURRENT_DATE'] = now()->format('d M Y');
        $tokens['PRINT_DATE'] = now()->format('d-m-Y');

        return $tokens;
    }

    protected function display(mixed $value): string
    {
        if (is_float($value) || (is_int($value) && ! is_bool($value))) {
            return number_format((float) $value, 2);
        }

        return (string) $value;
    }

    protected function loadTemplate(): string
    {
        if (! is_file($this->templatePath)) {
            throw new RuntimeException("RTF template not found at: {$this->templatePath}");
        }

        return File::get($this->templatePath);
    }

    /**
     * Escape a plain string for safe insertion into RTF body text.
     */
    protected function escapeRtf(string $value): string
    {
        $out = '';
        $len = mb_strlen($value, 'UTF-8');

        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($value, $i, 1, 'UTF-8');
            $code = mb_ord($char, 'UTF-8');

            if ($char === '\\') {
                $out .= '\\\\';
            } elseif ($char === '{') {
                $out .= '\{';
            } elseif ($char === '}') {
                $out .= '\}';
            } elseif ($char === "\n") {
                $out .= '\line ';
            } elseif ($char === "\r") {
                continue;
            } elseif ($code !== false && $code > 127) {
                // RTF unicode escape with a '?' fallback for legacy readers.
                $signed = $code > 32767 ? $code - 65536 : $code;
                $out .= '\u'.$signed.'?';
            } else {
                $out .= $char;
            }
        }

        return $out;
    }

    protected function convertToPdf(string $rtfFile): string
    {
        $process = new Process([
            $this->sofficePath,
            '--headless',
            '--norestore',
            '--convert-to', 'pdf',
            '--outdir', $this->outputPath,
            $rtfFile,
        ]);
        $process->setTimeout(120);
        $process->run();

        $pdfFile = preg_replace('/\.rtf$/i', '.pdf', $rtfFile);

        if (! $process->isSuccessful() || ! is_file($pdfFile)) {
            throw new RuntimeException(
                'LibreOffice RTF->PDF conversion failed: '.$process->getErrorOutput().$process->getOutput()
            );
        }

        return $pdfFile;
    }
}
