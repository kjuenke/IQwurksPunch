# IQwurksPunch Changelog

All notable changes to IQwurksPunch are documented in this file.

The project follows Semantic Versioning for stable releases and generally follows the Keep a Changelog format.

---

## [0.6.0] - 2026-07-21

### Added

#### Database Backup and Retention

- Added verified, timestamped SQLite database backups.
- Added backup integrity checks.
- Added foreign-key validation for backups.
- Added a backup catalog service.
- Added backup listing.
- Added configurable backup retention.
- Added retention preview mode.
- Added explicit retention deletion mode.
- Added backup execution locking.
- Added backup logging and failure reporting.
- Added automatic daily backup execution through cron.
- Added the following console commands:
  - `backup:create`
  - `backup:list`
  - `backup:prune`
  - `backup:run`
  - `backup:verify`

#### Database Restore

- Added safe database-restore previews.
- Added explicit `--apply` restore execution.
- Added exact backup-filename confirmation.
- Added backup verification before restoration.
- Added foreign-key verification before restoration.
- Added pre-restore safety backups.
- Added maintenance-mode integration during restoration.
- Added SQLite WAL checkpoint handling.
- Added stale SQLite sidecar-file cleanup.
- Added post-restore integrity validation.
- Added migration-history verification after restoration.
- Added restore rollback handling.
- Added the `backup:restore` console command.

#### Database Health and Diagnostics

- Added the `database:check` console command.
- Added SQLite integrity checking.
- Added foreign-key violation checking.
- Added database file and directory permission checks.
- Added SQLite version reporting.
- Added journal-mode reporting.
- Added application-table counts.
- Added migration counts.
- Added database page and unused-page statistics.
- Added database-size and diagnostic-duration reporting.

#### System Doctor

- Added the `doctor` console command.
- Added application-version checks.
- Added PHP-version checks.
- Added required PHP-extension checks.
- Added Composer dependency checks.
- Added configuration-file checks.
- Added application-timezone checks.
- Added SMTP configuration checks.
- Added SMTP configuration-permission checks.
- Added runtime-directory checks.
- Added disk-capacity checks.
- Added maintenance-mode checks.
- Added active-database health checks.
- Added migration-history checks.
- Added verified-backup checks.
- Added scheduler-cron checks.
- Added automatic-backup-cron checks.
- Added scheduler-activity checks.
- Added mail-activity checks.
- Added `PASS`, `WARN`, and `FAIL` diagnostic classifications.

#### Scheduler and Mail Diagnostics

- Added the `scheduler:check` console command.
- Added scheduler-cron detection.
- Added scheduler-lock inspection.
- Added scheduler application-log inspection.
- Added scheduler cron-output inspection.
- Added recent scheduler-failure detection.
- Added report-schedule validation.
- Added scheduled-recipient validation.
- Added last-delivery validation.
- Added the `mail:check` console command.
- Added SMTP hostname-resolution checks.
- Added SMTP connectivity checks.
- Added TLS-readiness checks.
- Added mail-transport readiness checks.
- Added delivery-log activity checks.

#### Maintenance Mode

- Added web maintenance mode.
- Added optional maintenance reasons.
- Added maintenance activation timestamps.
- Added configurable retry-after behavior.
- Added persistent maintenance state.
- Added the following console commands:
  - `maintenance:on`
  - `maintenance:off`
  - `maintenance:status`

#### Supervisor Punch Correction

- Added supervisor punch-history management.
- Added manual missing-punch entry.
- Added punch editing.
- Added punch deletion.
- Added required correction reasons.
- Added typed punch-deletion confirmation.
- Added punch-sequence validation.
- Added transaction rollback for invalid punch sequences.
- Added supervisor identification on corrected punches.
- Added correction timestamps.
- Added correction audit records.
- Added immutable punch-correction history.
- Added support for correcting:
  - `clock_in`
  - `clock_out`
  - `break_out`
  - `break_in`
  - `meal_out`
  - `meal_in`
- Added migration `010_add_punch_correction_support.php`.

#### Authentication Hardening

- Added centralized supervisor route guarding.
- Added database revalidation of authenticated supervisors.
- Added active-account validation.
- Added explicit administrator and supervisor role authorization.
- Added session regeneration after login.
- Added intended-page preservation.
- Added complete supervisor-session cleanup during logout.
- Added local application session storage.
- Added strict session handling.
- Added HTTP-only session cookies.
- Added SameSite cookie protection.
- Added supervisor session-activity tracking.

#### Kiosk Inactivity Protection

- Added a configurable kiosk inactivity timeout.
- Added a permitted timeout range of 15 to 600 seconds.
- Added a default timeout of 60 seconds.
- Added a final ten-second timeout warning.
- Added keyboard-activity detection.
- Added mouse and pointer-activity detection.
- Added touch-activity detection.
- Added form-input activity detection.
- Added automatic cleanup of unfinished kiosk interactions.
- Added selected-employee cleanup.
- Added PIN and temporary-authentication cleanup.
- Added automatic return to the employee-number screen.
- Added server-side timeout validation.
- Added stale PIN-form rejection.
- Added stale punch-form rejection.
- Added migration `011_add_kiosk_inactivity_timeout.php`.
- Added a Kiosk Safety section to Company Settings.

