<?php
/**
 * Copy to config.php and edit.
 * FEEDBACK_CRM_API_URL: Internal URL of the CRM (e.g. http://10.1.1.65)
 *   The standalone app calls this server-to-server to validate links and submit feedback.
 */
return [
    'crm_api_url' => getenv('FEEDBACK_CRM_API_URL') ?: 'http://10.1.1.65',
    'app_name' => 'Geminia Life Insurance',
];
