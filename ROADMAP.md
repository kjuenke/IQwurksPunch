# IQwurksPunch Roadmap

IQwurksPunch is an open-source employee time-clock and payroll-preparation system designed for a dedicated Linux kiosk.

This roadmap describes the intended development direction. Features may move between releases as operational requirements, testing, deployment experience, and product priorities evolve.

---

## Version 0.4 — Payroll Engine and Operations

**Status: Completed — July 15, 2026**

Version 0.4 established the production payroll engine and live administrative operations foundation.

Completed work:

### Payroll Engine

- Daily payroll calculation
- Weekly payroll calculation
- Daily overtime calculation
- Weekly overtime calculation
- Punch rounding
- Automatic meal deduction
- Paid-break allowance
- Company-timezone-aware payroll boundaries
- Incomplete-shift detection
- Payroll review warnings

### Payroll Exports

- Daily payroll CSV export
- Weekly payroll CSV export
- Consistent payroll categories
- Company and report-period information

### Notification Center

- Database-managed recipients
- Daily payroll subscriptions
- Weekly payroll subscriptions
- Exception-report subscriptions
- Recipient activation and deactivation
- Recipient deletion
- SMTP recipient separation from transport configuration

### Employee Lifecycle

- Employee activation
- Employee deactivation
- Payroll-history preservation
- Protected permanent deletion
- Employee-status visibility

### Operations Dashboard

- Active and total employee counts
- Employees currently clocked in
- Today’s punch count
- Current-week payroll totals
- Payroll issues requiring review
- Recent punch activity
- Automatic-report status
- Last email-report status

### Automated Testing

- Payroll calculation tests
- Rounding tests
- Meal-deduction tests
- Paid-break tests
- Overtime tests
- Employee lifecycle validation
- Notification-recipient validation

---

## Version 0.5 — Reporting and Payroll Documents

**Status: Completed — July 20, 2026**

Version 0.5 expanded IQwurksPunch into a complete payroll-preparation and reporting workspace.

Completed work:

### Payroll Workspace

- Date-range payroll reports
- Employee filtering
- Department filtering
- Report-level payroll totals
- Employee daily-detail sections
- Payroll-week summaries
- Payroll warnings
- Review status

### Configurable Labor Rules

- Dedicated Labor Rules administration page
- Configurable daily overtime threshold
- Configurable weekly overtime threshold
- Configurable double-time threshold
- Configurable Sunday or Monday workweek start
- Persistent labor-rule storage
- Labor Rules repository and service layers
- Labor Rules controller and administration view
- Validation of overtime and double-time thresholds

### Payroll Classification

- Regular hours
- Daily overtime hours
- Weekly overtime hours
- Double-time hours
- Total overtime hours
- Premium hours
- Total payable hours
- Prevention of overtime double counting

Payroll totals use these relationships:

```text
Total Overtime = Daily Overtime + Weekly Overtime

Premium Hours = Total Overtime + Double-Time

Payable Hours = Regular + Total Overtime + Double-Time
```

### CSV Exports

- Daily payroll CSV
- Weekly payroll CSV
- Payroll Workspace CSV
- Consistent payroll categories
- Report-level totals

### PDF Documents

- Daily payroll PDF
- Weekly payroll-register PDF
- Payroll Workspace PDF
- Employee time-card PDF
- Company identity
- Report periods
- Company timezone
- Payroll warnings
- Review status
- Employee and supervisor signature lines

### Dashboard and Email

- Expanded current-week dashboard totals
- Daily overtime display
- Weekly overtime display
- Double-time display
- Total overtime display
- Premium-hour display
- Total-payable-hours display
- Expanded daily payroll email content
- Email report-level totals
- Payroll issue counts in email reports
- Labor Rules dashboard navigation

### Automated Testing

- Configurable labor-rule tests
- Daily overtime classification tests
- Weekly overtime conversion tests
- Double-time classification tests
- Premium-hour tests
- Payroll-total reconciliation tests
- No-double-counting assertions
- Sunday and Monday workweek validation
- 28 tests
- 116 assertions

---

