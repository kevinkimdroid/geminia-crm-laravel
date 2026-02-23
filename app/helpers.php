<?php

if (! function_exists('tel_href')) {
    /**
     * Normalize phone number for tel: links. Strips 0 and 254 prefixes to avoid
     * double-prefix dial failure with MicroSIP. Returns 9-digit base for Kenya
     * so the SIP dial plan can apply its own prefix.
     */
    function tel_href(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return '';
        }
        // Kenya: strip 254 and leading 0 to get 9-digit base (7XXXXXXXX)
        if (substr($digits, 0, 3) === '254' && strlen($digits) === 12) {
            return substr($digits, 3);
        }
        if (substr($digits, 0, 1) === '0' && strlen($digits) === 10) {
            return substr($digits, 1);
        }
        // E.g. 00254722000000 - strip 00 and 254
        if (substr($digits, 0, 5) === '00254' && strlen($digits) >= 14) {
            return substr($digits, 5);
        }
        // Already 9 digits or other format - return as-is (avoid breaking intl numbers)
        return $digits;
    }
}