#### Production Web Deployment

- Added and validated an Nginx production deployment.
- Added and validated PHP-FPM 8.5 operation.
- Added public-directory document-root isolation.
- Added front-controller routing through `public/index.php`.
- Added protection against arbitrary PHP-file execution.
- Added hidden-file protection.
- Added security-related HTTP response headers.
- Added static-asset caching.
- Added LAN-only firewall access for the application.
- Added PHP-FPM production overrides.
- Added application-specific PHP-FPM error logging.

#### Dedicated Physical Kiosk

- Added a restricted local `kiosk` Linux account.
- Added LightDM automatic login.
- Added an Openbox kiosk session.
- Added Chromium full-screen kiosk mode.
- Added local kiosk startup at `http://127.0.0.1/kiosk`.
- Added automatic Chromium restart after browser exit.
- Added application-availability checks before Chromium startup.
- Added retry behavior while the web application is unavailable.
- Added idle mouse-cursor hiding.
- Disabled screen blanking.
- Disabled display power management.
- Disabled sleep, suspend, and hibernation.
- Added and validated kiosk recovery after Chromium termination.
- Added and validated kiosk recovery after LightDM restart.
- Added and validated full reboot recovery.

#### SQLite WAL Mode

- Added SQLite foreign-key enforcement on the primary connection.
- Added a 10-second SQLite busy timeout.
- Added SQLite WAL journal mode.
- Added normal synchronous mode.
- Added automatic WAL checkpoint configuration.
- Added group-writable SQLite sidecar-file handling.
- Added WAL-aware backup behavior.
- Added WAL-aware restore behavior.

#### Log Rotation

- Added application log rotation.
- Added scheduler log rotation.
- Added cron log rotation.
- Added mail log rotation.
- Added PHP-FPM application error-log rotation.
- Added kiosk browser-log rotation.
- Added daily rotation.
- Added 5 MB size-based rotation.
- Added compressed log archives.
- Added date-based archive names.
- Added 30-rotation application-log retention.
- Added 14-rotation kiosk-browser-log retention.

#### Offline Frontend Assets

- Added local Bootstrap 5.3.7 CSS.
- Added local Bootstrap 5.3.7 JavaScript.
- Added local Bootstrap Icons 1.13.1 CSS.
- Added local Bootstrap Icons font files.
- Added vendored license files.
- Added a SHA-256 checksum manifest.
- Removed reliance on external frontend CDNs for primary interface assets.
- Added offline kiosk interface support.

#### Console Architecture

- Added lazy command loading.
- Expanded the console application to include:

```text
help
backup:create
backup:list
backup:prune
backup:restore
backup:run
backup:verify
database:check
doctor
mail:check
maintenance:off
maintenance:on
maintenance:status
schedule:run
scheduler:check
version
```

#### Automated Tests

- Added punch-correction insertion tests.
- Added punch-correction editing tests.
- Added punch-correction deletion tests.
- Added required-correction-reason tests.
- Added punch-sequence validation tests.
- Added immutable-history tests.
- Added transaction rollback tests.
- Expanded the automated suite to 36 tests and 188 assertions.

### Changed

- Updated the application version to `0.6.0`.
- Changed the primary SQLite connection to WAL mode.
- Changed database backups to use a live-database-safe SQLite backup process.
- Changed restore operations to account for WAL and SQLite sidecar files.
- Changed administrative routes to require centralized authentication guarding.
- Changed supervisor sessions to be revalidated against the database.
- Changed login handling to regenerate the PHP session identifier.
- Changed logout handling to remove session state and expire the session cookie.
- Changed application sessions to use `storage/sessions`.
- Changed kiosk transaction handling to track server-side activity.
- Changed the kiosk starting page to clear unfinished employee state.
- Changed invalid employee-number and PIN responses to restart the kiosk flow safely.
- Changed punch correction to validate the complete resulting employee punch sequence before committing.
- Changed frontend references from CDN-hosted assets to locally stored assets.
- Changed production execution from the PHP development server to Nginx and PHP-FPM.
- Changed application and kiosk logs to use managed rotation.
- Changed the Git ignore policy to exclude runtime sessions, backups, temporary files, and safety copies.
- Updated the README for Version 0.6.
- Updated the roadmap to mark Version 0.6 complete.
- Added Version 0.6 release notes.

### Fixed

