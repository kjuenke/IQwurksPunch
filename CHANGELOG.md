# IQwurksPunch Changelog

All notable changes to IQwurksPunch are documented in this file.

The project follows Semantic Versioning for stable releases and generally follows the Keep a Changelog format.

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
- Daily payroll email content now includes:
  - Gross hours
  - Meal deduction
  - Unpaid breaks
  - Regular hours
  - Overtime hours
  - Payable hours
  - Payroll status
- Payroll calculations are shared across web reports, email reports, dashboard totals, and exports.
- Report routes were reorganized around focused controllers.
- Employee lists now distinguish active, inactive, historical, and deletable records.
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

### Known Limitations

- PDF payroll registers are not included in Version 0.4.
- Date-range payroll reports are not yet available.
- Department filtering and payroll search are not yet available.
- Automated backup and restore workflows remain planned.
- Exception-report email delivery is prepared in the Notification Center but not yet scheduled.

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

- Scheduler logs now record startup, evaluation, completion, failures, duration, timezone, process, and memory details.
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
