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

**Status: Planned**

Version 0.7 is expected to focus on structured payroll review, exception resolution, and approval controls.

Planned work:

### Missing-Punch Review

- Centralized missing-punch queue
- Incomplete-shift review
- Unmatched break review
- Unmatched meal review
- Employee-specific exception pages
- Date-range exception filtering
- Department exception filtering
- Supervisor resolution status
- Required resolution notes

### Payroll Review

- Payroll-period review workspace
- Employee review completion
- Supervisor review notes
- Exception acknowledgment
- Unresolved-issue counts
- Payroll-ready status
- Department-level review summaries
- Review history

### Payroll Approval

- Payroll-period approval
- Approval timestamp
- Approving supervisor
- Approval notes
- Approval validation
- Prevention of approval with unresolved blocking issues
- Approval audit history

### Payroll Locking

- Lock approved payroll periods
- Prevent punch correction in locked periods
- Prevent manual punch creation in locked periods
- Prevent deletion in locked periods
- Lock-aware report warnings
- Lock-aware export behavior
- Lock audit records

### Payroll Reopening

- Reopen approved payroll
- Required reopening reason
- Authorized-role restriction
- Reopening audit history
- Automatic removal of the locked state
- Review-status restoration
- Reapproval workflow

### Employee and Department Detail

- Employee payroll detail pages
- Department payroll summaries
- Department totals
- Employee review notes
- Correction and exception history
- Approval-state visibility

### Export Preparation

- External payroll export profiles
- Configurable column mappings
- Configurable employee identifiers
- Configurable earning codes
- Export validation
- Export preview
- Export audit history

---

## Version 0.8 — Reporting Automation

**Status: Planned**

Version 0.8 is expected to expand report scheduling and delivery.

Planned work:

### Additional Email Reports

- Manual weekly payroll email delivery
- Scheduled weekly payroll email delivery
- Scheduled exception-report delivery
- Payroll-approval notifications
- Backup-failure notifications
- Diagnostic-failure notifications

### Report Scheduling

- Independent schedules by report type
- Multiple delivery times
- Recipient groups
- Day-of-week configuration
- Schedule activation and deactivation
- Last-run status
- Next-run preview
- Duplicate-send prevention by report type

### Attachments

- PDF email attachments
- CSV email attachments
- Employee time-card attachments
- Configurable attachment formats
- Attachment-size validation

### Delivery Reliability

- Delivery retry policy
- Failure notifications
- Delivery-attempt history
- Retry limits
- Permanent-failure status
- Report archive
- Delivery-status dashboard

---

## Version 0.9 — Installation, Upgrade, and Packaging

**Status: Planned**

Version 0.9 is expected to convert the validated production deployment into a repeatable installation and upgrade process.

Planned work:

### Guided Installation

- Environment validation
- PHP requirement validation
- Extension validation
- Directory-permission validation
- Database initialization
- Initial supervisor creation
- Initial company configuration
- SMTP configuration guidance
- Cron installation guidance
- Web-server configuration guidance

### Upgrade Workflow

- Upgrade preflight checks
- Automatic pre-upgrade backup
- Migration preview
- Migration execution
- Post-upgrade database validation
- Application version verification
- Upgrade rollback guidance
- Maintenance-mode automation
- Upgrade logs

### Deployment Documentation

- Nginx configuration template
- PHP-FPM configuration template
- Scheduler cron template
- Backup cron template
- Logrotate template
- Firewall guidance
- Local kiosk configuration
- LightDM configuration
- Openbox configuration
- Chromium launcher
- Reboot-recovery checklist

### Release Packaging

- Versioned release archive
- Dependency installation guidance
- Checksum files
- Release manifest
- Upgrade notes
- Fresh-install notes
- Supported-platform documentation
- Configuration examples

### Administrative Documentation

- Backup administration guide
- Restore procedure
- Disaster-recovery checklist
- Diagnostic-command guide
- Operations troubleshooting guide
- Kiosk recovery guide
- Supervisor security guide

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