## Version 0.6 — Reliability, Recovery, and Production Kiosk

**Status: Completed — July 21, 2026**

Version 0.6 established the production reliability, recovery, security, and physical-kiosk foundation.

It also completed significant portions of the originally planned Payroll Administration and Installation Readiness milestones.

Completed work:

### Database Backup

- Timestamped SQLite backups
- Verified backup creation
- SQLite integrity checks
- Foreign-key validation
- Backup listing
- Backup catalog service
- Configurable retention count
- Retention preview
- Explicit retention deletion
- Backup locking
- Backup logging
- Backup failure reporting
- Automatic daily backup cron
- WAL-safe backup creation

### Database Restore

- Restore preview
- Backup integrity verification
- Foreign-key verification
- Exact filename confirmation
- Explicit restore application
- Pre-restore safety backup
- Maintenance-mode integration
- WAL checkpoint handling
- Stale sidecar-file cleanup
- Post-restore integrity checks
- Migration-history verification
- Restore rollback handling

### Database Health

- Database existence checks
- File readability checks
- File writability checks
- Directory writability checks
- SQLite version reporting
- Journal-mode reporting
- Integrity checks
- Foreign-key checks
- Application-table counts
- Migration counts
- Page-usage information
- Database-size reporting

### Application Diagnostics

- System Doctor command
- Scheduler diagnostic command
- Mail diagnostic command
- PHP-version checks
- PHP-extension checks
- Composer dependency checks
- Configuration-file checks
- Runtime-directory checks
- Disk-capacity checks
- Maintenance-mode checks
- Database-health checks
- Migration-history checks
- Verified-backup checks
- Scheduler activity checks
- Backup-cron checks
- Mail-activity checks

### Maintenance Mode

- Enable maintenance mode
- Optional maintenance reason
- Maintenance-status reporting
- Disable maintenance mode
- Retry interval
- Maintenance-state persistence
- Web maintenance response
- Console recovery availability

### Supervisor Punch Correction

- Employee punch-history review
- Manual punch entry
- Punch editing
- Punch deletion
- Required correction reasons
- Typed deletion confirmation
- Punch-sequence validation
- Transaction rollback on invalid changes
- Supervisor identification
- Correction timestamps
- Application audit records
- Immutable correction-history records

Supported punch types:

```text
clock_in
clock_out
break_out
break_in
meal_out
meal_in
```

### Authentication Hardening

- Centralized supervisor route guard
- Database revalidation of authenticated users
- Active-account checks
- Admin and supervisor role restrictions
- Session regeneration after login
- Intended-page preservation
- Complete logout cleanup
- Local application session storage
- HTTP-only cookies
- SameSite cookie protection
- Strict session mode
- Supervisor activity tracking

### Kiosk Inactivity Protection

- Configurable kiosk inactivity timeout
- Allowed range of 15 to 600 seconds
- Default timeout of 60 seconds
- Final 10-second warning
- Keyboard activity detection
- Mouse and pointer activity detection
- Touch activity detection
- Form-input activity detection
- Automatic clearing of unfinished transactions
- Selected-employee cleanup
- PIN cleanup
- Temporary-authentication cleanup
- Automatic return to the employee-number screen
- Server-side expired-form protection
- No automatic punch submission during timeout

### Production Web Deployment

- Nginx deployment
- PHP-FPM deployment
- Public-directory document root
- Front-controller routing
- Arbitrary PHP execution protection
- Hidden-file protection
- Security-related response headers
- Static-asset caching
- Local-network access
- LAN firewall restrictions

### Dedicated Physical Kiosk

- Restricted `kiosk` Linux account
- LightDM automatic login
- Openbox session
- Chromium full-screen kiosk mode
- Local kiosk URL
- Browser restart loop
- Application-availability checks
- Startup retry handling
- Hidden idle cursor
- Disabled screen blanking
- Disabled display power management
- Disabled suspend
- Disabled sleep
- Disabled hibernation
- Reboot recovery validation

### SQLite Reliability

