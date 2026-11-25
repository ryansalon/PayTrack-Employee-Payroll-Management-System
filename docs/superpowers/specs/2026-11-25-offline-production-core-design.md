# PayTrack Offline-Ready & Accurate Core — Design Spec

**Date:** 2026-11-25  
**Target Environment:** LGU-Mahinog, Camiguin — single payroll clerk running offline on a local Windows PC (CodeIgniter 4 + PHP 8.1+ + local MySQL via XAMPP/Laragon)  
**Selected Approach:** Approach A — Offline-Ready & Accurate Core

---

## 1. Intent & Why

PayTrack will be used in real-world municipal payroll operations by a single office payroll clerk running **offline** on a local office PC. In a Philippine municipal LGU context, payroll is subject to COA audit, strict payday deadlines (15th/30th), and the Data Privacy Act (RA 10173).

Today, PayTrack already handles the core LGU-Mahinog employee, office, deduction, payroll, Excel export, payslip, and reporting loop—but it is not yet safe for live municipal use because:
- All frontend assets (Bootstrap, Font Awesome, Chart.js, Google Fonts) load from online CDNs, so modals, dropdowns, charts, and layout break immediately without internet.
- There is no in-app database backup/restore, leaving a single offline PC vulnerable to total data loss.
- Every payroll and deduction screen is locked to the current calendar month (`date('Y-m')`) with UTC timezone, and `deductions` holds only one mutable row per employee—so editing an employee's deductions next month silently alters historical payslips and quarterly/annual reports.
- Multiple calculation and UI mismatches exist across controllers and views (e.g., 6 newer deduction columns missing from forms/tables/payslips/dashboard, `refund_rata` dropped in import's `total_deductions`, broken Payroll search bar, hardcoded Mayor/Treasurer signatories, CSRF disabled, and unguarded destructive endpoints).

This design hardens PayTrack into a self-contained, 100% offline, COA-accurate payroll tool for one clerk.

---

## 2. Goals, Non-Goals & Anti-Goals

### Goals
1. **100% offline operation** with zero external network requests, `Asia/Manila` timezone, and a one-click Windows launcher.
2. **Data safety & recovery** via one-click in-app database Backup & Restore (portable `.sql` file for USB flash drive storage) plus CSRF and accidental-wipe guardrails.
3. **Historical financial accuracy** via selectable payroll periods (`YYYY-MM`), per-period deduction snapshots stored on `payroll_records`, period locking (`draft` → `locked`), and a single centralized `PayrollCalculator` used everywhere.
4. **Fast single-operator workflow** via batch "Process Office / Process All" payroll processing and an in-app LGU Signatories Settings page.
5. **Bug-free reconciliation** so every screen (Payroll table, Deduction form, Payslip, Print sheet, Excel export, Reports, Dashboard) displays and totals the exact same 19 deduction fields and net pay.
6. **Automated test coverage** (PHPUnit) verifying payroll math, snapshots, period locking, batch processing, import math, and backup/restore.

### Non-Goals
- Multi-department role/approval workflows (HR → Accounting → Treasury). Only one clerk operates the app; existing `admin` / `staff` roles are simply enforced cleanly where appropriate.
- Custom agency remittance templates (GSIS, Pag-IBIG, PhilHealth, BIR 1601-C/2316, LBP ATM payroll advice) — deferred until the clerk provides the municipality's exact required formats.
- Job Order (JO) daily-rate × days-worked payroll computation — keep existing JO employee registration working, defer custom JO computation until requested.
- Migrating away from MySQL to SQLite or bundling a custom desktop Electron/PHP executable.

### Anti-Goals
- **Silent historical mutation:** Changing an employee's salary, office, position, or deduction in a later month must *never* rewrite a previously processed/locked month's payslip or report.
- **Partial offline:** Leaving even one `<link>` or `<script>` pointing to an external CDN (which stalls offline browsers or breaks print windows).
- **Data-destructive migrations:** Schema changes must migrate existing `pms_db` data cleanly without dropping or wiping existing employee/deduction/payroll records.

---

## 3. Constraints

- **Stack:** PHP `^8.1`, CodeIgniter 4 (`system/`), MySQL (`pms_db` on `localhost:3306` via XAMPP/Laragon), PhpSpreadsheet `^5.6`.
- **Testing:** Existing PHPUnit config uses in-memory SQLite (`$tests` group in `app/Config/Database.php`), so migrations and model queries must remain compatible with both MySQL and SQLite (avoid MySQL-only SQL syntax in application queries where CodeIgniter Query Builder works).
- **Existing LGU formats:** Preserve the LGU-Mahinog 4-sub-row Excel workbook layout (`Payroll::export`), the 1/2 Crosswise landscape print layout (`app/Views/payroll/print.php`), and the Excel importer (`Employee::import`, including RATA-only sheets).

---

## 4. Approaches Considered

| Approach | Summary | Trade-offs |
|---|---|---|
| **A. Offline-Ready & Accurate Core *(Selected)*** | Keep CodeIgniter 4 + local MySQL (XAMPP/Laragon). Bundle all frontend assets locally, add USB Backup/Restore, period selector + locking + deduction snapshots, batch processing, LGU settings, security guardrails, bug fixes, and PHPUnit tests. | Fastest path to a trustworthy daily offline tool with zero guesswork; defers custom agency remittance sheets and JO daily-rate formulas until the clerk confirms exact formats. |
| **B. Full Offline LGU Suite** | Approach A + GSIS/Pag-IBIG/PhilHealth/BIR/LBP remittance exports + JO daily-rate workflow + full field-level audit log screen. | Larger scope and high risk of guessing agency formats wrong without seeing LGU-Mahinog's real templates first. |
| **C. Zero-Install Portable Folder (SQLite + Bundled PHP)** | Approach A + switch DB to single-file SQLite + bundle Windows PHP runtime. | Easiest fresh-PC copy, but adds runtime packaging/extension maintenance (`intl`, `mbstring`, `gd`, `zip`) when XAMPP/Laragon + MySQL already works with `pms_db`. |

---

## 5. Detailed Design

### 5.1 True Offline Assets, Timezone & Launcher
- **Local vendor assets under `public/assets/vendor/`:**
  - `bootstrap/bootstrap.min.css` and `bootstrap/bootstrap.bundle.min.js` (Bootstrap 5.3.0)
  - `fontawesome/css/all.min.css` and `fontawesome/webfonts/*` (Font Awesome 6.4.0)
  - `chartjs/chart.umd.min.js` (Chart.js 4.4.1)
  - Remove all `fonts.googleapis.com` links (`layout/main.php`, `auth/login.php`, `payroll/print.php`). Use a clean local system font stack fallback (`'IBM Plex Sans', 'Segoe UI', system-ui, -apple-system, sans-serif` and `'Consolas', 'Courier New', monospace`) — or bundle `IBM Plex Sans` woff2 locally if available, with zero external URLs anywhere in `app/Views/`.
- **Update all views referencing CDNs:**
  - `app/Views/layout/main.php`
  - `app/Views/auth/login.php`
  - `app/Views/dashboard/index.php`
  - `app/Views/payroll/print.php`
  - `app/Views/payslip/batch_print.php`
  - All asset URLs use `base_url('assets/vendor/...')`.
- **Timezone:** Set `$appTimezone = 'Asia/Manila'` in `app/Config/App.php` so `date('Y-m')` and printed dates always reflect Philippine Standard Time.
- **Offline Launcher (`Start-PayTrack.bat` in repo root):**
  - Checks if MySQL is running (or prompts the clerk to start XAMPP/Laragon MySQL if port 3306 isn't listening), starts `php spark serve --host 127.0.0.1 --port 8080` if not already listening on 8080, and opens `http://localhost:8080` in the default browser.
  - Binds to `127.0.0.1` by default so the offline app isn't exposed to outside networks.

### 5.2 Database Schema Changes (Additive Migration)
Add a new migration (e.g., `2026-11-25-000001_AddOfflineCoreTablesAndSnapshots.php`) that **never drops existing tables or data**:

1. **`app_settings` table**
   - `setting_key` (`VARCHAR(100)`, primary key)
   - `setting_value` (`TEXT`, nullable)
   - `updated_at` (`DATETIME`)
   - Seed defaults if missing:
     - `lgu_name`: `LGU-MAHINOG`
     - `municipality_label`: `Mahinog, Camiguin`
     - `mayor_name`: `REY LAWRENCE K. TAN`
     - `mayor_title`: `Municipal Mayor`
     - `treasurer_name`: `MARY LUSSEL S. PACTO`
     - `treasurer_title`: `Disbursing Officer`

2. **`payroll_periods` table**
   - `period` (`VARCHAR(7)`, primary key — format `YYYY-MM`)
   - `status` (`VARCHAR(20)`, default `'draft'` — values: `'draft'`, `'locked'`)
   - `locked_at` (`DATETIME`, nullable)
   - `locked_by` (`INT UNSIGNED`, nullable)
   - `notes` (`VARCHAR(255)`, nullable)
   - `created_at` (`DATETIME`), `updated_at` (`DATETIME`)

3. **Snapshot columns added to `payroll_records`**
   - `deduction_snapshot` (`TEXT`, nullable) — JSON object storing all 19 deduction fields at the time payroll was processed/imported for that period.
   - `salary_rate_snapshot` (`DECIMAL(12,2)`, nullable) — employee's monthly base rate at the time payroll was processed (distinct from `gross_pay`, which is total earnings = `salary_rate_snapshot + refund_rata`).
   - `employee_code_snapshot` (`VARCHAR(50)`, nullable) — employee's `employee_id` code (e.g., `EMP-2026-001`).
   - `full_name_snapshot` (`VARCHAR(150)`, nullable)
   - `office_id_snapshot` (`INT UNSIGNED`, nullable)
   - `office_name_snapshot` (`VARCHAR(150)`, nullable)
   - `position_snapshot` (`VARCHAR(150)`, nullable)
   - `atm_account_no_snapshot` (`VARCHAR(100)`, nullable)
   - **Backward compatibility:** When reading an older `payroll_records` row created before this migration where snapshot columns are `NULL`, fall back to the live `employees` / `offices` / `deductions` tables via `LEFT JOIN` and `COALESCE`. Once a period is processed or re-processed, all snapshot columns are populated and become the authoritative record for that period's payslips, print sheets, Excel exports, and reports.
   - **Uniqueness & indexes:** Before adding a unique index on `payroll_records(employee_id, payroll_period)`, deduplicate any accidental duplicate `(employee_id, payroll_period)` rows (keeping the highest `id`), then add the unique index. Add regular indexes on `deductions(employee_id)` and `employees(office_id)`.

### 5.3 Centralized Payroll Calculator (`App\Libraries\PayrollCalculator`)
Create a single pure-PHP library class `App\Libraries\PayrollCalculator` with zero duplication across controllers and views:
- Defines the canonical list of all **19 deduction fields**:
  - GSIS (5): `gsis_premium`, `gsis_policy`, `gsis_other`, `gsis_ouli`, `gsis_diff`
  - Pag-IBIG (3): `pagibig_premium`, `pagibig_loan`, `pagibig_mp2`
  - PhilHealth (2): `phic`, `phic_diff`
  - Banks/Coops (5): `bank_lbp`, `bank_other_payables`, `bank_mcc`, `bank_1stvb`, `bank_rbt`
  - Tax & Other (4): `withholding_tax`, `loans`, `government_cont`, `other_deduct`
- **Canonical `gross_pay` contract:**
  - `salary_rate` = monthly base rate (`0.00` for RATA-only vouchers).
  - `refund_rata` = refund / RATA / PERA / ACA / differential earnings.
  - `gross_pay = round($salaryRate + $refundRata, 2)` (total earnings). This guarantees `Report` (`GROSS PAY - DEDUCTIONS = NET PAYABLE`) always balances AND prevents `Payslip::individual` from double-counting RATA (`Basic Pay = salary_rate_snapshot`, `Refund/RATA = refund_rata`, `Total Earnings = gross_pay`).
- Methods:
  - `normalizeDeductions(array $raw, bool $allowSignedOther = false): array` — returns all 19 keys cast to `round((float), 2)`, stripping commas/spaces; clamps standard entries `>= 0` (allowing signed `other_deduct` only when `$allowSignedOther = true` during Excel import reconciliation).
  - `totalDeductions(array $deductions): float`
  - `groupTotals(array $deductions): array` — returns grouped sums so every table, print sheet, Excel export, and report reconciles to the centavo:
    - `gsis_col1` = `gsis_premium + gsis_ouli + gsis_diff`
    - `gsis` = `gsis_col1 + gsis_policy + gsis_other`
    - `pagibig_col2` = `pagibig_loan + pagibig_mp2`
    - `pagibig` = `pagibig_premium + pagibig_col2`
    - `phic` = `phic + phic_diff`
    - `other_payables_total` = `bank_other_payables + loans + government_cont + other_deduct` (folded into column `M` sub-row 4 in the fixed `A..T` LGU Excel template and stacked under `LBP / Other Payables` in Payroll tables/print sheets so Excel's `=SUM(E:F)-SUM(G:P)` matches DB `net_pay` without altering LGU-Mahinog's fixed column map)
    - `bank_lbp_col` = `bank_lbp + other_payables_total`
    - `bank_1stvb_col` = `bank_1stvb + bank_rbt`
    - `banks` = `bank_lbp + bank_other_payables + bank_mcc + bank_1stvb + bank_rbt`
    - `other` = `loans + government_cont + other_deduct` (shown as its own `OTHER` column in `report/summary.php` Deduction Analysis)
    - `tax` = `withholding_tax`
    - `total` = sum of all 19 fields
  - `computePayroll(float $salaryRate, float $refundRata, array $deductions, ?float $firstQuincena = null): array` — returns `salary_rate_snapshot`, `refund_rata`, `gross_pay`, `total_deductions`, `net_pay = round($grossPay - $totalDeductions, 2)`, `first_quincena`, `second_quincena`, `cash_paid`.
    - Quincena rule: if `$firstQuincena !== null && $firstQuincena > 0 && $firstQuincena <= $netPay`, use `round($firstQuincena, 2)`; otherwise `round($netPay / 2, 2)`; always `second_quincena = round($netPay - $first_quincena, 2)`.
  - `extractSnapshot(array $payrollRow, ?array $fallbackDeductions = null): array` — decodes `deduction_snapshot` JSON if present, else falls back to `$fallbackDeductions`.

### 5.4 Period Control, Locking, Roster Rules & Batch Processing
- **Period validation helper:** Accept `?period=YYYY-MM` (validated via regex `/^\d{4}-(0[1-9]|1[0-2])$/`), defaulting to `date('Y-m')` in `Asia/Manila`.
- **Period selector UI (`<input type="month">` + quick jump to existing periods):**
  - Available on `Payroll::index`, `Payroll::printAll`, `Payroll::export`, `Deduction::index`, `Deduction::manage`, `Employee::import` modal, `Payslip::individual` (`?period=YYYY-MM`), and `Dashboard::index`.
  - All links (Print All, Export All, Individual Payslip, Manage Deductions) propagate the selected `period`.
- **Snapshot-driven (locked/historical) vs. Roster-driven (draft) reads:**
  - **Locked period (or any past period in `Report` / `Payslip`):** `Payroll::index`, `Payroll::printAll`, `Payroll::export`, `Report::generate`, and `Payslip::batchPrint` / `individual` read strictly from `payroll_records` using `LEFT JOIN employees` / `LEFT JOIN offices` and `COALESCE(*_snapshot, live)` — so later hires, office transfers, deactivations, or salary edits never alter closed months.
  - **Draft period:** `Payroll::index`, `Payroll::printAll`, `Payroll::export`, and `Deduction::index` show all active employees (`is_active = 1`) plus any employee who already has a `payroll_records` row in that draft period (hiding inactive employees who have no record in that draft month).
- **Period Locking (`POST /payroll/lock`, `POST /payroll/unlock`):**
  - Displays a clear status badge on `Payroll::index`: **DRAFT** (editable) vs **LOCKED** (read-only).
  - Locking a period sets `payroll_periods.status = 'locked'`, `locked_at = now`, `locked_by = session()->get('id')`.
  - While locked:
    - `Payroll::process`, `Payroll::processBatch`, and `Employee::import` (for that period) reject writes with a clear flash error (`"Period YYYY-MM is locked. Unlock it first to make changes."`).
    - `Deduction::update` updates the employee's standing profile (`deductions`, `salary_rate`, `atm_account_no`) for future months while disabling/ignoring `refund_rata`, **and does NOT touch the locked period's `payroll_records` row**.
  - Unlocking requires a confirmation modal (single-operator safety net against accidental edits, while still letting the clerk fix a genuine mistake if needed).
- **Batch Processing (`POST /payroll/process-batch`):**
  - Button on `Payroll::index`: **"Process Office"** (when an office is selected) or **"Process All Active"** (when viewing all offices), disabled when period is locked.
  - Inclusion rules: processes every active employee (`is_active = 1`) in scope who has `salary_rate > 0` (excluding RATA-only offices `rata` / `VICE-SB rata` when running across all offices, and skipping zero-rate/unconfigured JO rows so it never generates ₱0 or negative-net records).
  - Runs inside a database transaction: computes payroll via `PayrollCalculator`, preserves existing `refund_rata` and valid `first_quincena` for that period if a record already exists, writes `deduction_snapshot` + all employee/office snapshots + `processed_by = session()->get('id')`.
  - Reports processed and skipped counts (`"Processed 42 employees for November 2026 (skipped 3 with ₱0 salary rate)."`).
- **Single-employee `Payroll::process($employee_id)`:**
  - Restricted to `POST` only (remove `GET` from `Routes.php` line 49).
  - Accepts `period`, validates period is not locked, saves `deduction_snapshot`, all snapshots, and `processed_by = session()->get('id')`.

### 5.5 Database Backup & Restore (`/backup`)
Accessible from the sidebar (admin-only):
- **Pure-PHP SQL Exporter (`App\Libraries\DatabaseBackup`):**
  - Does **not** depend on external `mysqldump` or `mysql` binaries being on Windows `PATH`.
  - Exports schema + `INSERT` statements for all PayTrack tables (`users`, `offices`, `employees`, `deductions`, `payroll_records`, `payroll_periods`, `app_settings`) wrapped in `SET FOREIGN_KEY_CHECKS=0; ... SET FOREIGN_KEY_CHECKS=1;` with proper value escaping and a header comment identifying PayTrack backup version + timestamp.
  - `POST /backup/download`: streams `paytrack_backup_YYYY-MM-DD_HHmmss.sql` as a browser download AND saves a local copy under `writable/backups/` (keeping the latest 10 automatic copies).
- **Restore (`POST /backup/restore`):**
  - Uploads a `.sql` backup file, validates header marker (`-- PayTrack Backup`) and typed confirmation (`RESTORE`).
  - Automatically writes a pre-restore safety backup to `writable/backups/pre_restore_YYYY-MM-DD_HHmmss.sql` before applying.
  - Executes statements cleanly inside a transaction/foreign-key-safe wrapper and redirects with a summary message (or rolls back and reports the exact error if invalid).
- **Backup status reminder:** Dashboard shows the date/time of the last downloaded backup (stored in `app_settings` as `last_backup_at`) and shows a gentle yellow banner if no backup has been taken in over 7 days.

### 5.6 LGU Signatories & System Settings (`/settings`)
- Dedicated Settings view (accessible from sidebar / top-right gear alongside account password settings) backed by `app_settings`:
  - Editable fields: `LGU Name`, `Municipality / Province`, `Mayor Name`, `Mayor Title`, `Treasurer / Disbursing Officer Name`, `Treasurer / Disbursing Officer Title`.
- Consumed by:
  - `Payroll::export` (replaces hardcoded `private const` values in `Payroll.php`).
  - `app/Views/payroll/print.php` (replaces hardcoded header & signature names).
  - `app/Views/payslip/individual.php` (replaces hardcoded `LGU-MAHINOG` and replaces the placeholder SVG circle seal with `base_url('mahinoglgu.png')`).

### 5.7 Security & Accidental-Destruction Guardrails
- **Enable global `csrf` filter** in `app/Config/Filters.php` (`globals['before'] => ['csrf']`).
  - Add `<?= csrf_field() ?>` to every HTML `<form method="post">` across all views (`auth/login.php`, `deduction/manage.php`, `employee/create.php`, `employee/create_jo.php`, `employee/edit.php`, `user/index.php`, `user/settings.php`, plus new settings/backup/lock/batch forms).
  - Include CSRF token in AJAX requests (`Office::store`, `Office::delete` in `employee/create.php`, `employee/edit.php`, `employee/create_jo.php`) via `<meta name="csrf-token">` + `X-CSRF-TOKEN` header, and set `$regenerate = false` (or refresh token in JSON response) — **Our call:** set `$regenerate = false` in `app/Config/Security.php` (session-scoped token, standard CI4 recommendation for multi-tab / AJAX apps) so opening Print/Payslip in a new tab or adding multiple offices in the modal never breaks CSRF tokens on the main page.
- **HTTP method, soft-deactivation & privilege hygiene:**
  - Change `employee/delete/(:num)` from `GET` to `POST` in `Routes.php` and update the button in `employee/index.php` and `employee/edit.php` to a POST form with `csrf_field()`.
  - **Soft-deactivation for employees with payroll history:** If an employee has any `payroll_records`, `Employee::delete($id)` sets `is_active = 0` (preserving all historical payslips/reports cleanly) instead of hard-deleting. Only employees with zero `payroll_records` are hard-deleted. Add a simple status filter (`Active` / `Inactive` / `All`) and a `POST /employee/reactivate/(:num)` button on `employee/index.php`.
  - Change `payroll/process/(:num)` from `match(['get', 'post'])` to `post`.
  - Keep `auth/logout` as `GET` for sidebar simplicity while enforcing `POST` + CSRF on all data-mutating routes (`delete`, `reactivate`, `process`, `process-batch`, `lock`, `unlock`, `backup/download`, `backup/restore`, `settings/update`, `user/register`).
  - Enforce `session()->get('role') === 'admin'` on `User::register`, `Employee::deleteAll`, `Settings`, and `Backup`.
  - Enforce server-side `$this->request->getPost('confirm') === 'DELETE'` in `Employee::deleteAll()` (currently only checked in client-side JS!).
  - Escape all unescaped view outputs with `esc()` (especially `user/index.php`, `payroll/index.php`, `employee/index.php`, `layout/main.php` username/flash messages).
- **Employee ID & validation fixes:**
  - Fix `Employee::create()`, `Employee::createJo()`, and `Employee::import()` ID generation: query the max numeric suffix for `EMP-{year}-%` / `JO-{year}-%` instead of `countAllResults() + 1` so deleting an employee never causes duplicate-ID collisions.
  - Fix `EmployeeModel` validation rules so `update($id)` uses `is_unique[employees.employee_id,id,{id}]` and `Employee::update($id)` checks the return value and flashes validation errors instead of silently claiming success.

### 5.8 Specific Bug Fixes Across Modules
1. **`app/Views/deduction/manage.php` & `Deduction::update()`:**
   - Add the 5 missing form inputs (`pagibig_mp2`, `phic_diff`, `bank_other_payables`, `bank_rbt`, and `atm_account_no`) so saving the form never wipes them to `null`/0.
   - Fix client-side `recalcNetPay()` to include `+ refundRata` (matching server-side `($gross_pay) - $total_deductions` where `gross_pay = salary_rate + refund_rata`).
   - Strip commas server-side too (via `PayrollCalculator`) in case JS doesn't run.
   - **Period-aware prefill & save semantics:**
     - **Current or future draft period (`period >= date('Y-m')` and unlocked):** prefill from standing `deductions` + `employees` (plus that period's `refund_rata` if any); save updates standing `deductions`, `employees` (`salary_rate`, `atm_account_no`), AND upserts that period's `payroll_records` + full snapshots.
     - **Past unlocked period (`period < date('Y-m')` and unlocked):** prefill from that period's `payroll_records` snapshot (`deduction_snapshot`, `salary_rate_snapshot`, `atm_account_no_snapshot`, `refund_rata`) with a clear banner (*"Editing historical period YYYY-MM — changes apply only to this month and will not alter current standing rates"*); save updates **only** that period's `payroll_records` row (leaving standing `deductions` and `employees` untouched).
     - **Locked period:** disable `refund_rata` with a banner (*"Period YYYY-MM is locked — saving updates the employee's standing profile for future months only; YYYY-MM remains unchanged"*); save updates standing `deductions`, `employees.salary_rate`, and `employees.atm_account_no`, without touching the locked period's `payroll_records`.
2. **`app/Controllers/Payroll.php` & `app/Views/payroll/index.php`:**
   - Wire up the `search` query parameter (`full_name`, `employee_id`, `atm_account_no`) which is currently ignored.
   - Display grouped column totals via `PayrollCalculator::groupTotals()` for all 19 deduction fields so column totals always equal Net Pay (GSIS col 1 = `gsis_premium + gsis_ouli + gsis_diff`; Pag-IBIG col 2 = `pagibig_loan + pagibig_mp2`; PHIC = `phic + phic_diff`; LBP / Other Payables = `bank_lbp + bank_other_payables + loans + government_cont + other_deduct`; 1stVB = `bank_1stvb + bank_rbt`).
   - **SIGNATURE column display:** Display `atm_account_no` (or `atm_account_no_snapshot`) if present, falling back to `contact_number` if blank, consistently across `payroll/index.php`, `payroll/print.php`, and `Payroll::export`.
   - In `Payroll::export`, write `loans + government_cont + other_deduct` summed into `M{$r4}` (sub-row 4 of column `M` "LBP / Other Payables") so Excel's `=SUM(E:F)-SUM(G:P)` formula matches PayTrack's DB `net_pay` to the centavo while preserving the fixed `A..T` layout.
   - When `payroll_records` exists for the selected period, read deductions from `deduction_snapshot` (via `PayrollCalculator::extractSnapshot`) rather than live `deductions`.
3. **`app/Controllers/Employee.php` (`import` & `index`):**
   - Accept selected `period` from the import modal (defaulting to current Manila month) and reject if that period is locked.
   - Parse each 4-row employee block on normal office sheets into all 16 sub-row deduction fields (`G1=gsis_premium, G3=gsis_ouli, G4=gsis_diff, H1=gsis_policy, I1=gsis_other, J1=pagibig_premium, K1=pagibig_loan, K3=pagibig_mp2, L1=phic, L4=phic_diff, M1=bank_lbp, M3=bank_other_payables, M4=other_deduct, N1=bank_mcc, O1=bank_1stvb, O3=bank_rbt, P1=withholding_tax` — adding any unexpected extra row in a column into that column's parent field), and explicitly **reset all 19 deduction fields** on upsert (zeroing absent fields) so re-importing never double-counts stale values.
   - Reconcile imported `net_pay`: compute `gross_pay = salary_rate + refund_rata` and `mappedTotal = PayrollCalculator::totalDeductions($deductionData)`; if the sheet's `NET PAY` cell is populated and differs from `gross_pay - mappedTotal`, record the residual delta in `other_deduct` (matching `importRataOnlySheet`) so DB `total_deductions` and `net_pay` match the source sheet exactly.
   - Save `deduction_snapshot`, `salary_rate_snapshot`, `employee_code_snapshot`, `full_name_snapshot`, `office_id_snapshot`, `office_name_snapshot`, `position_snapshot`, `atm_account_no_snapshot`, and `processed_by` on imported payroll records (including RATA-only sheets, where `salary_rate_snapshot = 0`, `refund_rata = $rataAmount`, `gross_pay = $rataAmount`).
   - Fix `employee/index.php` line 54 header showing `$offices[0]['office_name']` instead of the actually selected office name, and fix empty-state `colspan="8"` (currently `7`).
4. **`app/Views/payroll/print.php`:**
   - Fix row numbering on page 2+ (`$no` must increment sequentially across chunks instead of `$chunkIdx * 10 + 1`).
   - Include all 19 deduction fields (using `PayrollCalculator::groupTotals()` matching `payroll/index.php` and `Payroll::export`) and read from `deduction_snapshot`.
   - Use LGU Settings for header & signatories.
5. **`app/Controllers/Payslip.php` & `app/Views/payslip/individual.php` / `batch_print.php`:**
   - Accept `?period=YYYY-MM` on `Payslip::individual($employee_id)` (defaulting to latest processed period for that employee) and use `LEFT JOIN` + snapshots so deactivated/archived employees still render valid historical payslips.
   - Read deductions from `deduction_snapshot` (fallback to live `deductions` only for legacy pre-snapshot rows).
   - Display `Basic Pay = salary_rate_snapshot` (omitted if `0` on RATA-only vouchers), `Refund / RATA = refund_rata`, and `Total Earnings = gross_pay` (eliminating RATA double-counting).
   - List **every non-zero deduction line** across all 19 fields on `individual.php` (always showing the 4 core statutory items: GSIS Personal Premium, Pag-IBIG Personal Premium, PhilHealth, Withholding Tax, plus any other non-zero loans/differentials/banks) so the printed line items always add up to `Total Deductions`.
   - Fix `individual.php` date parsing using `DateTimeImmutable::createFromFormat('!Y-m', $period)` and remove dead `$payroll['cut_off']` / `$payroll['salary_rate']` references.
6. **`app/Controllers/Report.php` & `app/Views/report/summary.php`:**
   - Fix undefined `$periodFormatted` variable on empty results (`report/summary.php` line 28).
   - Use `LEFT JOIN` + snapshots (`COALESCE(*_snapshot, live)`) and `deduction_snapshot` so Monthly, Quarterly, Annual, and Deduction Analysis reports remain historically accurate even after employee edits/transfers/deactivations.
   - Add an `OTHER` column (`loans + government_cont + other_deduct`) to Deduction Analysis so all displayed deduction columns always sum to `TOTAL DEDUCT`.
7. **`app/Controllers/Dashboard.php`:**
   - Accept `?period=YYYY-MM` (defaulting to current Manila month).
   - Stop fabricating fake numbers for months that have no `payroll_records` in the 6-month trend chart (show `0` for unprocessed months so the chart reflects real processed payroll, not phantom estimates).
   - Include all 19 deduction fields in `deductions_breakdown` and `top_deductions` (using snapshots for processed records / all 19 columns consistently).

### 5.9 Automated Tests (PHPUnit)
Add focused test suites under `tests/unit/` (using the existing CI4 `CIUnitTestCase` + SQLite `:memory:` test DB):
- `PayrollCalculatorTest`: all 19 deduction fields, grouped totals (including `other_payables_total`), canonical `gross_pay = salary_rate + refund_rata`, 50/50 and custom `first_quincena` splits, comma-formatted inputs, edge cases (`net_pay = 0`, `first_quincena > net_pay`, RATA-only `salary_rate = 0`).
- `PayrollLifecycleTest`: deduction/employee snapshot immutability (process Month 1 → edit deductions/salary/office & deactivate employee → verify Month 1 snapshot, payslip, and reports unchanged), period lock blocking writes while allowing standing profile updates, past-unlocked historical edit not overwriting standing rates, batch processing preserving custom quincena and skipping inactive / zero-rate / RATA-only rows, `processed_by` recorded.
- `EmployeeAndSecurityTest`: sequential ID generation after deleting an employee (no collision), `EmployeeModel` update validation with unchanged `employee_id`, soft-deactivation vs hard-delete behavior, admin-only guard on `User::register` and `Employee::deleteAll`, `DELETE` confirmation enforcement.
- `DatabaseBackupTest`: SQL export generation and clean round-trip restore preserving all tables and snapshot JSON.

---

## 6. Calls Made by Designer (For User Check)

- **Soft-deactivation for employees with payroll history:** Deleting an employee who has past payroll records marks them inactive (`is_active = 0`) instead of erasing them, and locked/historical periods render strictly from snapshots so closed months never change when someone leaves, transfers, or gets a raise.
- **Canonical `gross_pay = salary_rate + refund_rata`:** Makes `GROSS PAY - DEDUCTIONS = NET PAYABLE` balance on every report and prevents double-counting RATA on individual payslips.
- **Batch processing inclusion:** "Process All Active" skips inactive employees, RATA-only offices, and ₱0-rate rows (reporting how many were skipped) so it never generates blank or negative-net payroll rows.
- **`loans` / `government_cont` / `other_deduct` in LGU Excel export:** Folded into column `M` sub-row 4 ("LBP / Other Payables") so LGU-Mahinog's fixed `A..T` Excel column map stays untouched while Excel's `NET PAY` formula still matches PayTrack to the centavo.
- **Excel re-import reconciliation:** Resets all 19 deduction fields from the 4-row sheet block on upsert (preventing double-counting on re-import) and records any sheet `NET PAY` discrepancy into `other_deduct`.
- **Past unlocked period edits:** Editing an unlocked past month in `Deduction::manage` updates only that historical month's snapshot without overwriting the employee's current standing deductions/salary.
- **SIGNATURE column:** Shows `atm_account_no` when set, falling back to `contact_number` when blank.
- **CSRF regeneration:** Set `Security::$regenerate = false` (session-scoped token) so multi-tab Print/Payslip and AJAX modals never break tokens.
- **Dashboard 6-month trend:** Displays `0` for months with no processed payroll rather than fabricating estimates from today's active roster.

---

## 7. Left to the Builder

- Exact directory layout of bundled vendor files under `public/assets/vendor/` (as long as zero external URLs remain).
- Exact modal markup, alert styling, and Bootstrap utility classes for Settings, Backup/Restore, Lock/Unlock, and Batch Process UI (consistent with the existing sharp-edged navy theme in `layout/main.php`).
- Exact PHPUnit helper fixtures and migration timestamp naming.
