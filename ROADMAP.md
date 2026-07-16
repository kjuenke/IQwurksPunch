# IQwurksPunch Roadmap

IQwurksPunch is an open-source employee time-clock and payroll-reporting system designed for a dedicated Linux kiosk.

This roadmap describes intended development direction. Items may move between releases as testing and operational needs evolve.

---

## Version 0.4 — Payroll Engine and Operations

**Status: Release Candidate**

- Daily payroll calculation
- Weekly payroll calculation
- Daily and weekly overtime
- Meal and break policies
- Punch rounding
- Company-timezone-aware reports
- Daily and weekly CSV exports
- Notification Center
- Employee lifecycle management
- Live operations dashboard
- Expanded automated tests

---

## Version 0.5 — Reporting and Payroll Documents

Planned work:

- Weekly payroll-register PDF
- Daily payroll PDF
- Printable employee time cards
- Date-range payroll reports
- Department filtering
- Employee filtering and search
- Print-friendly report layouts
- Signature and approval lines
- Payroll exception reports
- Manual weekly payroll email
- Scheduled weekly payroll email

---

## Version 0.6 — Backup, Recovery, and Diagnostics

Planned work:

- Automatic SQLite database backups
- Configurable backup retention
- Manual backup command
- Restore workflow
- Application health checks
- Database integrity checks
- `doctor` diagnostic command
- Scheduler health reporting
- Mail-configuration diagnostics
- Expanded operations documentation

---

## Version 0.7 — Payroll Administration

Possible work:

- Supervisor punch correction
- Missing-punch resolution workflow
- Payroll approval and locking
- Audit trail for payroll adjustments
- Department summaries
- Employee payroll detail pages
- Export profiles for external payroll systems

---

## Version 1.0 — Stable Public Release

Target requirements:

- Stable installation and upgrade process
- Complete administrator documentation
- Complete kiosk deployment guide
- Backup and restore documentation
- Supported release packaging
- Migration and upgrade verification
- Broad automated-test coverage
- Security and data-integrity review
- Public open-source release readiness

---

## Future Possibilities

These are not committed to a specific release:

- Multiple kiosks
- Multiple companies
- Shift scheduling
- PTO and vacation tracking
- Barcode or RFID identification
- REST API
- Mobile administration
- QuickBooks export
- ADP export
- Paychex export
- Additional database engines