- Foreign-key enforcement
- Ten-second busy timeout
- WAL journal mode
- Normal synchronous mode
- Automatic WAL checkpoint configuration
- Group-writable SQLite sidecar files
- WAL-aware backup operations
- WAL-aware restore operations

### Logging and Runtime Reliability

- Application log rotation
- Scheduler log rotation
- Cron log rotation
- Mail log rotation
- PHP-FPM application error log
- Physical-kiosk browser log rotation
- Daily rotation
- Size-based rotation
- Compression
- Date-based archive names
- Runtime-file Git protection
- Session-file Git protection
- Backup-file Git protection
- Temporary-file Git protection

### Offline Frontend Assets

- Local Bootstrap CSS
- Local Bootstrap JavaScript bundle
- Local Bootstrap Icons CSS
- Local Bootstrap Icons fonts
- Vendored license files
- SHA-256 checksum manifest
- Removal of primary external frontend CDN dependencies
- Offline kiosk interface support

### Console Application

Available Version 0.6 commands:

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

### Automated Testing

- Punch insertion correction tests
- Punch editing correction tests
- Punch deletion correction tests
- Required-reason tests
- Punch-sequence validation tests
- Immutable-history tests
- Transaction rollback tests
- 36 tests
- 188 assertions

### Production Validation

- Database health passed
- SQLite integrity returned `ok`
- Foreign-key violations returned zero
- WAL mode confirmed
- WAL-safe backup behavior confirmed
- Backup verification passed
- Scheduler diagnostics passed
- System Doctor completed without failures
- Maintenance recovery verified
- Authentication guarding verified
- Punch correction verified
- Kiosk timeout verified
- Nginx and PHP-FPM verified
- Local assets verified
- Log rotation verified
- Chromium restart recovery verified
- LightDM recovery verified
- Full reboot recovery verified
- Local kiosk access verified
- LAN kiosk access verified
- Supervisor login verified
- Scheduler recovery verified
- Backup scheduling verified

---

## Version 0.7 — Payroll Review and Approval

**Status: Completed — July 27, 2026**

Version 0.7 introduces an explicit, auditable payroll-review workflow built around payroll periods, exception resolution, approval, locking, reopening, and protected payroll records.

Completed work:

### Payroll Periods

- Company-local inclusive payroll-period date ranges
- Payroll-period names
- Maximum period duration of 31 calendar days
- Overlapping-period prevention
- Newest-first payroll-period list
- Payroll-period creation page
- Payroll-period detail page
- Status badges
- Direct links to matching Payroll Workspace reports

Payroll-period states:

```text
open
under_review
approved
locked
```

### Payroll Review Workflow

- Begin formal payroll review
- Return a period to open
- Approve an under-review period
- Lock an approved period
- Reopen approved payroll for review
- Reopen locked payroll for review
- Explicit approval confirmation
- Exact `LOCK` confirmation
- Required reopening reason
- Minimum reopening-reason length of 10 characters
- Maximum workflow-reason length of 1,000 characters
- Concurrency-aware state transitions
- Transactional workflow updates

### Payroll Approval

- Approval restricted to under-review periods
- Approval blocked by unresolved exceptions
- Approving-supervisor identification
- Approval timestamp
- Required approval confirmation
- Approval metadata in reports
- Approval metadata in exports
- Approval metadata in email reports

### Payroll Locking

- Locking restricted to approved periods
- Exact typed `LOCK` confirmation
- Locking-supervisor identification
- Lock timestamp
- Final payroll-state visibility
- Protected punch dates
- Lock metadata in reports and exports

### Payroll Reopening

- Approved periods can be reopened
- Locked periods can be reopened
- Required reopening reason
- Reopening returns the period to `under_review`
- Active approval metadata is cleared
- Active lock metadata is cleared
- Prior approval and lock events remain in immutable history

Fields cleared during reopening:

```text
approved_by_user_id
approved_at
locked_by_user_id
locked_at
```

### Immutable Workflow History

- Period-creation history
- Review-start history
- Return-to-open history
- Approval history
- Lock history
- Reopening history
- Review-note history
- Exception-resolution history
- Exception-acceptance history
- Acting-user identification
- Previous-status records
- New-status records
- Preserved workflow reasons
- Chronological workflow display

