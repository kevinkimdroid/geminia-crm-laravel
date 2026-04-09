Geminia CRM (Laravel) — Overview for stakeholders

================================================================================
USING THIS TEXT IN MICROSOFT WORD (EASIEST WAYS)
================================================================================

Option A — Open the HTML version in Word (recommended)
  1. In File Explorer, open: docs\GEMINIA-CRM-FOR-WORD.html
  2. Right-click → Open with → Microsoft Word
  3. Edit freely, then File → Save As → Word Document (.docx)

Option B — Copy from this file
  1. Select all text in this file (Ctrl+A), copy (Ctrl+C)
  2. Paste into a blank Word document (Ctrl+V)
  3. In Word, select each main heading and apply Heading 1 (or your house style)
  4. For any “table” below, Word may paste as text; use Insert → Table → Convert Text to Table if needed, or retype the few rows from the HTML file

Option C — Print or PDF
  Use the HTML file in Word, then File → Print or Save as PDF

================================================================================
WHAT THIS DOCUMENT IS
================================================================================

Plain-language summary of how the new Laravel CRM compares to the old Vtiger-based CRM: what is better, what is new, and what is not finished yet. No special formatting required to read it.

Project: Geminia CRM (Laravel application)
Compared to: Legacy Geminia CRM (Vtiger: PHP, Smarty, vtiger MySQL database)


================================================================================
1. EXECUTIVE SUMMARY
================================================================================

The new CRM replaces the older Vtiger-based system with Laravel 11 and Blade templates. It still uses the SAME MySQL vtiger database for core CRM records. That means no duplicate customer data and the old and new systems can run in parallel during migration.

Where the new system is clearly stronger
  • Modern codebase, easier to maintain and extend over the years
  • Stronger web security practices (for example CSRF protection, structured login, password reset by email)
  • Clear deployment steps (Composer, frontend build, database migrations)
  • Many business-specific features that normal Vtiger does not ship with (ERP-linked clients, policy maturities, marketing and social tools, phone system integration, complaints, ticket SLA tooling)

Where the old system may still do MORE
  • Full calendar as in Vtiger, products, quotes, sales orders, invoices in Laravel
  • Deep Vtiger-only items unless rebuilt here: workflows, advanced custom views, related lists everywhere, full document and email module parity, legacy dashboard widgets


================================================================================
2. TECHNICAL COMPARISON (LEGACY VS NEW)
================================================================================

Framework
  Legacy: Vtiger and custom PHP, Smarty templates
  New:    Laravel 11, Blade templates

Database
  Legacy: MySQL vtiger schema
  New:    Same vtiger database for main CRM data, plus extra Laravel tables for new features

Users and login
  Legacy: vtiger_users table, older password formats
  New:    Laravel authentication with an adapter for existing vtiger users; login, logout, forgot password, reset password

Templates
  Legacy: Smarty
  New:    Blade

How updates are deployed
  Legacy: Typical Vtiger-style deployment
  New:    Composer dependencies, npm build step, Laravel migrations, caching of config and routes


================================================================================
3. CORE CRM CAPABILITIES ALREADY IN LARAVEL
================================================================================

These match familiar CRM modules and exist in the Laravel app today:

  • Authentication — login, logout, forgot password, reset password
  • Dashboard — with Vtiger-sourced data where applicable
  • Contacts — list, view, create, edit; follow-ups; campaigns
  • Leads — create, read, update, delete
  • Tickets (Help Desk) — full handling, export, close, reassign, comments, helper searches
  • Deals (Opportunities) — create, read, update, delete
  • Activities — create and list; supporting APIs
  • Search — global search
  • Support and customers — ERP-aware client views, “serve client” style flows
  • Reports — operational reports and Excel exports (SLA, aging, tickets by date, summaries, reassignment audit, and others)
  • Settings and admin — users, departments, groups, ticket automation, PBX mapping, ticket SLA, dropdowns, turning modules on/off, layout editor, roles setup (partial compared to full Vtiger admin)

Note: The file MIGRATION_PLAN.md in the project is an older checklist. Some items still marked “pending” there are already done in the Laravel app.


================================================================================
4. NEW CAPABILITIES (NOT STANDARD Vtiger OUT OF THE BOX)
================================================================================

ERP and Oracle
  Client cache table, sync using a Python script and/or Laravel command, connection to ERP over HTTP, bulk import API

Maturities (policies)
  Maturity cache, renewal tracking, mortgage renewals screens, discharge voucher PDF and email, exports

Marketing
  Campaigns, social post scheduling, social accounts and interactions, webhooks, social login connections

Compliance
  Complaints module with export; links from mail tools where applicable

Telephony (PBX)
  Call list, recordings, extension mapping, claim workflows

Mail
  Mail manager — fetch mail, create a ticket from an email

Documents and communication
  Email templates, configurable PDF maker, PDF password protection

SMS
  SMS sending screen and activity logging

Tickets (extra)
  SLA settings, automation rules, reassignment history, comments, customer feedback

Engagement
  Contact follow-ups; linking contacts to campaigns

Configuration
  Which modules appear, CRM settings, departments and user-department links

Public and APIs
  Customer feedback forms and feedback API endpoints

Further reading in the project: MIGRATION_PLAN.md, ERP_SETUP.md, scripts/README_SYNC.md, DEPLOYMENT.md, deploy/README.md


================================================================================
5. GAPS TO PLAN FOR (DO NOT ASSUME PARITY YET)
================================================================================

Until these are built in Laravel, the legacy Vtiger system may still be required for:

  • Full calendar and events experience as in Vtiger
  • Products, quotes, sales orders, invoices
  • Everything that depends on deep Vtiger features: quick-create everywhere, advanced filters and custom views, related lists on every screen, bulk import and export everywhere, full document library, full built-in email client, Vtiger-style workflows and scheduled jobs, Vtiger-style dashboard widgets

Use this list when talking to managers so nobody assumes “100% replaced” before it is true.


================================================================================
6. ONE SENTENCE FOR STAKEHOLDERS
================================================================================

The Laravel CRM modernizes the technical platform, adds major insurance and operations features, and shares one database with Vtiger; replacing every legacy screen and module is an ongoing phased project, not a single switch-over.

---
Document: Geminia CRM — Laravel. Content reflects the repository as of the last update to this file.
