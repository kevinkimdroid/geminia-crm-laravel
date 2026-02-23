<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Layout Editor - reads and updates Vtiger blocks and fields.
 */
class LayoutService
{
    protected static array $blockLabelMap = [
        'LBL_CAMPAIGN_INFORMATION' => 'Campaign Details',
        'LBL_CUSTOM_INFORMATION' => 'Custom Information',
        'LBL_EXPECTATIONS_AND_ACTUALS' => 'Expectations & Actuals',
        'LBL_DESCRIPTION_INFORMATION' => 'Description',
        'LBL_LEAD_INFORMATION' => 'Lead Information',
        'LBL_CONTACT_INFORMATION' => 'Contact Information',
        'LBL_POTENTIAL_INFORMATION' => 'Opportunity Details',
        'LBL_TICKET_INFORMATION' => 'Ticket Information',
        'LBL_REPORTS' => 'Report Information',
    ];

    protected static array $uitypeLabels = [
        1 => 'Text',
        2 => 'Text',
        4 => 'Text (Number)',
        5 => 'Date',
        6 => 'Email',
        7 => 'Number',
        9 => 'Percent',
        10 => 'Currency',
        11 => 'Phone',
        13 => 'Picklist',
        15 => 'Picklist',
        16 => 'Picklist',
        17 => 'URL',
        19 => 'Text Area',
        53 => 'Owner',
        71 => 'Currency',
        72 => 'Currency',
    ];

    /**
     * Get modules available for layout editing.
     */
    public function getEditableModules(): array
    {
        $vtigerNames = array_filter(array_unique(array_values(config('modules.app_to_vtiger', []))));
        $tabs = DB::connection('vtiger')
            ->table('vtiger_tab as t')
            ->whereIn('t.name', $vtigerNames)
            ->where('t.presence', 0)
            ->orderBy('t.name')
            ->get(['t.tabid', 't.name']);

        $labels = [
            'Home' => 'Dashboard',
            'Potentials' => 'Opportunities',
            'HelpDesk' => 'Tickets',
            'Contacts' => 'Contacts',
            'Leads' => 'Leads',
            'Campaigns' => 'Campaigns',
            'Reports' => 'Reports',
        ];

        return $tabs->map(fn ($t) => [
            'tabid' => $t->tabid,
            'name' => $t->name,
            'label' => $labels[$t->name] ?? $t->name,
        ])->values()->all();
    }

    /**
     * Get blocks and fields for a module (Detail View Layout).
     */
    public function getLayoutForModule(int $tabid): array
    {
        $tab = DB::connection('vtiger')->table('vtiger_tab')->where('tabid', $tabid)->first();
        if (!$tab) {
            return ['tab' => null, 'blocks' => []];
        }

        $blocks = DB::connection('vtiger')
            ->table('vtiger_blocks')
            ->where('tabid', $tabid)
            ->orderBy('sequence')
            ->get();

        $fields = DB::connection('vtiger')
            ->table('vtiger_field')
            ->where('tabid', $tabid)
            ->whereIn('presence', [0, 2])
            ->orderBy('block')
            ->orderBy('sequence')
            ->get();

        $fieldsByBlock = $fields->groupBy(function ($f) {
            $b = $f->block ?? '';
            return ($b === '' || $b === null) ? '__orphan__' : $b;
        });

        $result = [];
        foreach ($blocks as $block) {
            $blockFields = $fieldsByBlock->get($block->blockid, collect())
                ->map(fn ($f) => $this->formatField($f))
                ->values()
                ->all();

            $result[] = [
                'blockid' => $block->blockid,
                'label' => $this->translateBlockLabel($block->blocklabel),
                'sequence' => (int) $block->sequence,
                'fields' => $blockFields,
            ];
        }

        $orphanFields = $fieldsByBlock->get('__orphan__', collect());
        if ($orphanFields->isNotEmpty()) {
            $result[] = [
                'blockid' => null,
                'label' => 'Other Fields',
                'sequence' => 999,
                'fields' => $orphanFields->map(fn ($f) => $this->formatField($f))->values()->all(),
            ];
        }

        usort($result, fn ($a, $b) => $a['sequence'] <=> $b['sequence']);

        return [
            'tab' => ['tabid' => $tab->tabid, 'name' => $tab->name],
            'blocks' => $result,
        ];
    }

    protected function formatField(object $f): array
    {
        $typeofdata = $f->typeofdata ?? '';
        $mandatory = strpos($typeofdata, '~M') !== false;

        return [
            'fieldid' => $f->fieldid,
            'fieldlabel' => $f->fieldlabel ?: $f->fieldname,
            'fieldname' => $f->fieldname,
            'uitype' => $f->uitype ?? 1,
            'uitype_label' => self::$uitypeLabels[$f->uitype ?? 1] ?? 'Text',
            'mandatory' => $mandatory,
            'quickcreate' => (int) ($f->quickcreate ?? 0),
            'masseditable' => (int) ($f->masseditable ?? 0),
            'headerfield' => (int) ($f->headerfield ?? 0),
            'summaryfield' => (int) ($f->summaryfield ?? 0),
            'readonly' => (int) ($f->readonly ?? 0),
            'displaytype' => (int) ($f->displaytype ?? 1),
            'defaultvalue' => $f->defaultvalue ?? '',
        ];
    }

    protected function translateBlockLabel(string $label): string
    {
        return self::$blockLabelMap[$label] ?? str_replace(['LBL_', '_'], ['', ' '], $label);
    }

    /**
     * Update field options in Vtiger.
     */
    public function updateFieldOptions(int $fieldid, array $options): bool
    {
        $updates = [];
        if (array_key_exists('mandatory', $options)) {
            $field = DB::connection('vtiger')->table('vtiger_field')->where('fieldid', $fieldid)->first();
            if ($field) {
                $typeofdata = $field->typeofdata ?? '';
                $typeofdata = $options['mandatory']
                    ? preg_replace('/~O/', '~M', $typeofdata)
                    : preg_replace('/~M/', '~O', $typeofdata);
                $updates['typeofdata'] = $typeofdata;
            }
        }
        if (array_key_exists('quickcreate', $options)) {
            $updates['quickcreate'] = (int) $options['quickcreate'];
        }
        if (array_key_exists('masseditable', $options)) {
            $updates['masseditable'] = (int) $options['masseditable'];
        }
        if (array_key_exists('headerfield', $options)) {
            $updates['headerfield'] = (int) $options['headerfield'];
        }
        if (array_key_exists('summaryfield', $options)) {
            $updates['summaryfield'] = (int) $options['summaryfield'];
        }

        if (empty($updates)) {
            return true;
        }

        DB::connection('vtiger')->table('vtiger_field')->where('fieldid', $fieldid)->update($updates);
        return true;
    }
}