### Supervisor Review Notes

- Immutable review notes
- Author identification
- Note timestamps
- Notes permitted while open or under review
- Required nonblank notes
- Maximum length of 1,000 characters
- Matching browser and server validation
- Transactional note and history creation

### Payroll Exception Review

- Exception synchronization from Payroll Workspace results
- Stable SHA-256 exception keys
- Open exception counts
- Total exception counts
- Exception refresh
- Exception resolution
- Exception acceptance
- Optional resolution notes
- Required accepted-exception explanations
- Minimum accepted-exception explanation length of 10 characters
- Maximum exception-note length of 1,000 characters
- Reopening of resolved exceptions when an issue returns
- Preservation of accepted exceptions during refresh
- Transactional exception and history updates

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

Exception states:

```text
open
resolved
accepted
```

### Punch Protection

- Company-timezone conversion of stored UTC timestamps
- Protected-period lookup by company-local date
- Punch creation blocked in approved periods
- Punch creation blocked in locked periods
- Punch editing blocked in approved periods
- Punch editing blocked in locked periods
- Punch deletion blocked in approved periods
- Punch deletion blocked in locked periods
- Original and proposed dates checked during editing
- Service-layer enforcement
- Shared payroll-period protection service

### Payroll Report Integration

- Exact payroll-period report association
- Partial-overlap detection
- Explicit no-association status
- Period ID and period name
- Period date range
- Workflow status
- Creation metadata
- Review metadata
- Approval metadata
- Lock metadata
- Open exception count
- Total exception count
- Approval-blocked state
- Protected-period warnings
- Links to payroll-period details

### CSV, PDF, and Email Integration

- Payroll Workspace CSV workflow metadata
- Payroll Workspace PDF workflow metadata
- Employee time-card PDF workflow metadata
- Partial-overlap warnings
- No-association notices
- Approved-period protection notices
- Locked-period protection notices
- Daily payroll email workflow metadata
- Manual email-report integration
- Scheduled email-report integration
- Report-generation logging metadata

### Authorization and Security

- Database-backed workflow authorization
- Active-account validation
- Administrator authorization
- Supervisor authorization
- Session synchronization with database records
- Controller-level database revalidation
- Centralized CSRF service
- Secure session-bound CSRF tokens
- Automatic CSRF fields in POST forms
- Central POST-request CSRF enforcement
- POST-only logout
- Removal of the legacy state-changing email GET route

### Database

Added migration:

```text
012_create_payroll_review_tables.php
```

Added tables:

```text
payroll_periods
payroll_period_history
payroll_review_notes
payroll_exception_resolutions
```

Validated database baseline:

```text
16 tables
12 applied migrations
SQLite journal mode: wal
Database integrity: ok
Foreign-key violations: 0
```

### Automated Testing

Version 0.7 adds tests for:

- Period creation
- Date validation
- Period-duration validation
- Overlap prevention
- Workflow transitions
- Approval blocking
- Lock confirmation
- Reopening validation
- Metadata clearing
- Immutable history
- Transaction rollback
- Concurrency protection
- Punch protection
- Company-timezone protection
- Exception synchronization
- Exception resolution
- Exception acceptance
- Review-note validation
- Report metadata
- CSV metadata
- PDF metadata
- Email metadata
- Database-backed authorization
- CSRF token handling

Final release test baseline:

```text
136 tests
693 assertions
```

### Deferred Beyond Version 0.7

The following items remain planned for later releases:

- Advanced payroll-period filtering
- Dedicated standalone workflow-history page
- Dedicated standalone reopen page
- Department-level payroll-review summaries
- Employee-level review-completion tracking
- Workflow-status suffixes in export filenames
- External payroll-provider export profiles
- Configurable payroll export mappings
- Manual weekly payroll email delivery
- Scheduled weekly payroll email delivery
- Scheduled exception-report delivery
- PDF and CSV email attachments

---
## Version 0.8 — Reporting Automation and Delivery Reliability

**Status: Completed — July 31, 2026**

