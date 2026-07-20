# IQwurksPunch Changelog

All notable changes to IQwurksPunch are documented in this file.

The project follows Semantic Versioning for stable releases and generally follows the Keep a Changelog format.

---

## [0.5.0] - 2026-07-20

### Added

#### Payroll Workspace

- Added date-range payroll reporting.
- Added employee filtering.
- Added department filtering.
- Added report-level payroll totals.
- Added employee daily-detail sections.
- Added payroll-week summaries within date-range reports.
- Added payroll warnings and review status throughout the workspace.

#### Configurable Labor Rules

- Added a dedicated Labor Rules administration page.
- Added configurable daily overtime thresholds.
- Added configurable weekly overtime thresholds.
- Added configurable double-time thresholds.
- Added configurable Sunday or Monday workweek starts.
- Added persistent labor-rule storage through the repository and service layers.
- Added Labor Rules navigation under Settings.
- Added validation requiring the double-time threshold to exceed the daily overtime threshold.

#### Payroll Calculations

- Added explicit daily-overtime totals.
- Added explicit weekly-overtime totals.
- Added explicit double-time totals.
- Added total-overtime totals.
- Added premium-hour totals.
- Prevented daily overtime from being counted again as weekly overtime.
- Restricted weekly-overtime conversion to hours that remained classified as regular.
- Standardized payroll relationships:

```text
Total Overtime = Daily Overtime + Weekly Overtime

Premium Hours = Total Overtime + Double-Time

Payable Hours = Regular + Total Overtime + Double-Time
```

#### CSV Exports

- Added Payroll Workspace CSV export.
- Expanded daily payroll CSV output.
- Expanded weekly payroll CSV output.
- Added columns for:
  - Regular hours
  - Daily overtime
  - Weekly overtime
  - Double-time
  - Total overtime
  - Premium hours
  - Total payable hours
- Added report-level totals to Payroll Workspace CSV output.

#### PDF Documents

- Added daily payroll PDF export.
- Added weekly payroll-register PDF export.
- Added Payroll Workspace PDF export.
- Added printable employee time-card PDF export.
- Added company information, report periods, and timezone information to PDFs.
- Added payroll warnings and review status to PDFs.
- Added employee and supervisor signature lines to employee time cards.
- Added regular, overtime, double-time, premium, and payable totals to PDF documents.

#### Dashboard and Email Reporting

- Added daily overtime, weekly overtime, double-time, total overtime, premium hours, and payable hours to the dashboard’s current-week totals.
- Added a Labor Rules quick action to the dashboard.
- Expanded daily payroll email content with:
  - Daily overtime
  - Double-time
  - Total overtime
  - Premium hours
  - Report-wide totals
  - Employees requiring review
- Added explanatory text clarifying that weekly overtime is calculated in weekly payroll reports.
- Added payroll totals to report-generation logs.

#### Automated Tests

- Added dedicated labor-rules payroll tests.
- Added configurable-threshold test coverage.
- Added double-time classification coverage.
- Added weekly-overtime conversion coverage.
- Added no-double-counting assertions.
- Added Sunday and Monday workweek validation coverage.
- Expanded the automated suite to 28 tests and 116 assertions.

### Changed

- Labor Rules is now the authoritative source for:
  - Daily overtime threshold
  - Weekly overtime threshold
  - Double-time threshold
  - Workweek start day
- Company Settings remains responsible for:
  - Company identity
  - Timezone
  - Punch rounding
  - Meal deductions
  - Paid-break allowance
- Removed visible overtime and workweek controls from Company Settings.
- Retained legacy Company Settings values only as compatibility fallbacks.
- Updated `PunchReportService` to obtain labor-rule values from `LaborRulesService`.
- Updated Payroll Workspace calculations to use the configured workweek and overtime thresholds.
- Updated daily, weekly, and date-range payroll views with consistent payroll categories.
- Updated dashboard payroll totals to use the expanded weekly result structure.
- Changed employee time-card PDFs to landscape orientation for improved readability.
- Standardized CSV and PDF terminology across all payroll outputs.
- Updated project documentation for the completed Reporting and Payroll Documents release.

### Fixed

- Fixed daily and weekly reports potentially using obsolete overtime values from Company Settings.
- Fixed daily overtime being included in weekly-overtime eligibility.
- Fixed double-time being reported as ordinary overtime.
- Fixed total overtime calculations that could include double-time.
- Fixed premium-hour totals to include overtime and double-time exactly once.
- Fixed payable-hour totals to reconcile with regular, overtime, and double-time categories.
- Fixed inconsistent payroll labels between web reports, CSV files, PDF files, email reports, and dashboard totals.
- Fixed weekly PDF exporter identification by using the existing `PayrollRegisterPdfExporter`.
- Removed temporary development backup files after successful validation.

### Security and Data Integrity

- Payroll calculations now follow one authoritative labor-rule configuration.
- Overtime and double-time classifications are independently represented.
- Weekly overtime cannot reclassify hours already assigned to daily overtime or double-time.
- Existing company settings remain available as safe migration fallbacks.
- Payroll warnings remain visible in web reports, PDF documents, and email reports.
- Employee payroll history remains preserved.

### Upgrade Notes

Existing installations upgrading to Version 0.5.0 should:

1. Pull the Version 0.5.0 source.
2. Run all pending database migrations.
3. Open **Settings → Labor Rules**.
4. Confirm the daily overtime threshold.
5. Confirm the weekly overtime threshold.
6. Confirm the double-time threshold.
7. Confirm the Sunday or Monday workweek start.
8. Review Company Settings for timezone, rounding, meals, and breaks.
9. Generate daily and weekly payroll reports.
10. Verify CSV and PDF downloads.
11. Send a manual payroll email.
12. Run the full automated test suite:

```bash
vendor/bin/phpunit
```

The expected Version 0.5.0 result is:

```text
28 tests
116 assertions
```

Legacy overtime and pay-period values remain in Company Settings storage for backward compatibility, but Labor Rules is authoritative.

### Known Limitations

- Manual weekly payroll email delivery is not yet implemented.
- Scheduled weekly payroll email delivery is not yet implemented.
- Scheduled exception-report delivery is not yet implemented.
- Supervisor punch correction is not yet available.
- Payroll approval and locking are not yet available.
- Automatic backup and restore workflows are not yet available.
- Health-check and diagnostic console commands remain planned.

---

## [0.4.0] - 2026-07-15

### Added

- Configurable payroll policies:
  - Daily overtime threshold
  - Weekly overtime threshold
  - Punch rounding interval and mode
  - Automatic meal deduction
  - Paid-break allowance
- Focused payroll calculation classes with automated PHPUnit coverage.
- Company-timezone-aware daily payroll reporting.
- Company-timezone-aware weekly payroll reporting.
- Daily and weekly payroll CSV downloads.
- Weekly overtime calculation separated from daily overtime.
- Employee punch-history validation and incomplete-shift warnings.
- Notification Center with:
  - Database-managed recipients
  - Daily payroll subscriptions
  - Weekly payroll subscriptions
  - Exception-report subscriptions
  - Recipient activation and deactivation
  - Recipient deletion
- Employee lifecycle management:
  - Activate employees
  - Deactivate employees while preserving payroll history
  - Permanently delete employees only when no punch history exists
- Live operations dashboard displaying:
  - Active and total employees
  - Employees currently clocked in
  - Today’s punch count
  - Current-week payroll totals
  - Payroll issues requiring review
  - Recent punch activity
  - Automatic-report status
  - Last email-report status
- Shared dependency container registrations for new repositories, services, and exporters.
- Focused payroll and payroll-export controllers.
- Improved report navigation.
- PHPUnit cache ignore rule.

### Changed

- Email recipients now come exclusively from the Notification Center database.
- SMTP configuration files now contain only transport and sender settings.
- Daily payroll email content includes:
  - Gross hours
  - Meal deduction
  - Unpaid breaks
  - Regular hours
  - Overtime hours
  - Payable hours
  - Payroll status
- Payroll calculations are shared across web reports, email reports, dashboard totals, and exports.
- Report routes were reorganized around focused controllers.
- Employee lists distinguish active, inactive, historical, and deletable records.
- Dashboard placeholders were replaced with live operational data.
- Navigation links were corrected to use the actual report and settings routes.

### Fixed

- Corrected company-timezone display for the kiosk’s last-punch time.
- Corrected payroll day boundaries so stored UTC punches are grouped by company-local date.
- Prevented malformed clock-out records from silently producing valid payroll totals.
- Prevented duplicate employee numbers when editing employees.
- Prevented permanent deletion of employees with payroll history.
- Removed obsolete configuration-based recipient counts.
- Removed accidentally tracked backup and generated files.

### Security and Data Integrity

- Payroll history is preserved when employees are deactivated.
- Permanent employee deletion is blocked when punch records exist.
- Notification recipients are validated and normalized before storage.
- Notification types are restricted through an explicit allowlist.
- SMTP secrets remain in the ignored local `config/mail.php` file.

### Known Limitations at Release

- PDF payroll registers were not included in Version 0.4.
- Date-range payroll reports were not available.
- Department filtering and payroll search were not available.
- Automated backup and restore workflows remained planned.
- Exception-report email delivery was prepared in the Notification Center but was not scheduled.

---

## [0.3.0] - 2026-07-13

### Added

- Centralized application logging.
- Component-specific log channels.
- Logger factory and file log handlers.
- Console application and command architecture.
- Automatic report scheduler.
- Cron integration.
- Scheduler locking and duplicate-send prevention.
- Company-configurable report schedule.
- Company timezone support.
- Automatic daily payroll email delivery.
- Email history and delivery logging.
- Release-management documentation.
- Definition of Done.
- Coding standards.
- Contributor guide.

### Changed

- Scheduler logs record startup, evaluation, completion, failures, duration, timezone, process, and memory details.
- Structured scheduler logs are separated from raw cron output.
- Application services are increasingly resolved through the shared dependency container.

### Fixed

- Improved scheduler diagnostics and exception handling.
- Improved mail-delivery logging.
- Standardized release and Git workflows.

---

## [0.2.0-alpha]

### Added

- Company settings.
- Manual payroll email reports.
- Automatic report scheduling foundation.
- Email audit history.
- Company branding.
- Timezone-aware report display.
- Migration improvements.

---

## [0.1.0]

### Added

- Custom MVC framework.
- Supervisor authentication.
- Employee management.
- Employee PIN management.
- Kiosk interface.
- Punch tracking.
- Initial payroll reporting.
- SQLite database.
- Repository and service layers.
