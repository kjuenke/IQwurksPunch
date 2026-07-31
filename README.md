# IQwurksPunch

**Professional Open-Source Employee Time Clock and Payroll Preparation System**

IQwurksPunch is a self-hosted employee time clock and payroll-preparation application designed for a dedicated Linux kiosk.

Employees clock in and out using an employee number and four-digit PIN. Supervisors use a separate administration interface to manage employees, review punches, correct timekeeping records, configure labor rules, generate payroll reports, export CSV and PDF documents, manage email delivery, and monitor system health.

IQwurksPunch is built with PHP, Twig, SQLite, Bootstrap, and a custom MVC framework. It is designed to operate without recurring software subscription fees.

---

## Current Version

```text
0.8.0
```

Version 0.8 expands payroll reporting automation with manual and scheduled weekly reports, scheduled exception reports, approval and operational notifications, CSV and PDF email attachments, delivery-attempt history, controlled automatic retries, permanent-failure quarantine, and attachment-size safeguards.

Version 0.8.0 has completed implementation, automated validation, release acceptance, and final release preparation.

---

## Primary Features

### Employee Kiosk

- Employee-number identification
- Four-digit PIN verification
- Clock In and Clock Out
- Company-timezone clock
- Current employee status
- Last-punch display
- Large touch-friendly controls
- No employee login account required
- Configurable inactivity timeout
- Final timeout warning
- Automatic clearing of unfinished transactions
- Server-side rejection of expired kiosk forms
- No punch is created automatically during timeout

### Employee Administration

- Add employees
- Edit employees
- Change employee PINs
- Activate employees
- Deactivate employees
- Preserve payroll history for inactive employees
- Delete employees only when no punch history exists
- Search and review employee records

### Punch Correction

Supervisors can:

- Review employee punch history
- Add missing punches
- Edit incorrect punches
- Delete incorrect punches
- Enter a required correction reason
- Review correction metadata

Supported punch types include:

```text
clock_in
clock_out
break_out
break_in
meal_out
meal_in
```

Punch corrections include:

- Sequence validation
- Transaction rollback on invalid changes
- Supervisor identification
- Correction timestamps
- Application audit records
- Immutable correction-history records
- Protection against corrections in approved and locked payroll periods
- Company-timezone-aware protected-date validation

### Payroll Review and Approval

Version 0.7 introduces an explicit payroll-period workflow.

Payroll-period states are:

```text
open
under_review
approved
locked
```

Authorized administrators and supervisors can:

- Create payroll periods
- Prevent overlapping payroll ranges
- Begin formal payroll review
- Return a period to open
- Add immutable review notes
- Refresh payroll exceptions
- Resolve payroll exceptions
- Accept reviewed exceptions with an explanation
- Approve payroll after all blocking exceptions are cleared
- Lock approved payroll using exact `LOCK` confirmation
- Reopen approved or locked payroll with a required reason
- Review immutable workflow history

Payroll review includes:

- Company-local inclusive period dates
- Maximum period length of 31 calendar days
- Transactional state transitions
- Concurrency-aware status updates
- Database-validated acting users
- Approval and lock timestamps
- Approving and locking supervisor identification
- Open and total exception counts
- Approval blocking while unresolved exceptions remain
- Immutable transition history
- Immutable review notes limited to 1,000 characters

Reopening returns the payroll period to:

```text
under_review
```

and clears the active approval and lock fields while preserving all prior events in immutable workflow history.

### Payroll Exception Review

Payroll exceptions can be synchronized from an exact-range Payroll Workspace report.

Detected exception categories include:

```text
missing_clock_out
missing_clock_in
unmatched_meal
unmatched_break
overlapping_punch_activity
zero_duration_shift
invalid_punch_sequence
payroll_calculation_warning
```

Exception states are:

```text
open
resolved
accepted
```

Resolved exceptions reopen automatically if the underlying issue returns. Accepted exceptions remain accepted during later refreshes.

### Protected Payroll Records