Version 0.8 expands report scheduling and email delivery into an auditable, retry-aware reporting system.

Completed work:

### Additional Email Reports

- Manual weekly payroll email delivery
- Scheduled weekly payroll email delivery
- Scheduled payroll-exception delivery
- Payroll-approval notifications
- Operational-failure notifications
- Company-timezone report generation
- Payroll workflow metadata in daily and weekly reports

### Structured Report Scheduling

- Independent daily payroll schedules
- Independent weekly payroll schedules
- Independent payroll-exception schedules
- Schedule activation and deactivation
- Delivery-time configuration
- Weekly day-of-week configuration
- Weekday-only scheduling where applicable
- Last-delivery timestamp and result
- Duplicate-send prevention by report type
- Schedule identifiers in delivery history

### Notification Center Expansion

- Daily payroll subscriptions
- Weekly payroll subscriptions
- Exception-report subscriptions
- Approval-notification subscriptions
- Operational-failure subscriptions
- Active-recipient enforcement
- Notification-type allowlisting

### Payroll Email Attachments

- Daily payroll CSV attachments
- Daily payroll PDF attachments
- Weekly payroll CSV attachments
- Weekly payroll PDF attachments
- Shared in-memory attachment generation
- Safe filenames and content types
- Attachment count, filename, and size history

### Attachment Safety

- Centralized attachment-size policy
- Default combined raw limit of 10 MiB
- Configuration validation
- Enforcement before SMTP setup
- Permanent oversized-delivery failures
- Oversized attempt history
- Oversized retry exclusion

### Delivery Attempt History

- Pending, sent, and failed states
- Manual, scheduled, system, and retry sources
- Attempt-number tracking
- Maximum-attempt tracking
- Retry-parent relationships
- Permanent-failure status
- Error-message history
- Attachment metadata
- Start and completion timestamps

### Delivery Retry Policy

- Retry-plan validation
- Daily payroll retry execution
- Weekly payroll retry execution
- Exception-report retry execution
- Original daily report-date preservation
- Original weekly reference-date preservation
- Configurable attempt maximums
- Configurable batch limits
- Configurable retry delays
- Preview-only retry mode
- Explicit send mode
- Automatic retry cron integration

### Retry Safety

- Delayed eligibility after temporary failures
- Duplicate retry-child prevention
- Internal nonblocking retry lock
- External cron lock
- Malformed retry-record quarantine
- Unsupported notification-type quarantine
- Permanent-failure exclusion
- Exception-report closure messages

### Kiosk Usability

- Automatic focus on the available punch-action button
- Enter-key activation of Clock In or Clock Out
- Existing mouse and touch operation preserved

### Database

Added migrations:

```text
013_create_report_delivery_schedules.php
014_add_approval_notifications.php
015_add_operational_failures.php
016_create_email_delivery_attempts.php
```

Added primary tables:

```text
report_delivery_schedules
email_delivery_attempts
```

Validated Version 0.8 database baseline:

```text
18 tables
16 applied migrations
SQLite journal mode: wal
Database integrity: ok
Foreign-key violations: 0
```

### Automated Testing

Version 0.8 adds tests for:

- Weekly payroll email delivery
- Scheduled weekly reports
- Scheduled exception reports
- Approval notifications
- Operational-failure notifications
- CSV and PDF attachments
- Attachment normalization
- Attachment-size policy
- Oversized delivery handling
- Delivery-attempt history
- Retry-parent relationships
- Retry planning and execution
- Retry policy and delay
- Retry command behavior
- Retry locking
- Retry quarantine
- Exception-report closure
- Unsupported retry types

Final release test baseline:

```text
251 tests
1138 assertions
```

### Deferred Beyond Version 0.8

The following items remain planned for later releases:

- Employee time-card email attachments
- User-selectable attachment formats
- Long-term report attachment archives
- Standalone delivery-status dashboard
- Multiple delivery times for one report type
- Named recipient groups
- Advanced payroll-period filtering
- Department-level payroll-review summaries
- Employee-level review-completion tracking
- Workflow-status suffixes in export filenames
- External payroll-provider export profiles
- Configurable payroll export mappings