- Fixed unfinished kiosk transactions remaining visible when an employee walked away.
- Fixed stale kiosk PIN and punch forms being usable after the configured timeout.
- Fixed old kiosk session data potentially carrying into a new employee-number submission.
- Fixed supervisor sessions remaining valid after an account was removed or deactivated.
- Fixed unauthorized roles potentially retaining administrative session state.
- Fixed SQLite concurrency limitations caused by rollback-journal operation.
- Fixed direct active-database copying risks during backup creation.
- Fixed restore risks caused by stale WAL, SHM, or journal files.
- Fixed missing backup verification before restore execution.
- Fixed invalid punch corrections potentially leaving partial database changes.
- Fixed application frontend dependence on external CDN availability.
- Fixed unbounded application and kiosk browser log growth.
- Fixed runtime PHP session files appearing in Git status.
- Removed obsolete local safety-copy files.
- Fixed local Chromium recovery after an unexpected browser exit.
- Fixed kiosk startup dependency on the application already being available.

### Security and Data Integrity

- Administrative routes now require a currently active administrator or supervisor account.
- Supervisor identity and role are revalidated against the database.
- Login regenerates the session identifier.
- Logout removes authentication state and expires the session cookie.
- Session cookies use HTTP-only and SameSite protections.
- SMTP credentials remain in ignored local configuration.
- Runtime session data is excluded from version control.
- Database backups are verified before being accepted.
- Database restores require an exact typed filename confirmation.
- Restore execution creates a pre-restore safety backup.
- Restore execution verifies the resulting active database.
- Punch correction reasons are required.
- Punch deletion requires typed confirmation.
- Punch corrections preserve immutable history.
- Invalid punch sequences are rolled back.
- Kiosk timeout never creates a punch automatically.
- Expired kiosk forms are rejected by the server.
- Nginx exposes only the `public` directory.
- Arbitrary PHP-file execution is blocked.
- Hidden files are blocked.
- Firewall access is restricted to the trusted local network.
- SQLite foreign-key enforcement remains enabled.
- Database integrity and foreign-key checks are available through diagnostics.
- WAL-safe backup behavior was tested against live application writes.

### Upgrade Notes

Existing installations upgrading to Version 0.6.0 should:

1. Create and verify a database backup.
2. Pull or install the Version 0.6.0 source.
3. Install or update Composer dependencies if required.
4. Confirm runtime-directory ownership and permissions.
5. Run all pending migrations:

```bash
php migrate.php
```

6. Confirm migrations 010 and 011 are recorded.
7. Run:

```bash
./iqwurks database:check
```

8. Open **Settings → Company Settings**.
9. Review the Kiosk Safety inactivity timeout.
10. Confirm that the timeout is between 15 and 600 seconds.
11. Test kiosk timeout behavior on the PIN screen.
12. Test kiosk timeout behavior on the punch-options screen.
13. Confirm that no punch is created during a timeout.
14. Review supervisor punch-correction access.
15. Test adding, editing, and deleting a non-production punch.
16. Confirm correction reasons and immutable history.
17. Review `config/backup.php`.
18. Create a manual backup:

```bash
./iqwurks backup:create
```

19. Verify the newest backup:

```bash
./iqwurks backup:verify
```

20. Preview a restore without applying it.
21. Install or confirm the automatic backup cron entry.
22. Confirm the scheduler cron entry.
23. Install or confirm the logrotate policy.
24. Configure Nginx and PHP-FPM for production.
25. Confirm the local session directory is writable.
26. Confirm Bootstrap and Bootstrap Icons are served locally.
27. Verify the vendored frontend assets:

```bash
sha256sum --check public/assets/vendor/SHA256SUMS
```

28. Run scheduler diagnostics:

```bash
./iqwurks scheduler:check
```

29. Run mail diagnostics:

```bash
./iqwurks mail:check
```

30. Run the System Doctor:

```bash
./iqwurks doctor
```

31. Run the full automated test suite:

```bash
php vendor/bin/phpunit
```

The expected Version 0.6.0 result is:

```text
OK (36 tests, 188 assertions)
```

32. Reboot the production computer.
33. Confirm Nginx and PHP-FPM recover.
34. Confirm cron recovers.
35. Confirm the physical kiosk recovers.
36. Confirm local and LAN kiosk access.
37. Confirm supervisor login.
38. Confirm scheduled-report activity.
39. Confirm scheduled-backup activity.

No manual data conversion is required beyond the normal migration process.

### Known Limitations

- Manual weekly payroll email delivery is not yet implemented.
- Scheduled weekly payroll email delivery is not yet implemented.
- Scheduled exception-report delivery is not yet implemented.
- PDF and CSV email attachments are not yet implemented.
- Payroll-period approval is not yet implemented.
- Payroll-period locking is not yet implemented.
- Reopening approved payroll is not yet implemented.
- Supervisor review notes are not yet implemented.
- Third-party payroll export profiles are not yet implemented.
- A guided installation wizard is not yet implemented.
- An automated application-upgrade command is not yet implemented.
- Release packaging is not yet implemented.
- The console does not yet provide a universal per-command `--help` option.
- Multiple companies and multiple physical locations are not yet supported.
- The validated production deployment uses HTTP on a trusted local network.
- HTTPS should be added before the application is exposed through an untrusted network.
- A newly rotated empty mail log can temporarily produce a System Doctor warning until a successful email is logged.

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