Punch correction is blocked when the effective company-local punch date falls inside an approved or locked payroll period.

Protection applies to:

- Manual punch creation
- Punch editing
- Punch deletion
- The original punch date during editing
- The proposed punch date during editing

Punch timestamps remain stored in UTC. Protection checks convert those timestamps into the configured company timezone before comparing the company-local calendar date with the payroll-period range.

### Payroll Calculations

- Daily payroll calculation
- Weekly payroll calculation
- Date-range payroll workspace
- Employee filtering
- Department filtering
- Punch rounding
- Automatic meal deductions
- Paid-break allowances
- Daily overtime
- Weekly overtime
- Double-time
- Prevention of overtime double counting
- Incomplete-punch warnings
- Payroll review status

Payroll hour classifications include:

```text
Regular Hours
Daily Overtime
Weekly Overtime
Double-Time
Total Overtime
Premium Hours
Total Payable Hours
```

Payroll calculations use these relationships:

```text
Total Overtime = Daily Overtime + Weekly Overtime

Premium Hours = Total Overtime + Double-Time

Payable Hours = Regular + Total Overtime + Double-Time
```

### Labor Rules

A dedicated Labor Rules administration page controls:

- Daily overtime threshold
- Weekly overtime threshold
- Double-time threshold
- Sunday or Monday workweek start

Company Settings controls:

- Company identity
- Company timezone
- Punch rounding
- Automatic meal deduction
- Paid-break allowance
- Kiosk inactivity timeout

### Payroll Reports

- Daily payroll report
- Weekly payroll summary
- Date-range Payroll Workspace
- Employee daily detail
- Payroll-week summaries
- Report-level totals
- Payroll warnings
- Review status
- Employee time cards
- Exact payroll-period association
- Partial-overlap warnings
- Payroll-period workflow status
- Approval and lock metadata
- Open and total exception counts
- Protected-period warnings
- Links to associated payroll-period details

### CSV Exports

- Daily payroll CSV
- Weekly payroll CSV
- Payroll Workspace CSV
- Consistent payroll categories
- Report-level totals
- Payroll-period association metadata
- Workflow status
- Exception counts
- Approval and lock metadata
- Partial-overlap notices

### PDF Documents

- Daily payroll PDF
- Weekly payroll-register PDF
- Payroll Workspace PDF
- Employee time-card PDF
- Company identity
- Report period
- Company timezone
- Payroll warnings
- Review status
- Employee and supervisor signature lines
- Payroll-period association metadata
- Workflow status
- Approval and lock metadata
- Exception counts
- Partial-overlap warnings
- Approved and locked punch-protection notices

### Email Reporting

- Manual daily payroll email
- Automatic scheduled daily payroll email
- Multiple notification recipients
- Database-managed recipient subscriptions
- Email delivery history
- SMTP delivery logging
- Company-aware report subjects
- Payroll totals and review status
- Scheduler duplicate-send protection
- Payroll-period association information
- Payroll workflow status
- Approval and lock metadata
- Open and total exception counts
- Partial-overlap and no-association notices
- Protected-period notices

### Operations Dashboard

The supervisor dashboard displays:

- Active employees
- Total employees
- Employees currently clocked in
- Today’s punch count
- Recent punch activity
- Current-week payroll totals
- Payroll issues requiring review
- Automatic-report status
- Last email-report status
- Links to reports, labor rules, and administration tools

---

## Backup and Recovery

IQwurksPunch includes a verified SQLite backup and restore system.

### Backup Commands

```bash
./iqwurks backup:create
./iqwurks backup:list
./iqwurks backup:verify
./iqwurks backup:verify --all
./iqwurks backup:verify BACKUP_FILENAME
./iqwurks backup:prune --keep=30
./iqwurks backup:prune --keep=30 --delete
./iqwurks backup:run
```

`backup:run`:

1. Creates a timestamped SQLite backup.
2. Verifies database integrity.
3. Checks for foreign-key violations.
4. Applies the configured retention policy.
5. Records operational results.