---

## Version 0.9 — Installation, Upgrade, and Packaging

**Status: Completed — August 5, 2026**

Version 0.9 converts the validated production deployment into a repeatable installation, controlled-upgrade, release-packaging, and recovery workflow.

Completed work:

### Installation Diagnostics

- Installation preflight service
- `install:check` console command
- Operating-system validation
- PHP availability and version validation
- Required PHP-extension validation
- Required system-command validation
- Application-source validation
- Composer dependency validation
- Runtime-directory validation
- Runtime-directory permission validation
- Available-disk-space validation
- PASS, WARN, and FAIL readiness reporting
- Read-only installation diagnostics

### Upgrade Readiness and Planning

- Upgrade-readiness service
- `upgrade:check` console command
- Installed-version inspection
- Database availability and integrity review
- Migration-state review
- Composer-state review
- Maintenance-mode review
- Backup-readiness review
- Upgrade-journal review
- Blocking-failure reporting
- Nonblocking warning reporting
- Read-only upgrade planning
- `upgrade:plan` console command
- Planned-operation sequencing
- Controlled upgrade preview
- `upgrade:preview` console command
- Required confirmation-phrase reporting

### Controlled Upgrade Execution

- Safe process-runner interface
- Shell-bypassing process execution
- Argument-array command execution
- Standard-output capture
- Standard-error capture
- Exit-code capture
- Working-directory support
- Environment-variable overrides
- Controlled process timeouts
- Exit code `124` for timed-out processes
- Guarded upgrade operations
- Ordered upgrade execution
- Exact confirmation enforcement
- Blocking-operation failure handling
- `upgrade:apply` console command

### Upgrade Journaling and Recovery

- Persistent upgrade-execution journal
- Execution identifiers
- Start and completion timestamps
- Current and target version metadata
- Planned-operation counts
- Completed-operation counts
- Failure metadata
- Final execution status
- `upgrade:status` console command
- Persisted-status correction
- Interrupted-upgrade assessment
- Recovery-required state
- `upgrade:recovery-check` console command
- Read-only recovery diagnostics

### Distribution Package Planning

- Authoritative package-inclusion plan
- Required source-path validation
- Required root-file validation
- Deployment-directory inclusion
- Empty runtime-directory inclusion
- Private-data exclusions
- Generated-data exclusions
- Unsafe-entry detection
- Source-file counts
- Directory counts
- Source-byte totals
- Read-only package preview

### Portable Package Manifest

- `PACKAGE-MANIFEST.json`
- Manifest schema version
- Application version
- Package base name
- Package filename
- Deterministic manifest ordering
- Entry-type metadata
- Packaged permission modes
- SHA-256 file digests
- File and directory inventory

### Controlled Package Staging

- Temporary release-staging workspace
- Normalized source-directory permissions
- Normalized runtime-directory permissions
- Normalized source-file permissions
- Preserved executable application entry points
- Empty generated runtime directories
- Temporary-workspace cleanup

Normalized package modes:

```text
Source directories:             0755
Generated runtime directories:  0770
Normal source files:            0644
Application entry points:       0750
```

### Versioned Distribution Archives

- GNU TAR archive generation
- Versioned archive filenames
- Deterministic ownership metadata
- Deterministic timestamp handling
- Controlled permission preservation
- `package:build` console command
- Preview-only default mode
- Explicit `--build` execution
- Generated-package Git ignore rules
- Package output under `storage/exports/packages`

Version 0.9 corrected release-package preview baseline:

```text
395 manifest entries
321 source files
74 directories
6 generated runtime directories
```

These counts include the complete tracked `releases/` directory and the required Version 0.9 release note.

### Independent Package Verification

- `package:verify` console command
- Optional expected SHA-256 verification
- Archive readability checks
- Safe relative-path validation
- Single top-level package-root enforcement
- Package-root validation
- Archive-filename validation
- Manifest JSON validation
- Manifest-schema validation
- Application-version validation
- Required installation-path validation
- File SHA-256 validation
- File permission validation
- Directory permission validation
- Unlisted-entry detection
- Empty runtime-directory validation
- Forbidden-path validation
- Symbolic-link rejection
- Temporary extraction-workspace cleanup
- Required deployment-documentation validation
- Required version-matched release-note validation
- Rejection of stale packages without deployment templates

