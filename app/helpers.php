<?php

if (! function_exists('ticket_categories')) {
    /**
     * Get all ticket categories from the CRM (vtiger) merged with config defaults.
     * Cached for 5 minutes to avoid repeated DB queries.
     */
    function ticket_categories(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('ticket_categories_from_crm', 300, function () {
            try {
                return app(\App\Services\CrmService::class)->getTicketCategoriesFromCrm();
            } catch (\Throwable $e) {
                $base = config('tickets.categories', []);
                $custom = \App\Models\CrmSetting::tableExists()
                    ? \App\Models\CrmSetting::parsedLines(\App\Models\CrmSetting::get('ticket_categories_custom'))
                    : [];

                return array_values(array_unique(array_merge($base, $custom)));
            }
        });
    }
}

if (! function_exists('ticket_sources')) {
    /**
     * Get all ticket sources from the CRM (vtiger).
     * Cached for 5 minutes to avoid repeated DB queries.
     */
    function ticket_sources(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('ticket_sources_from_crm', 300, function () {
            try {
                return app(\App\Services\CrmService::class)->getTicketSourcesFromCrm();
            } catch (\Throwable $e) {
                $base = config('tickets.sources', ['CRM', 'Email', 'Web', 'Phone']);
                $custom = \App\Models\CrmSetting::tableExists()
                    ? \App\Models\CrmSetting::parsedLines(\App\Models\CrmSetting::get('ticket_sources_custom'))
                    : [];

                return array_values(array_unique(array_merge($base, $custom)));
            }
        });
    }
}

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

if (! function_exists('contact_can_access')) {
    /**
     * Check if current user can access a contact by ID. Admins: yes. Contact owner or group: yes.
     * Also allow if user has tickets assigned to them for this contact.
     */
    function contact_can_access(int $contactId): bool
    {
        $user = \Illuminate\Support\Facades\Auth::guard('vtiger')->user()
            ?? \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return false;
        }
        try {
            if (method_exists($user, 'isAdministrator') && $user->isAdministrator()) {
                return true;
            }
        } catch (\Throwable $e) {
            // isAdministrator may throw – treat as non-admin
        }
        $userId = (int) ($user->id ?? $user->getAuthIdentifier() ?? $user->getKey() ?? 0);
        if ($userId <= 0) {
            return false;
        }
        try {
            $db = \Illuminate\Support\Facades\DB::connection('vtiger');
            $ownerId = $db->table('vtiger_crmentity')
                ->where('crmid', $contactId)
                ->value('smownerid');
            if ($ownerId !== null) {
                if ((int) $ownerId == $userId) {
                    return true;
                }
                try {
                    if ((int) $ownerId > 0 && $db->table('vtiger_user2group')
                        ->where('userid', $userId)
                        ->where('groupid', (int) $ownerId)
                        ->exists()) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // vtiger_user2group may not exist
                }
            }
            // User has tickets assigned to them for this contact
            if ($db->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->where('t.contact_id', $contactId)
                ->where('e.deleted', 0)
                ->where('e.smownerid', $userId)
                ->exists()) {
                return true;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('contact_can_access failed: ' . $e->getMessage());
        }
        return false;
    }
}

if (! function_exists('crm_owner_filter')) {
    /**
     * For role-based data access: returns null if current user is Administrator (sees all),
     * otherwise returns the current user's ID so CRM queries filter by smownerid.
     */
    function crm_owner_filter(): ?int
    {
        $user = \Illuminate\Support\Facades\Auth::guard('vtiger')->user()
            ?? \Illuminate\Support\Facades\Auth::user();
        if (!$user || $user->isAdministrator()) {
            return null;
        }
        return (int) ($user->id ?? $user->getAuthIdentifier());
    }
}

if (! function_exists('ticket_can_access')) {
    /**
     * Check if current user can access a ticket by ID.
     * Returns true – ticket routes are already behind auth:vtiger middleware.
     * No per-ticket permission check; all logged-in users can edit/reassign any ticket.
     */
    function ticket_can_access(int $ticketId): bool
    {
        return true;
    }
}

