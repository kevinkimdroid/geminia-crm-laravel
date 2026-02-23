<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfTemplate extends Model
{
    protected $fillable = [
        'module',
        'name',
        'company_name',
        'tagline',
        'company_address',
        'company_zip',
        'company_city',
        'company_country',
        'company_phone',
        'company_fax',
        'company_website',
        'header_content',
        'footer_content',
        'footer_text',
        'show_page_numbers',
        'logo_path',
        'body_template',
        'field_layout',
        'styles',
        'is_default',
    ];

    protected $casts = [
        'field_layout' => 'array',
        'styles' => 'array',
        'is_default' => 'boolean',
        'show_page_numbers' => 'boolean',
    ];

    public static function getForModule(string $module): ?self
    {
        return static::where('module', $module)->where('is_default', true)->first()
            ?? static::where('module', $module)->first();
    }
}
