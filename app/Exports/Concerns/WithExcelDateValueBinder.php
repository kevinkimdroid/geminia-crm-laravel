<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

/**
 * Uses PhpSpreadsheet's AdvancedValueBinder so ISO date/datetime strings (e.g. Y-m-d H:i:s)
 * are stored as Excel serial values with date number formats — enabling filters and sorting.
 */
trait WithExcelDateValueBinder
{
    public function bindValue(Cell $cell, $value): bool
    {
        return $this->excelDateAdvancedBinder()->bindValue($cell, $value);
    }

    private function excelDateAdvancedBinder(): AdvancedValueBinder
    {
        static $binder;

        return $binder ??= new AdvancedValueBinder();
    }
}