if (! function_exists('contact_can_access')) {
    /**
     * Check if current user can access a contact by ID. Admins: yes. Owner or group: yes.
     * Also allows if user has tickets assigned to them for this contact.
     */
    function contact_can_access(int $contactId): bool
    {
        if (config('tickets.allow_all_authenticated', false)) {
            return \Illuminate\Support\Facades\Auth::guard('vtiger')->check()
                || \Illuminate\Support\Facades\Auth::check();
        }
        $user = \Illuminate\Support\Facades\Auth::guard('vtiger')->user()
            ?? \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return false;
        }
        try {
            if (method_exists($user, 'isAdministrator') && $user->isAdministrator()) {
                return true;
            }
        } catch (\Throwable $e) {
            // isAdministrator may throw – treat as non-admin
        }
        $userId = (int) ($user->id ?? $user->getAuthIdentifier() ?? $user->getKey() ?? 0);
        if ($userId <= 0) {
            return false;
        }
        try {
            $db = \Illuminate\Support\Facades\DB::connection('vtiger');
            $ownerId = $db->table('vtiger_crmentity')
                ->where('crmid', $contactId)
                ->value('smownerid');
            if ($ownerId !== null && (int) $ownerId == $userId) {
                return true;
            }
            if ($ownerId !== null && (int) $ownerId > 0) {
                try {
                    if ($db->table('vtiger_user2group')
                        ->where('userid', $userId)
                        ->where('groupid', (int) $ownerId)
                        ->exists()) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // vtiger_user2group may not exist
                }
            }
            // Allow if user has tickets assigned to them for this contact
            $hasAssignedTicket = $db->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->where('t.contact_id', $contactId)
                ->where('e.deleted', 0)
                ->where('e.smownerid', $userId)
                ->exists();
            if ($hasAssignedTicket) {
                return true;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('contact_can_access failed: ' . $e->getMessage());
        }
        return false;
    }
}

if (! function_exists('contact_can_access')) {
    /**
     * Check if current user can access a contact by ID. Admins: yes. Contact owner or
     * user in owner group: yes. User has tickets assigned to them for this contact: yes.
     */
    function contact_can_access(int $contactId): bool
    {
        if (config('tickets.allow_all_authenticated', false)) {
            return \Illuminate\Support\Facades\Auth::guard('vtiger')->check()
                || \Illuminate\Support\Facades\Auth::check();
        }
        $user = \Illuminate\Support\Facades\Auth::guard('vtiger')->user()
            ?? \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return false;
        }
        try {
            if (method_exists($user, 'isAdministrator') && $user->isAdministrator()) {
                return true;
            }
        } catch (\Throwable $e) {
            // isAdministrator may throw – treat as non-admin
        }
        $userId = (int) ($user->id ?? $user->getAuthIdentifier() ?? $user->getKey() ?? 0);
        if ($userId <= 0) {
            return false;
        }
        try {
            $db = \Illuminate\Support\Facades\DB::connection('vtiger');
            $ownerId = $db->table('vtiger_crmentity')
                ->where('crmid', $contactId)
                ->whereIn('e.setype', ['Contacts', 'Contact'])
                ->value('smownerid');
        } catch (\Throwable $e) {
            $ownerId = \Illuminate\Support\Facades\DB::connection('vtiger')
                ->table('vtiger_crmentity')
                ->where('crmid', $contactId)
                ->value('smownerid');
        }
        if ($ownerId !== null && (int) $ownerId == $userId) {
            return true;
        }
        if ($ownerId !== null && (int) $ownerId > 0) {
            try {
                if ($db->table('vtiger_user2group')
                    ->where('userid', $userId)
                    ->where('groupid', (int) $ownerId)
                    ->exists()) {
                    return true;
                }
            } catch (\Throwable $e) {
                // vtiger_user2group may not exist
            }
        }
        // User has tickets assigned to them for this contact
        try {
            $db = \Illuminate\Support\Facades\DB::connection('vtiger');
            if ($db->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->where('t.contact_id', $contactId)
                ->where('e.deleted', 0)
                ->where('e.smownerid', $userId)
                ->exists()) {
                return true;
            }
        } catch (\Throwable $e) {
            // Ignore
        }
        return false;
    }
}

if (! function_exists('contact_can_access')) {
    /**
     * Check if current user can access a contact by ID.
     * Admins: yes. Contact owner or group: yes. User has tickets for this contact: yes.
     */
    function contact_can_access(int $contactId): bool
    {
        if (config('tickets.allow_all_authenticated', false)) {
            return \Illuminate\Support\Facades\Auth::guard('vtiger')->check()
                || \Illuminate\Support\Facades\Auth::check();
        }
        $user = \Illuminate\Support\Facades\Auth::guard('vtiger')->user()
            ?? \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return false;
        }
        try {
            if (method_exists($user, 'isAdministrator') && $user->isAdministrator()) {
                return true;
            }
        } catch (\Throwable $e) {
            // isAdministrator may throw
        }
        $userId = (int) ($user->id ?? $user->getAuthIdentifier() ?? $user->getKey() ?? 0);
        if ($userId <= 0) {
            return false;
        }
        try {
            $db = \Illuminate\Support\Facades\DB::connection('vtiger');
            $ownerId = $db->table('vtiger_crmentity')
                ->where('crmid', $contactId)
                ->value('smownerid');
            if ($ownerId !== null && (int) $ownerId == $userId) {
                return true;
            }
            if ($ownerId !== null && (int) $ownerId > 0) {
                try {
                    if ($db->table('vtiger_user2group')
                        ->where('userid', $userId)
                        ->where('groupid', (int) $ownerId)
                        ->exists()) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // vtiger_user2group may not exist
                }
            }
            $hasAssignedTicket = $db->table('vtiger_troubletickets as t')
                ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                ->where('t.contact_id', $contactId)
                ->where('e.deleted', 0)
                ->where('e.smownerid', $userId)
                ->exists();
            if ($hasAssignedTicket) {
                return true;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('contact_can_access failed: ' . $e->getMessage());
        }
        return false;
    }
}

