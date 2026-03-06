<?php

if (! function_exists('looks_like_kra_pin')) {
    /**
     * Return true if value looks like Kenya KRA PIN (e.g. A0006812561Y, P051234567X).
     * POLICY column must never show PIN; use this to filter.
     */
    function looks_like_kra_pin(?string $value): bool
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            return false;
        }
        // Kenya KRA PIN: letter + 8–11 digits + letter
        return (bool) preg_match('/^[A-Za-z]\d{8,11}[A-Za-z]$/', $v);
    }
}

if (! function_exists('is_personal_email')) {
    /**
     * Return true if email is from a personal provider (Gmail, Yahoo, Hotmail/Outlook).
     * Used to exclude organizational/corporate emails from display.
     */
    function is_personal_email(?string $email): bool
    {
        $email = trim((string) ($email ?? ''));
        if ($email === '' || ! str_contains($email, '@')) {
            return false;
        }
        $domain = strtolower(explode('@', $email, 2)[1] ?? '');
        $personal = [
            'gmail.com', 'googlemail.com',
            'yahoo.com', 'yahoo.co.ke', 'yahoo.co.uk', 'ymail.com', 'rocketmail.com',
            'hotmail.com', 'hotmail.co.ke', 'hotmail.co.uk', 'live.com', 'outlook.com',
            'outlook.co.ke', 'msn.com', 'passport.com',
        ];

        return in_array($domain, $personal, true);
    }
}

if (! function_exists('personal_email_only')) {
    /**
     * Return email only if it's from Gmail, Yahoo, or Hotmail/Outlook.
     * Returns null/empty for organizational emails.
     */
    function personal_email_only(?string $email): ?string
    {
        $email = trim((string) ($email ?? ''));
        if ($email === '') {
            return null;
        }

        return is_personal_email($email) ? $email : null;
    }
}

if (! function_exists('looks_like_client_id')) {
    /**
     * Return true if value looks like client/contract ID (purely numeric 6+ digits).
     * POLICY column must show policy_number (e.g. GEMPPP0334, IL-GEMS-2025006599), never IDs.
     */
    function looks_like_client_id(?string $value): bool
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            return false;
        }
        return (bool) preg_match('/^\d{6,}$/', $v);
    }
}

if (! function_exists('pick_policy_excluding_pin')) {
    /**
     * Pick first non-empty policy value, excluding KRA PIN and client IDs.
     * Use this to get policy_number from contact cf_860, cf_856, cf_872.
     */
    function pick_policy_excluding_pin(?string ...$candidates): ?string
    {
        foreach ($candidates as $v) {
            $v = trim((string) ($v ?? ''));
            if ($v === '' || looks_like_kra_pin($v) || looks_like_client_id($v)) {
                continue;
            }
            return $v;
        }
        return null;
    }
}

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
