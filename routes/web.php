<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/password/forgot', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/password/forgot', [AuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/password/reset', [AuthController::class, 'showResetForm'])->name('password.reset');
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('password.update');

Route::get('/feedback', [\App\Http\Controllers\FeedbackController::class, 'form'])->name('feedback.form');
Route::post('/feedback', [\App\Http\Controllers\FeedbackController::class, 'form']);
Route::get('/feedback/thank-you', [\App\Http\Controllers\FeedbackController::class, 'thankYou'])->name('feedback.thank-you');

Route::get('/api/feedback/validate', [\App\Http\Controllers\Api\FeedbackApiController::class, 'validate'])->name('api.feedback.validate');
Route::post('/api/feedback/submit', [\App\Http\Controllers\Api\FeedbackApiController::class, 'submit'])->name('api.feedback.submit');

Route::match(['get', 'post'], '/webhooks/social/meta', [\App\Http\Controllers\SocialMediaWebhookController::class, 'meta'])
    ->middleware('throttle:120,1')
    ->name('webhooks.social.meta');
Route::post('/webhooks/social/ingest', [\App\Http\Controllers\SocialMediaWebhookController::class, 'ingest'])
    ->middleware('throttle:60,1')
    ->name('webhooks.social.ingest');

Route::any('/crm-client-feedback', function () {
    ob_start();
    require base_path('crm-client-feedback/index.php');
    return response(ob_get_clean() ?: '')->header('Content-Type', 'text/html; charset=utf-8');
});

Route::get('/api/erp/clients', [\App\Http\Controllers\Api\ErpClientController::class, 'index'])
    ->middleware(['erp.api.token', 'throttle:60,1'])
    ->name('api.erp.clients');
Route::post('/api/admin/erp-clients-import', [\App\Http\Controllers\Api\ErpClientsImportController::class, 'store'])
    ->middleware(['erp.sync.token', 'throttle:10,1'])
    ->name('api.admin.erp-clients-import');

Route::middleware('auth:vtiger')->group(function () {
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/api/dashboard/clients-count', [DashboardController::class, 'clientsCount'])->name('api.dashboard.clients-count');
Route::get('/search', [\App\Http\Controllers\SearchController::class, 'index'])->name('search');
Route::redirect('/welcome', '/');
Route::redirect('/index.php', '/');

Route::get('/contacts', [\App\Http\Controllers\CustomerController::class, 'index'])->name('contacts.index');
Route::resource('contacts', ContactController::class)->except(['index']);
Route::post('contacts/{contact}/followup', [ContactController::class, 'storeFollowup'])->name('contacts.followup.store');
Route::post('contacts/{contact}/campaigns', [ContactController::class, 'addToCampaign'])->name('contacts.campaigns.add');
Route::delete('contacts/{contact}/campaigns/{campaign}', [ContactController::class, 'removeFromCampaign'])->name('contacts.campaigns.remove');
Route::resource('leads', LeadController::class);
Route::get('/tickets/export', [TicketController::class, 'export'])->name('tickets.export');
Route::get('/tickets/{ticket}/close', [TicketController::class, 'showCloseForm'])->name('tickets.close.form');
Route::post('/tickets/{ticket}/close', [TicketController::class, 'quickClose'])->name('tickets.close');
Route::post('/tickets/{ticket}/inactivate', [TicketController::class, 'inactivate'])->name('tickets.inactivate');
Route::post('/tickets/{ticket}/reassign', [TicketController::class, 'reassign'])->name('tickets.reassign');
Route::post('/tickets/{ticket}/comments', [TicketController::class, 'storeComment'])->name('tickets.comments.store');
Route::resource('tickets', TicketController::class)->except(['destroy']);
Route::get('/api/tickets/contacts', [TicketController::class, 'searchContacts'])->name('api.tickets.contacts');
Route::get('/api/tickets/contact/{contact}/policy', [TicketController::class, 'contactPolicy'])->name('api.tickets.contact.policy');
Route::get('/api/tickets/products', [TicketController::class, 'searchProducts'])->name('api.tickets.products');
Route::get('/api/tickets/accounts', [TicketController::class, 'searchAccounts'])->name('api.tickets.accounts');
Route::resource('deals', DealController::class);
Route::get('/activities', [\App\Http\Controllers\ActivityController::class, 'index'])->name('activities.index');
Route::get('/activities/create', [\App\Http\Controllers\ActivityController::class, 'create'])->name('activities.create');
Route::post('/activities', [\App\Http\Controllers\ActivityController::class, 'store'])->name('activities.store');
Route::get('/api/contacts/{contact}/tickets', [\App\Http\Controllers\ActivityController::class, 'ticketsForContact'])->name('api.contacts.tickets');
Route::get('/work-tickets', [\App\Http\Controllers\WorkTicketController::class, 'index'])->name('work-tickets.index');
Route::get('/work-tickets/create', [\App\Http\Controllers\WorkTicketController::class, 'create'])->name('work-tickets.create');
Route::post('/work-tickets', [\App\Http\Controllers\WorkTicketController::class, 'store'])->name('work-tickets.store');
Route::get('/work-tickets/{workTicket}', [\App\Http\Controllers\WorkTicketController::class, 'show'])->name('work-tickets.show');
Route::post('/work-tickets/{workTicket}/updates', [\App\Http\Controllers\WorkTicketController::class, 'storeUpdate'])->name('work-tickets.updates.store');

Route::get('/marketing', fn () => view('marketing'))->name('marketing');
Route::get('/marketing/social-media', [\App\Http\Controllers\SocialMediaController::class, 'index'])->name('marketing.social-media');
Route::post('/marketing/social-media/schedule', [\App\Http\Controllers\SocialMediaController::class, 'schedule'])->name('marketing.social-media.schedule');
Route::get('/marketing/social-media/create-lead/{id}', [\App\Http\Controllers\SocialMediaController::class, 'createLeadFromInteraction'])->name('marketing.social-media.create-lead');
Route::post('/marketing/social-media/schedule/{id}/cancel', [\App\Http\Controllers\SocialMediaController::class, 'cancelSchedule'])->name('marketing.social-media.schedule.cancel');
Route::get('/social-auth/{platform}/redirect', [SocialAuthController::class, 'redirect'])->name('social-auth.redirect');
Route::get('/social-auth/{platform}/callback', [SocialAuthController::class, 'callback'])->name('social-auth.callback');
Route::post('/social-auth/{platform}/disconnect', [SocialAuthController::class, 'disconnect'])->name('social-auth.disconnect');
Route::prefix('marketing')->name('marketing.')->group(function () {
    Route::get('broadcast', [\App\Http\Controllers\MassBroadcastController::class, 'index'])->name('broadcast');
    Route::post('broadcast/send', [\App\Http\Controllers\MassBroadcastController::class, 'send'])->name('broadcast.send');
    Route::resource('campaigns', \App\Http\Controllers\CampaignController::class)->parameters(['campaigns' => 'campaign']);
});
Route::get('/support', fn () => view('support', ['ticketCounts' => app(\App\Services\CrmService::class)->getTicketCountsByStatus()]))->name('support');
Route::get('/support/tickets', fn () => redirect()->route('tickets.index'))->name('support.tickets');
Route::get('/support/serve-client', [\App\Http\Controllers\ServeClientController::class, 'index'])->name('support.serve-client');
Route::get('/support/serve-client/search', [\App\Http\Controllers\ServeClientController::class, 'search'])->name('serve-client.search');
Route::post('/support/serve-client/create-ticket', [\App\Http\Controllers\ServeClientController::class, 'createTicket'])->name('serve-client.create-ticket');
Route::get('/support/faq', fn () => view('support.faq'))->name('support.faq');
Route::get('/support/maturities', [\App\Http\Controllers\MaturitiesController::class, 'index'])->name('support.maturities');
Route::get('/support/mortgage-renewals', [\App\Http\Controllers\MortgageRenewalController::class, 'index'])->name('support.mortgage-renewals');
Route::get('/support/mortgage-renewals/export', [\App\Http\Controllers\MortgageRenewalController::class, 'export'])->name('support.mortgage-renewals.export');
Route::post('/support/maturities/renewal-status', [\App\Http\Controllers\MaturitiesController::class, 'updateRenewalStatus'])->name('support.maturities.renewal-status');
Route::get('/support/maturities/discharge-voucher/pdf', [\App\Http\Controllers\MaturityDischargeVoucherController::class, 'pdf'])->name('support.maturities.discharge-voucher.pdf');
Route::post('/support/maturities/discharge-voucher/email', [\App\Http\Controllers\MaturityDischargeVoucherController::class, 'email'])->name('support.maturities.discharge-voucher.email');
Route::get('/support/maturities/export', [\App\Http\Controllers\MaturitiesController::class, 'export'])->name('support.maturities.export');
Route::get('/support/sms-notifier', [\App\Http\Controllers\SmsNotifierController::class, 'index'])->name('support.sms-notifier');
Route::post('/support/sms-notifier/send', [\App\Http\Controllers\SmsNotifierController::class, 'send'])->name('support.sms-notifier.send');
Route::get('/support/email-client', [\App\Http\Controllers\SupportClientEmailController::class, 'index'])->name('support.email-client');
Route::post('/support/email-client/send', [\App\Http\Controllers\SupportClientEmailController::class, 'send'])->name('support.email-client.send');
Route::get('/support/customers', [\App\Http\Controllers\CustomerController::class, 'index'])->name('support.customers');
Route::prefix('compliance')->name('compliance.')->group(function () {
    Route::get('complaints/export', [\App\Http\Controllers\ComplaintController::class, 'export'])->name('complaints.export');
    Route::resource('complaints', \App\Http\Controllers\ComplaintController::class)->parameters(['complaints' => 'complaint']);
});
Route::get('/api/support/clients', [\App\Http\Controllers\CustomerController::class, 'clientsApi'])->name('api.support.clients');
Route::get('/support/clients/show', [\App\Http\Controllers\CustomerController::class, 'show'])->name('support.clients.show');
Route::get('/support/clients/debug-api', [\App\Http\Controllers\CustomerController::class, 'debugApi'])->name('support.clients.debug-api');
Route::get('/support/clients/debug-products', [\App\Http\Controllers\CustomerController::class, 'debugProducts'])->name('support.clients.debug-products');
Route::get('/support/clients/create-ticket', [\App\Http\Controllers\ServeClientController::class, 'createTicketFromPolicy'])->name('support.clients.create-ticket');
Route::get('/tools', fn () => view('tools'))->name('tools');
Route::get('/tools/email-templates', [\App\Http\Controllers\EmailTemplateController::class, 'index'])->name('tools.email-templates');
Route::get('/tools/email-templates/create', [\App\Http\Controllers\EmailTemplateController::class, 'create'])->name('tools.email-templates.create');
Route::post('/tools/email-templates', [\App\Http\Controllers\EmailTemplateController::class, 'store'])->name('tools.email-templates.store');
Route::get('/tools/email-templates/{emailTemplate}/edit', [\App\Http\Controllers\EmailTemplateController::class, 'edit'])->name('tools.email-templates.edit');
Route::put('/tools/email-templates/{emailTemplate}', [\App\Http\Controllers\EmailTemplateController::class, 'update'])->name('tools.email-templates.update');
Route::delete('/tools/email-templates/{emailTemplate}', [\App\Http\Controllers\EmailTemplateController::class, 'destroy'])->name('tools.email-templates.destroy');
Route::get('/tools/recycle-bin', fn () => view('tools.recycle-bin'))->name('tools.recycle-bin');
Route::get('/tools/pbx-manager', [\App\Http\Controllers\PbxController::class, 'index'])->name('tools.pbx-manager');
Route::post('/tools/pbx-manager/fetch', [\App\Http\Controllers\PbxController::class, 'fetch'])->name('tools.pbx-manager.fetch');
Route::post('/tools/pbx-manager/make-call', [\App\Http\Controllers\PbxController::class, 'makeCall'])->name('tools.pbx-manager.make-call');
Route::get('/tools/pbx-manager/vtiger/{id}/recording', [\App\Http\Controllers\PbxController::class, 'recordingVtiger'])->name('tools.pbx-manager.recording.vtiger');
Route::get('/tools/pbx-manager/calls/{pbxCall}/recording', [\App\Http\Controllers\PbxController::class, 'recording'])->name('tools.pbx-manager.recording');
Route::post('/tools/pbx-manager/claim', [\App\Http\Controllers\PbxController::class, 'claim'])->name('tools.pbx-manager.claim');
Route::post('/tools/pbx-manager/claim-latest', [\App\Http\Controllers\PbxController::class, 'claimLatest'])->name('tools.pbx-manager.claim-latest');
Route::get('/tools/pdf-protect', [\App\Http\Controllers\PdfProtectController::class, 'index'])->name('tools.pdf-protect');
Route::post('/tools/pdf-protect', [\App\Http\Controllers\PdfProtectController::class, 'process'])->name('tools.pdf-protect.process');
Route::get('/tools/pdf-maker', [\App\Http\Controllers\PdfMakerController::class, 'index'])->name('tools.pdf-maker');
Route::get('/tools/pdf-maker/{module}/create', [\App\Http\Controllers\PdfMakerController::class, 'create'])->name('tools.pdf-maker.create');
Route::get('/tools/pdf-maker/{module}/template', [\App\Http\Controllers\PdfMakerController::class, 'template'])->name('tools.pdf-maker.template');
Route::post('/tools/pdf-maker/{module}/template', [\App\Http\Controllers\PdfMakerController::class, 'storeTemplate'])->name('tools.pdf-maker.template.store');
Route::get('/tools/pdf-maker/{module}/preview', [\App\Http\Controllers\PdfMakerController::class, 'preview'])->name('tools.pdf-maker.preview');
Route::post('/tools/pdf-maker/{module}/logo/remove', [\App\Http\Controllers\PdfMakerController::class, 'removeLogo'])->name('tools.pdf-maker.logo.remove');
Route::get('/tools/mail-manager', [\App\Http\Controllers\MailManagerController::class, 'index'])->name('tools.mail-manager');
Route::get('/tools/mail-manager/create', [\App\Http\Controllers\MailManagerController::class, 'create'])->name('tools.mail-manager.create');
Route::post('/tools/mail-manager', [\App\Http\Controllers\MailManagerController::class, 'store'])->name('tools.mail-manager.store');
Route::post('/tools/mail-manager/fetch', [\App\Http\Controllers\MailManagerController::class, 'fetch'])->name('tools.mail-manager.fetch');
Route::get('/tools/mail-manager/{id}/create-ticket', [\App\Http\Controllers\MailManagerController::class, 'createTicketFromEmail'])->name('tools.mail-manager.create-ticket');
Route::get('/tools/mail-manager/{id}', [\App\Http\Controllers\MailManagerController::class, 'show'])->name('tools.mail-manager.show');
Route::get('/reports', [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports');

Route::middleware('admin')->group(function () {
Route::get('/settings', fn () => redirect()->route('settings.crm'))->name('settings');
Route::get('/work-tickets/reporting-lines', [\App\Http\Controllers\WorkTicketController::class, 'reportingLines'])->name('work-tickets.reporting-lines');
Route::post('/work-tickets/reporting-lines', [\App\Http\Controllers\WorkTicketController::class, 'saveReportingLines'])->name('work-tickets.reporting-lines.save');
Route::get('/settings/crm', [\App\Http\Controllers\SettingsController::class, 'crm'])->name('settings.crm');
Route::post('/settings/users/{user}/send-reset-link', [\App\Http\Controllers\UserManagementController::class, 'sendResetLink'])->name('settings.users.send-reset-link');
Route::get('/settings/users/create', [\App\Http\Controllers\UserManagementController::class, 'create'])->name('settings.users.create');
Route::post('/settings/users', [\App\Http\Controllers\UserManagementController::class, 'store'])->name('settings.users.store');
Route::get('/settings/users/{user}/edit', [\App\Http\Controllers\UserManagementController::class, 'edit'])->name('settings.users.edit');
Route::put('/settings/users/{user}', [\App\Http\Controllers\UserManagementController::class, 'update'])->name('settings.users.update');
Route::put('/settings/users/{user}/department', [\App\Http\Controllers\UserManagementController::class, 'updateDepartment'])->name('settings.users.update-department');
Route::get('/settings/users/{user}/offboard', [\App\Http\Controllers\UserManagementController::class, 'offboard'])->name('settings.users.offboard');
Route::post('/settings/users/{user}/offboard', [\App\Http\Controllers\UserManagementController::class, 'offboardSubmit'])->name('settings.users.offboard.submit');
Route::post('/settings/users/{user}/reactivate', [\App\Http\Controllers\UserManagementController::class, 'reactivate'])->name('settings.users.reactivate');
Route::post('/settings/departments', [\App\Http\Controllers\DepartmentController::class, 'store'])->name('settings.departments.store');
Route::put('/settings/departments/{department}', [\App\Http\Controllers\DepartmentController::class, 'update'])->name('settings.departments.update');
Route::delete('/settings/departments/{department}', [\App\Http\Controllers\DepartmentController::class, 'destroy'])->name('settings.departments.destroy');
Route::delete('/settings/users/{user}', [\App\Http\Controllers\UserManagementController::class, 'destroy'])->name('settings.users.destroy');
Route::post('/settings/crm/groups', [\App\Http\Controllers\GroupsController::class, 'store'])->name('settings.groups.store');
Route::put('/settings/crm/groups/{id}', [\App\Http\Controllers\GroupsController::class, 'update'])->name('settings.groups.update');
Route::delete('/settings/crm/groups/{id}', [\App\Http\Controllers\GroupsController::class, 'destroy'])->name('settings.groups.destroy');
Route::post('/settings/crm/ticket-automation', [\App\Http\Controllers\TicketAutomationController::class, 'store'])->name('settings.ticket-automation.store');
Route::put('/settings/crm/ticket-automation/{id}', [\App\Http\Controllers\TicketAutomationController::class, 'update'])->name('settings.ticket-automation.update');
Route::delete('/settings/crm/ticket-automation/{id}', [\App\Http\Controllers\TicketAutomationController::class, 'destroy'])->name('settings.ticket-automation.destroy');
Route::post('/settings/crm/pbx-extension-mapping', [\App\Http\Controllers\PbxExtensionMappingController::class, 'store'])->name('settings.pbx-extension-mapping.store');
Route::post('/settings/crm/pbx-extension-mapping/sync', [\App\Http\Controllers\PbxExtensionMappingController::class, 'sync'])->name('settings.pbx-extension-mapping.sync');
Route::delete('/settings/crm/pbx-extension-mapping/{mapping}', [\App\Http\Controllers\PbxExtensionMappingController::class, 'destroy'])->name('settings.pbx-extension-mapping.destroy');
Route::post('/settings/crm/pbx-extension-mapping', [\App\Http\Controllers\PbxExtensionMappingController::class, 'store'])->name('settings.pbx-extension-mapping.store');
Route::post('/settings/crm/pbx-extension-mapping/sync', [\App\Http\Controllers\PbxExtensionMappingController::class, 'sync'])->name('settings.pbx-extension-mapping.sync');
Route::delete('/settings/crm/pbx-extension-mapping/{mapping}', [\App\Http\Controllers\PbxExtensionMappingController::class, 'destroy'])->name('settings.pbx-extension-mapping.destroy');
Route::post('/settings/crm/pbx-extension-mapping', [\App\Http\Controllers\PbxExtensionMappingController::class, 'store'])->name('settings.pbx-extension-mapping.store');
Route::post('/settings/crm/pbx-extension-mapping/sync', [\App\Http\Controllers\PbxExtensionMappingController::class, 'sync'])->name('settings.pbx-extension-mapping.sync');
Route::delete('/settings/crm/pbx-extension-mapping/{mapping}', [\App\Http\Controllers\PbxExtensionMappingController::class, 'destroy'])->name('settings.pbx-extension-mapping.destroy');
Route::post('/settings/crm/ticket-sla/roles', [\App\Http\Controllers\TicketSlaController::class, 'updateRoles'])->name('settings.ticket-sla.update-roles');
Route::post('/settings/crm/ticket-sla/departments', [\App\Http\Controllers\TicketSlaController::class, 'addDepartmentTat'])->name('settings.ticket-sla.add-department');
Route::post('/settings/crm/ticket-sla/sync-categories', [\App\Http\Controllers\TicketSlaController::class, 'syncFromCategories'])->name('settings.ticket-sla.sync-categories');
Route::post('/settings/crm/ticket-dropdowns', [\App\Http\Controllers\TicketDropdownSettingsController::class, 'update'])->name('settings.ticket-dropdowns.update');
Route::post('/settings/crm/ticket-sla/import', [\App\Http\Controllers\TicketSlaController::class, 'importFromExcel'])->name('settings.ticket-sla.import');
Route::post('/settings/crm/ticket-sla/departments/update', [\App\Http\Controllers\TicketSlaController::class, 'updateDepartmentTat'])->name('settings.ticket-sla.update-department');
Route::delete('/settings/crm/ticket-sla/departments/{department}', [\App\Http\Controllers\TicketSlaController::class, 'deleteDepartmentTat'])->name('settings.ticket-sla.delete-department');
Route::post('/settings/modules/toggle', [\App\Http\Controllers\ModuleController::class, 'toggle'])->name('settings.modules.toggle');
Route::get('/settings/layout-editor', [\App\Http\Controllers\LayoutEditorController::class, 'index'])->name('settings.layout-editor');
});
Route::get('/settings/layout-editor/module/{tabid}', [\App\Http\Controllers\LayoutEditorController::class, 'show'])->name('settings.layout-editor.show')->where('tabid', '[0-9]+');
Route::post('/settings/layout-editor/field', [\App\Http\Controllers\LayoutEditorController::class, 'updateField'])->name('settings.layout-editor.field.update');
Route::get('/reports/sla-broken', [\App\Http\Controllers\ReportsController::class, 'slaBroken'])->name('reports.sla-broken');
Route::get('/reports/ticket-aging', [\App\Http\Controllers\ReportsController::class, 'ticketAging'])->name('reports.ticket-aging');
Route::get('/reports/tickets-by-date', [\App\Http\Controllers\ReportsController::class, 'ticketsByDate'])->name('reports.tickets-by-date');
Route::get('/reports/management-usage', [\App\Http\Controllers\ReportsController::class, 'managementUsage'])->name('reports.management-usage');
Route::get('/reports/assignment-handlers', [\App\Http\Controllers\ReportsController::class, 'assignmentHandlers'])->name('reports.assignment-handlers');
Route::get('/reports/contacts-summary', [\App\Http\Controllers\ReportsController::class, 'contactsSummary'])->name('reports.contacts-summary');
Route::get('/reports/calls-summary', [\App\Http\Controllers\ReportsController::class, 'callsSummary'])->name('reports.calls-summary');
Route::get('/reports/reassignment-audit', [\App\Http\Controllers\ReportsController::class, 'reassignmentAudit'])->name('reports.reassignment-audit');
Route::get('/reports/bounced-emails', [\App\Http\Controllers\ReportsController::class, 'bouncedEmailsReport'])->name('reports.bounced-emails');
Route::get('/reports/export/management-usage', [\App\Http\Controllers\ReportsController::class, 'exportManagementUsage'])->name('reports.export.management-usage');
Route::get('/reports/export/reassignment-audit', [\App\Http\Controllers\ReportsController::class, 'exportReassignmentAudit'])->name('reports.export.reassignment-audit');
Route::get('/reports/export/assignment-handlers', [\App\Http\Controllers\ReportsController::class, 'exportAssignmentHandlers'])->name('reports.export.assignment-handlers');
Route::get('/reports/export/sla-broken', [\App\Http\Controllers\ReportsController::class, 'exportSlaBroken'])->name('reports.export.sla-broken');
Route::get('/reports/export/ticket-aging', [\App\Http\Controllers\ReportsController::class, 'exportTicketAging'])->name('reports.export.ticket-aging');
Route::get('/reports/export/tickets-by-date', [\App\Http\Controllers\ReportsController::class, 'exportTicketsByDate'])->name('reports.export.tickets-by-date');
Route::get('/reports/export/sales-by-person', [\App\Http\Controllers\ReportsController::class, 'exportSalesByPerson'])->name('reports.export.sales-by-person');
Route::get('/reports/export/pipeline-by-stage', [\App\Http\Controllers\ReportsController::class, 'exportPipelineByStage'])->name('reports.export.pipeline-by-stage');
Route::get('/reports/export/all-excel', [\App\Http\Controllers\ReportsController::class, 'exportAllExcel'])->name('reports.export.all-excel');
Route::get('/settings/profiles', [\App\Http\Controllers\ProfileController::class, 'index'])->name('profiles.index');
Route::get('/settings/profiles/{profile}', [\App\Http\Controllers\ProfileController::class, 'show'])->name('profiles.show');
Route::put('/settings/profiles/{profile}', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profiles.update');
Route::post('/settings/users/{user}/send-reset-link', [\App\Http\Controllers\UserManagementController::class, 'sendResetLink'])->name('settings.users.send-reset-link');
Route::get('/settings/users/{user}/edit', [\App\Http\Controllers\UserManagementController::class, 'edit'])->name('settings.users.edit');
Route::put('/settings/profiles/{profile}', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profiles.update');

Route::middleware('admin')->prefix('setup')->name('setup.')->group(function () {
    Route::get('/', [SetupController::class, 'index'])->name('index');
    Route::get('/users', [SetupController::class, 'users'])->name('users');
    Route::post('/users/role', [SetupController::class, 'updateUserRole'])->name('users.update-role');
    Route::get('/roles', [SetupController::class, 'roles'])->name('roles');
    Route::get('/roles/{roleId}/modules', [SetupController::class, 'editRoleModules'])->name('roles.modules');
    Route::post('/roles/{roleId}/modules', [SetupController::class, 'updateRoleModules'])->name('roles.modules.update');
});
}); // auth middleware group