if (! function_exists('crm_user_can_access_record')) {
    /**
     * Check if current user can access a CRM record (Contact, Lead, Deal, Ticket).
     * Administrators see all. Others ONLY records assigned to them (smownerid).
     * For tickets: assignee can view, comment, update, close, add activities.
     */
    function crm_user_can_access_record($record): bool
    {
        $user = \Illuminate\Support\Facades\Auth::guard('vtiger')->user()
            ?? \Illuminate\Support\Facades\Auth::user();
        if (!$user || $user->isAdministrator()) {
            return true;
        }
        $userId = (int) ($user->id ?? $user->getAuthIdentifier() ?? 0);

        // Get crmid for DB lookup (Ticket=ticketid, Contact=contactid, Lead=leadid, Deal=potentialid)
        $crmid = null;
        if (is_object($record)) {
            $crmid = $record->ticketid ?? $record->contactid ?? $record->leadid ?? $record->potentialid ?? $record->crmid ?? (method_exists($record, 'getKey') ? $record->getKey() : null) ?? $record->id ?? null;
        } elseif (is_array($record)) {
            $crmid = $record['ticketid'] ?? $record['contactid'] ?? $record['leadid'] ?? $record['potentialid'] ?? $record['crmid'] ?? $record['id'] ?? null;
        }

        // TICKETS: use same visibility logic as ticket list – if it would show in their list, allow
        $isTicket = is_object($record) && ($record instanceof \App\Models\Ticket || isset($record->ticketid));
        $isTicket = $isTicket || (is_array($record) && isset($record['ticketid']));
        if ($crmid !== null && $isTicket && $userId > 0) {
            $crm = app(\App\Services\CrmService::class);
            if ($crm->ticketVisibleToUser((int) $crmid, $userId)) {
                return true;
            }
            // Group-assigned: ticket owned by group, user in group
            try {
                $ownerId = \Illuminate\Support\Facades\DB::connection('vtiger')
                    ->table('vtiger_crmentity')->where('crmid', $crmid)->value('smownerid');
                if ($ownerId !== null && (int) $ownerId > 0) {
                    $inGroup = \Illuminate\Support\Facades\DB::connection('vtiger')
                        ->table('vtiger_user2group')
                        ->where('userid', $userId)
                        ->where('groupid', (int) $ownerId)
                        ->exists();
                    if ($inGroup) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                // vtiger_user2group may not exist
            }
        }

        // Generic: fetch smownerid from vtiger_crmentity
        $ownerId = null;
        if ($crmid !== null) {
            try {
                $row = \Illuminate\Support\Facades\DB::connection('vtiger')
                    ->table('vtiger_crmentity')
                    ->where('crmid', $crmid)
                    ->value('smownerid');
                $ownerId = $row !== null ? (int) $row : null;
            } catch (\Throwable $e) {
                // Fallback
            }
        }

        // Fallback: model attributes
        if ($ownerId === null && is_object($record)) {
            $ownerId = $record->smownerid ?? (method_exists($record, 'getAttribute') ? $record->getAttribute('smownerid') : null);
            $ownerId = $ownerId !== null ? (int) $ownerId : null;
        } elseif ($ownerId === null && is_array($record)) {
            $raw = $record['smownerid'] ?? $record['attributes']['smownerid'] ?? null;
            $ownerId = $raw !== null ? (int) $raw : null;
        }

        if ($ownerId !== null && $ownerId === $userId) {
            return true;
        }

        // Group ownership
        if ($ownerId !== null && $ownerId > 0) {
            try {
                if (\Illuminate\Support\Facades\DB::connection('vtiger')
                    ->table('vtiger_user2group')
                    ->where('userid', $userId)
                    ->where('groupid', $ownerId)
                    ->exists()) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        // CONTACTS: allow if user has tickets assigned to them for this contact
        $isContact = is_object($record) && (isset($record->contactid) || $record instanceof \App\Models\Contact);
        $isContact = $isContact || (is_array($record) && isset($record['contactid']));
        if ($crmid !== null && $isContact && $userId > 0) {
            try {
                $hasAssignedTicket = \Illuminate\Support\Facades\DB::connection('vtiger')
                    ->table('vtiger_troubletickets as t')
                    ->join('vtiger_crmentity as e', 't.ticketid', '=', 'e.crmid')
                    ->where('t.contact_id', $crmid)
                    ->where('e.deleted', 0)
                    ->where('e.smownerid', $userId)
                    ->exists();
                if ($hasAssignedTicket) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        return false;
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
