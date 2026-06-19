<?php

namespace App\Services\Receipts;

use DOMDocument;
use DOMElement;

/**
 * Builds a BI Publisher-style XML data document from a receipt array.
 * This XML is the canonical data payload that gets merged into the RTF
 * template; it is also exposed for download/audit.
 */
class ReceiptXmlBuilder
{
    /**
     * @param  array{header: array<string,mixed>, lines: array<int, array<string,mixed>>}  $receipt
     */
    public function build(array $receipt): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('RECEIPT');
        $root->setAttribute('company', (string) config('receipt.company_name'));
        $root->setAttribute('generated_at', now()->toIso8601String());
        $dom->appendChild($root);

        foreach ($receipt['header'] as $key => $value) {
            $this->appendField($dom, $root, strtoupper($key), $value);
        }

        $linesEl = $dom->createElement('LINES');
        $root->appendChild($linesEl);

        foreach ($receipt['lines'] as $line) {
            $lineEl = $dom->createElement('LINE');
            foreach ($line as $key => $value) {
                $this->appendField($dom, $lineEl, strtoupper($key), $value);
            }
            $linesEl->appendChild($lineEl);
        }

        return $dom->saveXML();
    }

    protected function appendField(DOMDocument $dom, DOMElement $parent, string $name, mixed $value): void
    {
        $el = $dom->createElement($name);
        // createTextNode performs correct XML escaping of &, <, > and quotes.
        $el->appendChild($dom->createTextNode($this->text($value)));
        $parent->appendChild($el);
    }

    protected function text(mixed $value): string
    {
        if (is_float($value) || is_int($value)) {
            $formatted = number_format((float) $value, 2, '.', '');

            return $formatted;
        }

        return (string) $value;
    }
}
