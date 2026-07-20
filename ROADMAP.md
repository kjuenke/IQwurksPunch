# IQwurksPunch Roadmap

IQwurksPunch is an open-source employee time-clock and payroll-preparation system designed for a dedicated Linux kiosk.

This roadmap describes the intended development direction. Features may move between releases as testing, operational requirements, and deployment experience evolve.

---

## Version 0.4 — Payroll Engine and Operations

**Status: Completed — July 15, 2026**

Version 0.4 established the production payroll engine and live administrative operations foundation.

Completed work:

- Daily payroll calculation
- Weekly payroll calculation
- Daily overtime calculation
- Weekly overtime calculation
- Meal and break policies
- Punch rounding
- Company-timezone-aware reports
- Daily payroll CSV export
- Weekly payroll CSV export
- Notification Center
- Database-managed notification recipients
- Employee activation and deactivation
- Payroll-history preservation
- Live operations dashboard
- Payroll issue reporting
- Expanded automated tests

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
- Payroll warnings and review status

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
- Consistent payroll categories across exports
- Report-wide totals

### PDF Documents

- Daily payroll PDF
- Weekly payroll-register PDF
- Payroll Workspace PDF
- Printable employee time-card PDF
- Payroll warnings
- Review status
- Company information
- Report periods and timezone
- Employee and supervisor signature lines

### Dashboard and Email

- Expanded dashboard weekly payroll totals
- Daily overtime display
- Weekly overtime display
- Double-time display
- Premium-hour display
- Expanded daily payroll email content
- Email report-wide payroll totals
- Payroll issue counts in email reports
- Labor Rules dashboard navigation

### Automated Testing

- Configurable labor-rule tests
- Daily overtime classification tests
- Weekly overtime conversion tests
- Double-time tests
- Premium-hour tests
- No-double-counting assertions
- Workweek start validation
- 28 tests
- 116 assertions

---

## Version 0.6 — Backup, Recovery, and Diagnostics

**Status: Planned**

Version 0.6 will focus on protecting business data and improving system diagnostics.

Planned work:

### Database Backup

- Automatic SQLite database backups
- Configurable backup schedule
- Configurable backup retention
- Timestamped backup files
- Backup logging
- Backup failure reporting
- Safe handling of the active SQLite database

### Manual Backup and Restore

- Manual backup console command
- Backup listing command
- Restore workflow
- Restore confirmation and safety checks
- Pre-restore safety backup
- Database migration verification after restore
- Backup and restore documentation

### Database Health

- SQLite integrity checks
- Database readability checks
- Migration-status checks
- Missing-table detection
- Writable-path verification
- Database file and directory permission checks

### Application Diagnostics

- Diagnostic `doctor` console command
- PHP-version checks
- Required PHP-extension checks
- Configuration-file checks
- Storage-directory checks
- Log-directory checks
- Mail-configuration diagnostics
- Scheduler diagnostics
- Cron guidance
- Application version reporting

### Operational Health

- Scheduler last-run reporting
- Scheduler failure visibility
- Last successful email-report status
- Backup status on the operations dashboard
- Database-health status on the operations dashboard
- Clear administrator remediation guidance

### Documentation

- Backup administration guide
- Restore procedure
- Disaster-recovery checklist
- Diagnostic-command guide
- Operations troubleshooting guide

---

## Version 0.7 — Payroll Administration

**Status: Planned**

Version 0.7 is expected to focus on supervisor payroll corrections and approval workflows.

Possible work:

- Supervisor punch correction
- Missing-punch resolution workflow
- Manual punch entry
- Reason-required payroll adjustments
- Adjustment audit trail
- Payroll approval
- Payroll-period locking
- Reopening approved payroll
- Employee payroll detail pages
- Department payroll summaries
- Payroll exception workflow
- Supervisor review notes
- Export profiles for external payroll systems

---

## Version 0.8 — Reporting Automation

**Status: Possible**

Potential work:

- Manual weekly payroll email delivery
- Scheduled weekly payroll email delivery
- Scheduled exception-report delivery
- Configurable report schedules by report type
- Recipient groups
- Report attachments
- PDF email attachments
- CSV email attachments
- Report-delivery retries
- Delivery-failure notifications
- Report archive

---

## Version 0.9 — Installation and Upgrade Readiness

**Status: Possible**

Potential work:

- Guided installation process
- Environment validation
- Initial configuration workflow
- Database migration verification
- Upgrade command
- Upgrade rollback guidance
- Release packaging
- Service configuration examples
- Production web-server documentation
- Kiosk auto-start configuration
- Supported deployment checklist

---

## Version 1.0 — Stable Public Release

**Status: Target**

Target requirements:

- Stable installation and upgrade process
- Complete administrator documentation
- Complete kiosk deployment guide
- Backup and restore documentation
- Supported release packaging
- Migration and upgrade verification
- Broad automated-test coverage
- Security review
- Data-integrity review
- Payroll-calculation review
- Recovery testing
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
- REST API
- Mobile administration
- Plugin architecture
- QuickBooks export
- ADP export
- Paychex export
- Additional database engines
