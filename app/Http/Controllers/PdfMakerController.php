<?php

namespace App\Http\Controllers;

use App\Models\PdfTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PdfMakerController extends Controller
{
    protected array $modules = [
        'invoices' => [
            'name' => 'Invoices',
            'slug' => 'invoices',
            'description' => 'Template for Invoice',
            'icon' => 'bi-receipt',
            'fields' => ['number', 'date', 'due_date', 'contact', 'address', 'items', 'subtotal', 'tax', 'total'],
        ],
        'sales-orders' => [
            'name' => 'Sales Orders',
            'slug' => 'sales-orders',
            'description' => 'Template for Sales Order',
            'icon' => 'bi-cart-check',
            'fields' => ['number', 'date', 'contact', 'address', 'items', 'subtotal', 'tax', 'total'],
        ],
        'purchase-orders' => [
            'name' => 'Purchase Orders',
            'slug' => 'purchase-orders',
            'description' => 'Template for Purchase Order',
            'icon' => 'bi-cart-plus',
            'fields' => ['number', 'date', 'vendor', 'address', 'items', 'subtotal', 'tax', 'total'],
        ],
        'general-products' => [
            'name' => 'General Products',
            'slug' => 'general-products',
            'description' => 'Templates for Quotes',
            'icon' => 'bi-box-seam',
            'fields' => ['number', 'date', 'valid_until', 'contact', 'address', 'items', 'subtotal', 'tax', 'total'],
        ],
    ];

    public function index()
    {
        return view('tools.pdf-maker', [
            'modules' => array_values($this->modules),
        ]);
    }

    public function create(string $module)
    {
        $moduleConfig = $this->modules[$module] ?? null;
        if (! $moduleConfig) {
            abort(404);
        }

        return view('tools.pdf-maker-create', [
            'module' => $moduleConfig,
        ]);
    }

    public function template(string $module)
    {
        $moduleConfig = $this->modules[$module] ?? null;
        if (! $moduleConfig) {
            abort(404);
        }

        $template = PdfTemplate::getForModule($module);
        if (! $template) {
            $template = $this->createDefaultTemplate($module);
        }

        return view('tools.pdf-maker-template', [
            'module' => $moduleConfig,
            'template' => $template,
        ]);
    }

    public function storeTemplate(Request $request, string $module)
    {
        $moduleConfig = $this->modules[$module] ?? null;
        if (! $moduleConfig) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:255',
            'company_zip' => 'nullable|string|max:64',
            'company_city' => 'nullable|string|max:128',
            'company_country' => 'nullable|string|max:128',
            'company_phone' => 'nullable|string|max:64',
            'company_fax' => 'nullable|string|max:64',
            'company_website' => 'nullable|string|max:255',
            'footer_text' => 'nullable|string|max:500',
            'show_page_numbers' => 'nullable|boolean',
            'logo' => 'nullable|image|mimes:jpeg,png,gif,webp|max:2048',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $request->file('logo')->store('pdf-logos', 'public');
            $logoPath = 'pdf-logos/' . $request->file('logo')->hashName();
        }

        $existing = PdfTemplate::where('module', $module)->where('is_default', true)->first();
        $data = [
            'name' => $validated['name'] ?? 'Default',
            'company_name' => $validated['company_name'] ?? '',
            'tagline' => $validated['tagline'] ?? '',
            'field_layout' => ($existing ? $existing->field_layout : null) ?? $this->getDefaultFieldLayout($moduleConfig),
            'company_address' => $validated['company_address'] ?? '',
            'company_zip' => $validated['company_zip'] ?? '',
            'company_city' => $validated['company_city'] ?? '',
            'company_country' => $validated['company_country'] ?? '',
            'company_phone' => $validated['company_phone'] ?? '',
            'company_fax' => $validated['company_fax'] ?? '',
            'company_website' => $validated['company_website'] ?? '',
            'footer_text' => $validated['footer_text'] ?? '',
            'show_page_numbers' => (bool) ($validated['show_page_numbers'] ?? false),
        ];
        if ($logoPath !== null) {
            $data['logo_path'] = $logoPath;
        } elseif ($existing) {
            $data['logo_path'] = $existing->logo_path;
        }

        $template = PdfTemplate::updateOrCreate(
            ['module' => $module, 'is_default' => true],
            $data
        );

        return redirect()->route('tools.pdf-maker.template', $module)
            ->with('success', 'Template saved successfully.');
    }

    public function preview(Request $request, string $module)
    {
        $moduleConfig = $this->modules[$module] ?? null;
        if (! $moduleConfig) {
            abort(404);
        }

        $template = PdfTemplate::getForModule($module) ?? $this->createDefaultTemplate($module);

        $html = $this->buildPdfHtml($template, $moduleConfig, true);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return $pdf->stream('preview-' . $module . '.pdf');
    }

    public function removeLogo(string $module)
    {
        $template = PdfTemplate::getForModule($module);
        if ($template && $template->logo_path) {
            Storage::disk('public')->delete($template->logo_path);
            $template->update(['logo_path' => null]);
        }

        return redirect()->route('tools.pdf-maker.template', $module)
            ->with('success', 'Logo removed.');
    }

    protected function createDefaultTemplate(string $module): PdfTemplate
    {
        $moduleConfig = $this->modules[$module];
        $fields = collect($moduleConfig['fields'] ?? [])->map(fn ($key, $i) => [
            'key' => $key,
            'label' => ucfirst(str_replace('_', ' ', $key)),
            'visible' => true,
            'order' => $i,
        ])->values()->toArray();

        return new PdfTemplate([
            'module' => $module,
            'name' => 'Default',
            'company_name' => '',
            'tagline' => '',
            'company_address' => '',
            'company_zip' => '',
            'company_city' => '',
            'company_country' => '',
            'company_phone' => '',
            'company_fax' => '',
            'company_website' => '',
            'footer_text' => '',
            'show_page_numbers' => false,
            'header_content' => null,
            'footer_content' => null,
            'body_template' => null,
            'field_layout' => $fields,
            'is_default' => true,
        ]);
    }

    protected function getDefaultFieldLayout(array $moduleConfig): array
    {
        return collect($moduleConfig['fields'] ?? [])->map(fn ($key, $i) => [
            'key' => $key,
            'label' => ucfirst(str_replace('_', ' ', $key)),
            'visible' => true,
            'order' => $i,
        ])->values()->toArray();
    }

    protected function getDefaultBodyTemplate(string $module): string
    {
        $contactLabel = in_array($module, ['purchase-orders']) ? 'Vendor' : 'Contact';
        $contactKey = in_array($module, ['purchase-orders']) ? 'vendor' : 'contact';

        $rows = [
            ['Document', '{number}'],
            ['Date', '{date}'],
            [$contactLabel, '{' . $contactKey . '}'],
            ['Address', '{address}'],
        ];
        if (in_array($module, ['invoices'])) {
            $rows[] = ['Due Date', '{due_date}'];
        }
        if (in_array($module, ['general-products'])) {
            $rows[] = ['Valid Until', '{valid_until}'];
        }

        $trs = implode('', array_map(fn ($r) => "<tr><td style=\"padding:8px;border-bottom:1px solid #e2e8f0;\"><strong>{$r[0]}</strong></td><td style=\"padding:8px;border-bottom:1px solid #e2e8f0;\">{$r[1]}</td></tr>", $rows));

        return '<table style="width:100%;border-collapse:collapse;margin-top:20px;">' . $trs . '
            <tr><td colspan="2" style="padding:20px 8px;border-bottom:1px solid #e2e8f0;"><strong>Items</strong></td></tr>
            <tr><td colspan="2" style="padding:12px 8px;">{items}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #e2e8f0;"><strong>Subtotal</strong></td><td style="padding:8px;border-bottom:1px solid #e2e8f0;">{subtotal}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #e2e8f0;"><strong>Tax</strong></td><td style="padding:8px;border-bottom:1px solid #e2e8f0;">{tax}</td></tr>
            <tr><td style="padding:8px;"><strong>Total</strong></td><td style="padding:8px;"><strong>{total}</strong></td></tr>
        </table>';
    }

    protected function buildPdfHtml(PdfTemplate $template, array $moduleConfig, bool $preview = false): string
    {
        $header = $this->buildHeaderHtml($template, $moduleConfig);
        $footer = $this->buildFooterHtml($template);
        $body = $this->buildBodyHtml($template, $moduleConfig, $preview);

        return view('tools.pdf-layout', [
            'headerHtml' => $header,
            'footerHtml' => $footer,
            'bodyHtml' => $body,
        ])->render();
    }

    protected function buildHeaderHtml(PdfTemplate $template, array $moduleConfig = []): string
    {
        $logoUrl = $template->logo_path
            ? storage_path('app/public/' . $template->logo_path)
            : null;
        if ($logoUrl && ! file_exists($logoUrl)) {
            $logoUrl = null;
        }
        $logoHtml = $logoUrl
            ? '<img src="' . $logoUrl . '" style="max-height:50px;max-width:180px;" alt="Logo" />'
            : '';

        $companyName = $template->company_name ?: ($moduleConfig['name'] ?? 'Document');
        $tagline = $template->tagline ? '<p style="margin:4px 0 0 0;font-size:11px;color:#64748b;">' . e($template->tagline) . '</p>' : '';

        return '<div style="display:flex;align-items:center;gap:20px;">'
            . $logoHtml
            . '<div><h2 style="margin:0;font-size:18px;color:#0E4385;">' . e($companyName) . '</h2>'
            . $tagline . '</div></div>';
    }

    protected function buildCompanyBlock(PdfTemplate $template, bool $preview): string
    {
        $name = $preview ? ($template->company_name ?: 'Company Name') : ($template->company_name ?? '');
        $addr = $preview ? ($template->company_address ?: '123 Business St') : ($template->company_address ?? '');
        $zip = $preview ? ($template->company_zip ?: '10001') : ($template->company_zip ?? '');
        $city = $preview ? ($template->company_city ?: 'New York') : ($template->company_city ?? '');
        $country = $preview ? ($template->company_country ?: 'USA') : ($template->company_country ?? '');
        $phone = $preview ? ($template->company_phone ?: '+1 234 567 8900') : ($template->company_phone ?? '');
        $fax = $preview ? ($template->company_fax ?: '+1 234 567 8901') : ($template->company_fax ?? '');
        $web = $preview ? ($template->company_website ?: 'www.example.com') : ($template->company_website ?? '');

        $lines = array_filter([$name, $addr, trim("$zip $city"), $country]);
        if ($phone || $fax) {
            $lines[] = trim("$phone " . ($fax ? "· $fax" : ''));
        }
        if ($web) {
            $lines[] = $web;
        }
        return implode('<br>', array_map('e', $lines)) ?: '—';
    }

    protected function buildFooterHtml(PdfTemplate $template): string
    {
        $parts = [];
        if ($template->footer_text) {
            $parts[] = '<span>' . e($template->footer_text) . '</span>';
        }
        if ($template->show_page_numbers) {
            $parts[] = '<span>Page 1 of 1</span>';
        }
        if (empty($parts)) {
            $parts[] = '<span>Generated by Geminia CRM</span>';
        }
        return '<p style="margin:0;font-size:10px;color:#64748b;">' . implode(' &middot; ', $parts) . '</p>';
    }

    protected function buildBodyHtml(PdfTemplate $template, array $moduleConfig, bool $preview): string
    {
        $billTo = $preview
            ? "Sample Customer\n123 Client Street\n10001 New York\nNY, USA"
            : "—";
        $billToHtml = nl2br(e($billTo));

        $companyBlock = $this->buildCompanyBlock($template, $preview);

        $docLabel = $moduleConfig['name'] ?? 'Document';
        $date = $preview ? date('Y-m-d') : '';
        $subtotal = $preview ? '100.00' : '';
        $tax = $preview ? '0.00' : '';
        $total = $preview ? '100.00' : '';
        $currency = $preview ? 'KES' : '';

        $productRows = $preview
            ? '<tr><td style="padding:8px;border-bottom:1px solid #e2e8f0;">Sample Product</td><td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:right;">100.00 ' . e($currency) . '</td></tr>'
            : '<tr><td style="padding:8px;border-bottom:1px solid #e2e8f0;">$PRODUCT_TITLE$</td><td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:right;">$PRODUCT_PRICE$ $CURRENCY$</td></tr>';

        return '<div style="margin-top:20px;">
            <table style="width:100%;border-collapse:collapse;margin-bottom:24px;">
                <tr>
                    <td style="width:50%;vertical-align:top;padding-right:24px;">
                        <div style="font-size:10px;color:#64748b;margin-bottom:6px;">Bill To</div>
                        <div style="font-size:11px;">' . $billToHtml . '</div>
                    </td>
                    <td style="width:50%;vertical-align:top;text-align:right;">
                        <div style="font-size:10px;color:#64748b;margin-bottom:6px;">' . e($docLabel) . '</div>
                        <div style="font-size:11px;">' . $companyBlock . '</div>
                    </td>
                </tr>
            </table>
            <div style="margin-bottom:16px;font-size:11px;">
                <span style="color:#64748b;">' . e($docLabel) . ' Date:</span> ' . e($date) . '
            </div>
            <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:10px;text-align:left;border:1px solid #e2e8f0;font-size:10px;text-transform:uppercase;">Description</th>
                        <th style="padding:10px;text-align:right;border:1px solid #e2e8f0;font-size:10px;text-transform:uppercase;">List Price</th>
                    </tr>
                </thead>
                <tbody>' . $productRows . '</tbody>
            </table>
            <table style="width:100%;max-width:280px;margin-left:auto;">
                <tr><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Subtotal</td><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;text-align:right;">' . e($subtotal) . ' ' . e($currency) . '</td></tr>
                <tr><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Tax</td><td style="padding:6px 0;border-bottom:1px solid #e2e8f0;text-align:right;">' . e($tax) . ' ' . e($currency) . '</td></tr>
                <tr><td style="padding:10px 0;font-weight:bold;">Total</td><td style="padding:10px 0;text-align:right;font-weight:bold;">' . e($total) . ' ' . e($currency) . '</td></tr>
            </table>
        </div>';
    }
}