Required package paths include:

```text
PACKAGE-MANIFEST.json
CHANGELOG.md
composer.json
composer.lock
config/mail.example.php
deployment/README.md
iqwurks
LICENSE
migrate.php
README.md
ROADMAP.md
VERSION
```

### Package Privacy and Safety

Excluded package content includes:

```text
.git
.env
config/mail.php
firewall-rules.txt
installed-packages.txt
vendor
```

Runtime exclusions include:

- Active SQLite databases
- SQLite WAL files
- SQLite shared-memory files
- SQLite journal files
- Backups
- Cached data
- Generated exports
- Application logs
- Session data

Additional protections include:

- No symbolic links
- No unsafe archive paths
- No world-writable archive entries
- Exactly one top-level package directory
- Verified packaged permission modes
- Verified file checksums

### Deployment Templates

Added reusable templates:

```text
deployment/README.md
deployment/cron/iqwurks-punch.crontab
deployment/kiosk/iqwurks-kiosk-browser
deployment/lightdm/50-iqwurks-kiosk.conf
deployment/logrotate/iqwurks-punch
deployment/nginx/iqwurks-punch.conf
deployment/openbox/autostart
deployment/php-fpm/99-iqwurks-punch.ini
deployment/ufw/README.md
```

Deployment placeholders include:

```text
{{APPLICATION_ROOT}}
{{CHROMIUM_BINARY}}
{{KIOSK_HOME}}
{{KIOSK_URL}}
{{KIOSK_USER}}
{{PHP_FPM_SOCKET}}
{{SERVER_NAMES}}
{{TIMEZONE}}
{{TRUSTED_SUBNET}}
```

Template coverage includes:

- Nginx
- PHP-FPM
- Scheduler cron
- Email-retry cron
- Backup cron
- Log rotation
- Trusted-network firewall guidance
- LightDM automatic login
- Openbox kiosk startup
- Chromium kiosk launching
- Browser restart and recovery

### Installation and Administrative Documentation

- Package-oriented installation guide
- Release-checksum verification
- Independent package verification
- Archive extraction
- Composer dependency installation
- Filesystem ownership and permissions
- Installation preflight
- SMTP configuration
- Database initialization and migration
- PHP-FPM configuration
- Nginx configuration
- Initial supervisor setup
- Company configuration
- Scheduler cron
- Email-retry cron
- Backup cron
- Log rotation
- Firewall configuration
- Kiosk deployment
- Diagnostic commands
- Automated testing
- Controlled upgrade workflow
- Upgrade status
- Interrupted-upgrade recovery
- Rollback guidance
- Package creation
- Package verification
- Release validation
- Security review
- Runtime-file documentation
- System-file documentation
- Known limitations

Primary documentation:

```text
docs/Installation.md
releases/0.9.0.md
```

### GitHub Distribution

- GitHub repository created
- Main branch uploaded
- Maintained release branches uploaded
- Historical release tags uploaded
- Version 0.9 feature branch uploaded
- Dedicated server SSH key configured
- Local and remote branch identity verified
- Repository security audit completed
- Obsolete empty development database removed from tracking
- Active production database confirmed ignored
- SMTP configuration confirmed ignored
- Machine inventory files confirmed untracked
- Explicit storage-root SQLite ignore rules added

### Automated Testing

Version 0.9 adds tests for:

- Installation preflight
- Upgrade readiness
- Upgrade planning
- Upgrade preview
- Exact confirmation
- Safe process execution
- Process output capture
- Process timeouts
- Guarded upgrade operations
- Controlled upgrade application
- Upgrade journals
- Upgrade status
- Interrupted-upgrade assessment
- Recovery checks
- Package planning
- Package manifests
- Controlled staging
- Permission normalization
- Archive generation
- Deterministic archive metadata
- Independent archive verification
- Archive checksum validation
- Archive filename validation
- Required deployment documentation
- Forbidden package paths
- Runtime-data exclusion
- Symbolic-link rejection
- Temporary-workspace cleanup
- Package command argument validation

