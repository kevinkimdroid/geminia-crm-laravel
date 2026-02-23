# Geminia CRM → Laravel 11 Migration Plan

## Overview
This document outlines the phased migration of Geminia CRM (Vtiger-based) to Laravel 11 while **preserving all existing features**.

## Current Stack
- **Framework**: Vtiger CRM (custom PHP, Smarty templates)
- **Database**: MySQL (vtiger schema)
- **Auth**: Session-based, vtiger_users table, MD5/crypt passwords
- **Modules**: Home, Contacts, Leads, HelpDesk, Calendar, Reports, etc.

## Target Stack
- **Framework**: Laravel 11 (LTS, stable)
- **Database**: Same MySQL database (vtiger) - no schema change initially
- **Auth**: Laravel Auth + vtiger_users adapter
- **Views**: Blade (migrate from Smarty incrementally)

---

## Phase 1: Foundation (Current)
- [x] Create Laravel 11 project
- [x] Configure vtiger database connection
- [x] Create migration plan
- [x] User model mapping to vtiger_users (VtigerUser)
- [x] Auth adapter (vtiger password compatibility)
- [x] Login/Logout
- [x] Dashboard (basic, with real data)

## Phase 2: Core Modules
- [x] Contacts (List, Detail, Edit, Create)
- [x] Leads (List, Detail, Edit, Create)
- [x] HelpDesk/Tickets (List, Detail, Edit, Create)
- [ ] Calendar/Events
- [x] Module navigation & app menu

## Phase 3: Extended Modules
- [x] Potentials/Opportunities (Deals)
- [ ] Products, Quotes, Sales Order, Invoice
- [ ] Reports
- [ ] Documents
- [ ] Email integration

## Phase 4: Settings & Admin
- [ ] User management
- [ ] Roles & Profiles
- [ ] Picklists
- [ ] Workflows
- [ ] Cron tasks

## Phase 5: Dashboard & Widgets
- [ ] Dashboard tabs
- [ ] All widget types (MiniList, Chart, etc.)
- [ ] Customizable layout

## Phase 6: Cleanup
- [ ] Remove legacy code
- [ ] Full Blade conversion
- [ ] API layer (optional)

---

## Database Strategy
**Use existing vtiger database** - no migration of data. Laravel models will map to vtiger_* tables. This ensures:
- Zero data loss
- Ability to run both systems in parallel during migration
- Rollback safety

## Feature Parity Checklist
| Feature | Status |
|---------|--------|
| Login/Logout | Done |
| Dashboard | Done (real data from Vtiger) |
| List views | Done (Contacts, Leads, Tickets, Deals) |
| Detail views | Done |
| Create/Edit | Done |
| Quick Create | Pending |
| Search | Pending |
| Filters/Custom views | Pending |
| Related lists | Pending |
| Import/Export | Pending |
| Reports | Pending |
| Calendar | Pending |
| Documents | Pending |
| Email | Pending |
| Settings | Pending |

---

## Running Both Systems
- **Legacy**: `http://localhost:8000/` (or XAMPP)
- **Laravel**: `http://localhost:8001/` (or different port)
- Same database - changes in one reflect in the other