The default backup directory is:

```text
storage/backups
```

The default retention target is:

```text
30 backups
```

### Restore Preview

```bash
./iqwurks backup:restore BACKUP_FILENAME
```

A restore preview verifies the selected backup and displays the intended restore operation without changing the active database.

### Apply a Restore

```bash
./iqwurks backup:restore \
    BACKUP_FILENAME \
    --apply \
    --confirm=BACKUP_FILENAME
```

Restore protection includes:

- Exact filename confirmation
- Backup integrity verification
- Foreign-key verification
- Maintenance-mode integration
- Pre-restore safety backup
- SQLite WAL checkpoint handling
- Removal of stale sidecar files
- Post-restore validation
- Migration-history validation
- Rollback handling

---

## Diagnostics

### Database Health

```bash
./iqwurks database:check
```

Checks include:

- Database availability
- Read and write permissions
- SQLite version
- Journal mode
- Integrity status
- Foreign-key violations
- Application table count
- Migration count
- Page utilization
- File size

### Scheduler Health

```bash
./iqwurks scheduler:check
```

Checks include:

- Cron installation
- Scheduler lock
- Application-log activity
- Cron-output activity
- Recent failures
- Company timezone
- Report schedule
- Notification recipients
- Last scheduled delivery

### Mail Health

```bash
./iqwurks mail:check
```

Checks include:

- SMTP configuration
- Hostname resolution
- Network connectivity
- TLS readiness
- Mail transport readiness
- Delivery-log activity

### System Doctor

```bash
./iqwurks doctor
```

The System Doctor checks:

- Application version
- PHP version
- Required PHP extensions
- Composer dependencies
- Configuration files
- Runtime directories
- Disk capacity
- Maintenance mode
- Database health
- Migration history
- Verified backups
- Scheduler configuration
- Backup cron
- Scheduler activity
- Mail activity

Results are classified as:

```text
PASS
WARN
FAIL
```

---

## Maintenance Mode

Enable maintenance mode:

```bash
./iqwurks maintenance:on
```

Enable it with a reason:

```bash
./iqwurks maintenance:on Planned database maintenance
```

Check status:

```bash
./iqwurks maintenance:status
```

Disable maintenance mode:

```bash
./iqwurks maintenance:off
```

Maintenance state is stored in:

```text
storage/cache/maintenance.json
```

---

## Console Commands

Display console help:

```bash
./iqwurks help
```

Available commands:

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

Display the installed version:

```bash
./iqwurks version
```

The current console does not implement a universal per-command `--help` option.

---

## Production Deployment

The validated Version 0.7 production deployment uses:

- Ubuntu Linux
- Nginx
- PHP-FPM 8.5
- SQLite
- Cron
- Logrotate
- LightDM
- Openbox
- Chromium
- UFW
- Netdata

### Application URLs

Employee kiosk:

```text
http://SERVER_ADDRESS/kiosk
```

Supervisor login:

```text
http://SERVER_ADDRESS/login
```

Operations dashboard:

```text
http://SERVER_ADDRESS/dashboard
```

The validated installation uses:

```text
http://192.168.1.82/kiosk
http://192.168.1.82/login
http://192.168.1.82/dashboard
```

These addresses are deployment-specific and should be changed to match the target system.

### Web Server

The production Nginx configuration:

- Serves the `public` directory
- Routes application requests through `public/index.php`
- Uses PHP-FPM
- Blocks arbitrary PHP execution
- Blocks hidden files
- Serves static assets directly
- Adds security-related headers
- Disables server-version disclosure

### Scheduled Reports

The production scheduler runs once per minute:

```cron
* * * * * cd /var/www/IQwurksPunch && /usr/bin/flock -n /tmp/iqwurks-scheduler.lock /usr/bin/php iqwurks schedule:run >> storage/logs/cron-scheduler.log 2>&1
```

### Scheduled Backups

The validated daily backup schedule is:

```cron
CRON_TZ=America/Los_Angeles
15 1 * * * cd /var/www/IQwurksPunch && /usr/bin/flock -n /tmp/iqwurks-backup-cron.lock /usr/bin/php iqwurks backup:run >> storage/logs/cron-backup.log 2>&1
```

Adjust the timezone and schedule for the deployment.

---

## Dedicated Physical Kiosk

The validated physical kiosk uses:

- A restricted Linux account named `kiosk`
- LightDM automatic login
- Openbox
- Chromium full-screen kiosk mode
- Automatic browser restart
- Application-availability checks
- Disabled screen blanking
- Disabled display power management
- Hidden idle mouse cursor
- Disabled sleep, suspend, and hibernation

The local kiosk opens:

```text
http://127.0.0.1/kiosk
```

If Chromium exits, the launcher restarts it automatically.

If IQwurksPunch is unavailable during startup, the launcher waits and retries until the application responds.

---

## SQLite Configuration

The production SQLite connection enables:

```sql
PRAGMA foreign_keys = ON;
PRAGMA busy_timeout = 10000;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA wal_autocheckpoint = 1000;
```

WAL mode improves concurrency among:

- Employee kiosk requests
- Supervisor activity
- Scheduled reports
- Backup operations
- Diagnostic commands

Backup and restore operations are WAL-aware.

---

## Local Frontend Assets

Bootstrap and Bootstrap Icons are stored locally under:

```text
public/assets/vendor
```

The application does not require an external frontend content-delivery network for its primary interface assets.

Verify vendored asset integrity from the project root:

```bash
sha256sum --check public/assets/vendor/SHA256SUMS
```

Vendored content includes:

- Bootstrap CSS
- Bootstrap JavaScript bundle
- Bootstrap Icons CSS
- Bootstrap Icons font files
- License files
- SHA-256 checksum manifest

---

## Logging

Application logs are stored under:

```text
storage/logs
```

Examples include:

```text
scheduler.log
cron-scheduler.log
cron-backup.log
mail.log
php-fpm-error.log
```

The production logrotate policy provides:

- Daily rotation
- Rotation at 5 MB
- Compression
- Date-based archive names
- Thirty application-log rotations
- Fourteen kiosk-browser-log rotations
- Appropriate file ownership and permissions

A newly rotated empty mail log may temporarily produce a System Doctor warning until the next successful email delivery.

---

## Security

Version 0.7 security protections include:

- Centralized CSRF protection for every POST request
- Cryptographically secure session-bound CSRF tokens
- Constant-time CSRF token comparison
- Automatic CSRF fields in rendered POST forms
- POST-only logout
- Removal of the obsolete state-changing email GET route
- Database-backed workflow authorization
- Active-account revalidation for payroll workflow actions
- Administrator and supervisor role enforcement
- Supervisor authentication guard
- Database revalidation of authenticated users
- Active-account verification
- Admin and supervisor role restrictions
- Session regeneration after login
- Session destruction during logout
- HTTP-only cookies
- SameSite cookie policy
- Strict session mode
- Local session storage
- Hidden SMTP configuration
- Protected runtime files
- Public kiosk route allowlist
- Server-side kiosk timeout enforcement
- Typed restore confirmation
- Typed punch-deletion confirmation
- Immutable correction history
- Nginx hidden-file protection
- Restricted PHP execution
- LAN firewall restrictions

The validated deployment uses HTTP on a trusted local network. HTTPS should be added before exposing the application across an untrusted network.

---

## Requirements

Minimum application requirements:

- Linux
- PHP 8.5 or later
- SQLite
- Composer
- Cron
- SMTP account for email delivery

Recommended production components:

- Nginx
- PHP-FPM
- Logrotate
- UFW or another firewall
- LightDM, Openbox, and Chromium for a physical kiosk
- Netdata or another monitoring system

---

## Installation Summary

A complete production installation generally requires:

1. Install PHP, SQLite, Composer, Nginx, and PHP-FPM.
2. Place the application under the web root.
3. Install Composer dependencies.
4. Create the local SMTP configuration.
5. Set ownership and permissions.
6. Run database migrations.
7. Configure Nginx.
8. Configure PHP-FPM.
9. Install the scheduler cron entry.
10. Install the automatic backup cron entry.
11. Install log rotation.
12. Configure the firewall.
13. Configure the physical kiosk if needed.
14. Run all diagnostics.
15. Run the automated tests.
16. Perform a reboot-recovery test.

Detailed documentation is stored under:

```text
docs/
```

Release notes are stored under:

```text
releases/
```

---

## Automated Tests

Run:

```bash
php vendor/bin/phpunit --display-deprecations
```

Expected Version 0.7 result:

```text
OK (251 tests, 1138 assertions)
```

Version 0.8 test coverage includes:

- Payroll-period review, approval, locking, and reopening
- Payroll-exception synchronization and resolution
- Protected payroll-period punch correction
- Manual and scheduled weekly payroll email delivery
- Scheduled payroll-exception delivery
- Payroll-approval notifications
- Operational-failure notifications
- CSV and PDF payroll email attachments
- Email delivery-attempt history
- Scheduled delivery metadata
- Retry planning and execution
- Daily and weekly report-date preservation
- Configurable retry limits and delays
- Retry-chain duplicate prevention
- Exception-report retry closure
- Internal retry execution locking
- Malformed retry-record quarantine
- Unsupported retry-type quarantine
- Attachment normalization and size limits
- Oversized-delivery permanent-failure handling
- Database-backed authorization
- CSRF token handling
- Employee-kiosk Enter-key operation

Every modified PHP file should also pass:

```bash
php -l PATH_TO_FILE.php
```

---

## Project Structure

```text
app/
    Console/
    Controllers/
    Core/
    Helpers/
    Logging/
    Models/
    Payroll/
    Repositories/
    Services/
    Views/

bootstrap/
config/
database/
    migrations/
    sqlite/

docs/
plugins/
public/
    assets/

releases/
routes/
storage/
    backups/
    cache/
    exports/
    logs/
    sessions/

tests/
```

---

## Project Status

Version 0.8 is the current stable release.

It completes the Reporting Automation and Delivery Reliability milestone, including:

- Manual weekly payroll email delivery
- Scheduled weekly payroll delivery
- Scheduled payroll-exception delivery
- Payroll-approval notifications
- Operational-failure notifications
- CSV payroll email attachments
- PDF payroll email attachments
- Structured report-delivery schedules
- Detailed email delivery-attempt history
- Configurable retry policies
- Delayed automatic retry eligibility
- Retry-chain duplicate prevention
- Exception-report retry closure
- Malformed retry-record quarantine
- Unsupported retry-type quarantine
- Permanent oversized-attachment failure handling
- Internal and external retry execution locks
- Employee-kiosk Enter-key operation

The validated Version 0.8 baseline is:

```text
Application version: 0.8.0
Tests: 251
Assertions: 1138
Database tables: 18
Applied migrations: 16
SQLite journal mode: wal
Database integrity: ok
Foreign-key violations: 0
```

Items deferred beyond Version 0.8 include:

- Advanced payroll-period filtering
- Department-level payroll-review summaries
- Employee-level review-completion tracking
- Workflow-aware export filename suffixes
- Employee time-card email attachments
- User-selectable attachment formats
- Long-term report attachment archives
- Standalone delivery-status administration dashboard
- Multiple delivery times for one report type
- Named recipient groups
- External payroll-provider export profiles
- Guided installation and upgrade automation
- Release packaging

---

## Project Goals

IQwurksPunch is designed to provide:

- No recurring software subscription fee
- Simple employee operation
- Professional supervisor tools
- Accurate payroll preparation
- Reliable local operation
- Recoverable business data
- Transparent audit history
- Long-term maintainability
- Open-source availability

---

## License

IQwurksPunch is released under the MIT License.