Final Version 0.9 test baseline:

```text
363 tests
2979 assertions
```

### Deferred Beyond Version 0.9

The following remain planned for Version 1.0 or later:

- Browser-based installation wizard
- Automatic operating-system package installation
- Automatic deployment-template installation
- Automatic Nginx site activation
- Automatic PHP-FPM template activation
- Automatic firewall-rule installation
- Automatic kiosk software installation
- Automatic remote release discovery
- Automatic release downloading
- Cryptographic archive signing beyond SHA-256 publication
- Fully automatic source rollback without administrator review
- Universal per-command `--help`
- Multiple-company deployment management
- Multiple-location deployment management

---

## Version 1.0 — Stable Public Release

**Status: Target**

Target requirements:

- Stable installation process
- Stable upgrade process
- Supported rollback procedure
- Complete administrator documentation
- Complete kiosk deployment documentation
- Complete backup and restore documentation
- Supported release packaging
- Migration and upgrade verification
- Broad automated-test coverage
- Security review
- Authentication review
- Data-integrity review
- Payroll-calculation review
- Punch-correction review
- Recovery testing
- Reboot-recovery testing
- Production operations checklist
- Public open-source release readiness

---

### Payroll Period Management

Version 1.0 will introduce persisted payroll-period records and controlled
administrative lifecycle management.

Completion requirements:

- Administrators can create payroll periods with defined start and end dates.
- The system prevents overlapping periods unless an explicitly approved
  operating rule permits them.
- Draft payroll periods can be edited before they are finalized.
- Draft payroll periods can be permanently deleted after confirmation.
- Deleting a payroll-period record does not delete the underlying employee
  punches.
- Finalized or closed payroll periods cannot be silently deleted.
- Finalized periods can be voided or archived with a required reason.
- Generated CSV and PDF exports are streamed on demand and are not retained as
  payroll-period-linked artifacts.
- Confirmation screens show the date range, employee count, punch count,
  report-artifact tracking status, and current period status.
- Destructive actions require explicit administrator confirmation.
- Payroll-period creation, editing, finalization, deletion, voiding, and
  archival are recorded in the audit log.
- Tests cover authorization, status transitions, deletion protection, punch
  retention, and audit history.

### Supervisor User Management

Version 1.0 will provide administrator-controlled supervisor account
management using the existing user authentication foundation.

Completion requirements:

- Administrators can create supervisor accounts.
- New accounts support username, email address, password, role, and active
  status.
- Usernames remain unique and are validated before an account is created.
- Passwords are securely hashed and are never stored or displayed as plain
  text.
- Administrators can edit supervisor account details.
- Administrators can reset supervisor passwords.
- Administrators can activate or deactivate supervisor accounts.
- Supervisors with operational or audit history are deactivated rather than
  silently deleted.
- The system prevents deactivation or removal of the final active
  administrator.
- The system prevents an administrator from accidentally locking out their
  own active session.
- Role changes require administrator authorization.
- Supervisor users cannot create administrators or elevate their own role
  unless a future permission policy explicitly grants that authority.
- Account creation, role changes, password resets, activation, and
  deactivation are recorded in the audit log.
- The management interface displays account status, role, email address,
  creation information, and last-login information.
- Tests cover authorization, validation, password handling, last-administrator
  protection, account status, and audit history.

## Future Possibilities

These ideas are not committed to a specific release:

- Multiple kiosks
- Multiple physical locations
- Multiple companies
- Shift scheduling
- PTO and vacation tracking
- Holiday rules
- Seventh-day overtime rules
- Alternative overtime policies
- Barcode identification
- RFID identification
- Employee self-service
- Mobile administration
- REST API
- Plugin architecture
- QuickBooks export
- ADP export
- Paychex export
- Additional payroll-provider exports
- Additional database engines
- High-availability deployment
- Remote health monitoring
