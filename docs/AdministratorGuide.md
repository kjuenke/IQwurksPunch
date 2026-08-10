# IQwurksPunch Administrator Guide

**Applies to:** IQwurksPunch 1.0.0-dev
**Audience:** Administrators, supervisors, payroll staff, and system operators

---

## 1. Purpose

IQwurksPunch is a self-hosted employee time clock and payroll-preparation system.

Employees use the kiosk to:

- Enter an employee number
- Enter a four-digit PIN
- Clock in
- Clock out

Supervisors use the administration interface to:

- Manage employees
- Review current employee status
- Review and correct punches
- Configure company settings
- Configure labor rules
- Generate payroll reports
- Export CSV and PDF documents
- Manage notification recipients
- Send payroll reports
- Review email history
- Monitor scheduler activity
- Create and verify database backups
- Restore a verified backup
- Run system diagnostics
- Enable maintenance mode

Active administrators can also:

- Create and manage payroll periods
- Delete eligible draft payroll periods
- Archive or void payroll periods safely
- Manage supervisor and administrator accounts
- Reset account passwords
- Activate or deactivate managed accounts
- Review user-management activity

---

## 2. Application Access

### Employee Kiosk

Validated production address:

```text
http://192.168.1.82/kiosk
```

Generic address:

```text
http://SERVER_ADDRESS/kiosk
```

The physical kiosk opens the local address:

```text
http://127.0.0.1/kiosk
```

### Supervisor Login

Validated production address:

```text
http://192.168.1.82/login
```

Generic address:

```text
http://SERVER_ADDRESS/login
```

### Supervisor Dashboard

```text
http://SERVER_ADDRESS/dashboard
```

A supervisor must log in before accessing administrative pages.

---

## 3. Supervisor Authentication

Administrative access is restricted to active users with one of these roles:

```text
admin
supervisor
```

IQwurksPunch revalidates the signed-in user against the database during administrative requests.

Access is removed when:

- The user no longer exists
- The account has been deactivated
- The user does not have an authorized role
- The session is cleared
- The supervisor session is idle for eight hours
- The user logs out

### Logging In

1. Open `/login`.
2. Enter the supervisor username.
3. Enter the supervisor password.
4. Select **Log In**.

After login, IQwurksPunch may return the user to the administrative page originally requested.

### Logging Out

Use the application’s **Logout** action.

Logout:

- Clears supervisor authentication data
- Clears the supervisor role
- Clears activity state
- Destroys the active session
- Expires the application session cookie

### Session Storage

Application sessions are stored under:

```text
storage/sessions
```

The session cookie is named:

```text
IQWURKSPUNCHSESSID
```

Session protections include:

- Strict session mode
- Cookie-only session handling
- HTTP-only cookies
- SameSite=Lax
- Session regeneration after login
- CSRF-token rotation after login
- An eight-hour supervisor inactivity timeout

---

## 4. Operations Dashboard

The dashboard provides a live summary of application activity.

Depending on available data, it displays:

- Active employee count
- Total employee count
- Employees currently clocked in
- Today’s punch count
- Recent punch activity
- Current-week payroll totals
- Payroll issues requiring review
- Scheduled-report status
- Last email-report status

Dashboard payroll totals may include:

- Regular hours
- Daily overtime
- Weekly overtime
- Double-time
- Total overtime
- Premium hours
- Total payable hours

Use the dashboard as the starting point for daily operational review.

---

## 5. Employee Management

The employee administration interface supports:

- Adding employees
- Editing employee records
- Changing employee PINs
- Activating employees
- Deactivating employees
- Reviewing employee status
- Opening employee punch history
- Permanently deleting eligible employees

### Adding an Employee

1. Open **Employees**.
2. Select **Add Employee**.
3. Enter the employee number.
4. Enter the employee name.
5. Enter the four-digit PIN.
6. Confirm the PIN.
7. Save the employee.

Employee numbers must be unique.

### Changing an Employee PIN

1. Open **Employees**.
2. Locate the employee.
3. Select **Change PIN**.
4. Enter the new four-digit PIN.
5. Confirm the PIN.
6. Save the change.

### Deactivating an Employee

Deactivation prevents normal use while preserving payroll history.

Use deactivation when an employee:

- Leaves the company
- Is temporarily inactive
- Should no longer use the kiosk
- Must remain available in historical payroll reports

### Permanently Deleting an Employee

Permanent deletion is blocked when punch history exists.

This protects:

- Payroll records
- Timekeeping history
- Auditability
- Historical reports

Use deactivation instead of deletion when an employee has recorded punches.

---

## 6. Employee Kiosk Operation

The kiosk follows this sequence:

1. Employee enters an employee number.
2. IQwurksPunch finds the employee.
3. Employee enters a four-digit PIN.
4. IQwurksPunch verifies the PIN.
5. The kiosk displays the employee’s current status.
6. The employee selects **Clock In** or **Clock Out**.
7. The punch is recorded.
8. The kiosk returns to the starting screen.

The kiosk displays:

- Company-local date
- Company-local time
- Company timezone
- Employee name
- Current work status
- Last recorded punch

### Invalid Employee Number

When an employee number is not found:

- The partial transaction is cleared.
- The kiosk returns to the starting screen.
- An error message is displayed.

### Invalid PIN

When the PIN is invalid:

- Temporary employee state is cleared.
- Temporary kiosk authentication is cleared.
- The kiosk returns to the starting screen.
- The employee must begin again.

---

## 7. Kiosk Inactivity Protection

IQwurksPunch automatically clears unfinished kiosk transactions when the kiosk is left unattended.

This prevents the next employee from seeing or using another employee’s partially completed transaction.

### Configuring the Timeout

Open:

```text
Settings → Company Settings
```

Locate:

```text
Kiosk Safety
```

Set:

```text
Kiosk Inactivity Timeout
```

Allowed range:

```text
15–600 seconds
```

Default:

```text
60 seconds
```

### Activity That Restarts the Countdown

The timer is restarted by:

- Keyboard input
- Mouse activity
- Pointer activity
- Touch activity
- Form input
- Selection changes
- Wheel activity
- Form submission

### Timeout Warning

During the final ten seconds, the kiosk displays a warning.

The warning tells the employee that the kiosk will reset unless activity resumes.

### What the Timeout Clears

The timeout clears:

- Selected employee
- Employee name
- Entered PIN
- Temporary kiosk authentication
- Server-side kiosk activity state
- Unfinished transaction state

The kiosk then returns to:

```text
/kiosk
```

### What the Timeout Does Not Do

A timeout does not:

- Clock the employee in
- Clock the employee out
- Create a break punch
- Create a meal punch
- Modify an existing punch
- Submit the currently displayed action

Server-side validation also rejects stale PIN and punch submissions after the timeout.

---

## 8. Company Settings

Open:

```text
Settings → Company Settings
```

Company Settings manages:

### Company Information

- Company name
- Address
- City
- State
- ZIP code
- Phone
- Email
- Timezone

### Kiosk Safety

- Kiosk inactivity timeout

### Timekeeping Policy

- Punch rounding interval
- Punch rounding direction
- Automatic meal deduction
- Meal deduction duration
- Paid-break allowance

### Company Timezone

The company timezone affects:

- Kiosk date and time
- Punch display
- Payroll date boundaries
- Payroll reports
- Email reports
- Scheduler calculations
- Diagnostic timestamps

The validated production timezone is:

```text
America/Los_Angeles
```

### Saving Settings

After making changes, select:

```text
Save Company Settings
```

Review the confirmation message and verify that the updated value remains visible after the page reloads.

---

## 9. Labor Rules

Open:

```text
Settings → Labor Rules
```

Labor Rules is authoritative for:

- Daily overtime threshold
- Weekly overtime threshold
- Double-time threshold
- Workweek start day

Supported workweek starts include:

```text
Sunday
Monday
```

The double-time threshold must be greater than the daily overtime threshold.

### Payroll Relationships

IQwurksPunch uses these relationships:

```text
Total Overtime = Daily Overtime + Weekly Overtime

Premium Hours = Total Overtime + Double-Time

Payable Hours = Regular + Total Overtime + Double-Time
```

Hours already classified as daily overtime or double-time are not classified again as weekly overtime.

---

## 10. Payroll Reports

IQwurksPunch includes:

- Daily payroll reports
- Weekly payroll reports
- Payroll Workspace date-range reports
- Employee time cards
- Punch reports

Reports may include:

- Gross hours
- Meal deduction
- Unpaid breaks
- Regular hours
- Daily overtime
- Weekly overtime
- Double-time
- Total overtime
- Premium hours
- Total payable hours
- Payroll warnings
- Review status

### Payroll Workspace

The Payroll Workspace supports:

- Start-date selection
- End-date selection
- Employee filtering
- Department filtering
- Report-level totals
- Employee daily details
- Payroll-week summaries
- Warning review
- CSV export
- PDF export
- Employee time-card generation

### Payroll Warnings

Warnings may indicate:

- Missing clock-out
- Unexpected clock-out
- Incomplete shift
- Invalid punch sequence
- Incomplete break
- Incomplete meal period
- Payroll data requiring supervisor review

Warnings should be resolved before payroll is finalized.

---

## 11. CSV and PDF Exports

### CSV Exports

Available CSV exports include:

- Daily payroll CSV
- Weekly payroll CSV
- Payroll Workspace CSV

CSV exports can be opened in spreadsheet software or imported into another payroll workflow.

### PDF Documents

Available PDF documents include:

- Daily payroll PDF
- Weekly payroll-register PDF
- Payroll Workspace PDF
- Employee time-card PDF

PDF documents may include:

- Company identity
- Employee identity
- Report period
- Company timezone
- Payroll categories
- Payroll totals
- Payroll warnings
- Review status
- Signature lines

---

## 12. Supervisor Punch Correction

Supervisors can correct timekeeping records from the employee list.

Open:

```text
Employees
```

Locate an employee and open the employee’s punch history.

Available correction actions include:

- Add Punch
- Edit Punch
- Delete Punch

### Supported Punch Types

```text
clock_in
clock_out
break_out
break_in
meal_out
meal_in
```

### Adding a Missing Punch

1. Open the employee’s punch history.
2. Select **Add Punch**.
3. Select the punch date.
4. Select the punch time.
5. Select the punch type.
6. Enter a correction reason.
7. Submit the correction.

### Editing a Punch

1. Open the employee’s punch history.
2. Locate the punch.
3. Select **Edit**.
4. Change the date, time, or punch type.
5. Enter a correction reason.
6. Save the correction.

### Deleting a Punch

1. Open the employee’s punch history.
2. Locate the punch.
3. Select **Delete**.
4. Enter a correction reason.
5. Enter the required typed deletion confirmation.
6. Submit the deletion.

### Correction Reasons

A correction reason is required for every:

- Added punch
- Edited punch
- Deleted punch

Use a clear business explanation, such as:

```text
Employee forgot to clock out at end of shift.
```

Avoid vague reasons such as:

```text
Fix
Change
Error
```

### Punch Sequence Validation

IQwurksPunch validates the complete resulting punch sequence before committing a correction.

Examples of potentially invalid conditions include:

- Clocking out before clocking in
- Starting a break while already on break
- Ending a break that was never started
- Starting a meal while already on meal
- Ending a meal that was never started
- Creating overlapping work states

Invalid corrections are rejected.

The database transaction is rolled back so no partial change remains.

### Correction History

Correction activity records:

- Employee
- Punch
- Correction action
- Original values
- New values
- Correction reason
- Supervisor
- Correction timestamp

Correction history is immutable.

Application audit records are also created.

---

## 13. Notification Center

Notification recipients are stored in the database.

Administrators can manage:

- Recipient name
- Recipient email address
- Active status
- Daily payroll subscription
- Weekly payroll subscription
- Exception-report subscription

The SMTP configuration manages only:

- Mail server
- Port
- Encryption
- Username
- Password
- Sender address
- Sender name

Recipients should not be hard-coded in the SMTP configuration.

---

## 14. Manual Email Reports

Manual daily payroll email delivery is available from the report-email interface.

Before sending:

1. Confirm the report date.
2. Confirm active recipients.
3. Review the payroll report.
4. Confirm that warnings are understood.
5. Select the manual-send action.

Email history records:

- Report type
- Recipients
- Delivery status
- Sent time

---

## 15. Automatic Report Scheduling

Automatic daily payroll reports are managed from the report email settings.

Schedule configuration includes:

- Enabled or disabled status
- Delivery time
- Delivery days
- Active recipients

The production scheduler runs once per minute:

```cron
* * * * * cd /var/www/IQwurksPunch && /usr/bin/flock -n /tmp/iqwurks-scheduler.lock /usr/bin/php iqwurks schedule:run >> storage/logs/cron-scheduler.log 2>&1
```

The lock prevents overlapping scheduler execution.

### Running the Scheduler Manually

```bash
cd /var/www/IQwurksPunch

./iqwurks schedule:run
```

A normal result may be:

```text
No scheduled report is due.
```

This is not an error.

---

## 16. Scheduler Diagnostics

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks scheduler:check
```

The diagnostic checks:

- Scheduler cron
- Scheduler lock
- Scheduler application log
- Scheduler cron-output log
- Recent scheduler failures
- Company timezone
- Report schedule
- Scheduled recipients
- Last scheduled delivery

A healthy result shows:

```text
Overall status: PASS
```

The scheduler diagnostic currently performs nine checks.

---

## 17. Database Backups

The default backup configuration is stored in:

```text
config/backup.php
```

Default backup directory:

```text
storage/backups
```

Default retention:

```text
30 backups
```

Backup lock file:

```text
storage/cache/backup.lock
```

### Create a Backup

```bash
cd /var/www/IQwurksPunch

./iqwurks backup:create
```

The command:

- Creates a timestamped SQLite backup
- Checks database integrity
- Checks foreign-key violations
- Reports the filename
- Reports the file size
- Reports the creation time
- Reports command duration

### List Backups

```bash
./iqwurks backup:list
```

The list includes:

- Creation date
- File size
- Filename
- Total backup storage

### Verify the Newest Backup

```bash
./iqwurks backup:verify
```

### Verify a Named Backup

```bash
./iqwurks backup:verify BACKUP_FILENAME
```

Example:

```bash
./iqwurks backup:verify \
    iqwurks_20260721_141835_c7e72a.sqlite
```

### Verify All Backups

```bash
./iqwurks backup:verify --all
```

Verification checks:

- File availability
- SQLite integrity
- Foreign-key violations
- File readability

### Preview Backup Pruning

```bash
./iqwurks backup:prune --keep=30
```

Preview mode does not delete backups.

### Delete Backups Beyond Retention

```bash
./iqwurks backup:prune --keep=30 --delete
```

Review the preview before using `--delete`.

### Run the Complete Backup Workflow

```bash
./iqwurks backup:run
```

This command:

1. Creates a backup.
2. Verifies the backup.
3. Applies the configured retention policy.
4. Records the result.

It does not accept command-line arguments.

---

## 18. Automatic Backup Schedule

The validated production backup cron entry is:

```cron
# BEGIN IQWURKSPUNCH BACKUP
# Daily verified SQLite backup with automatic retention
CRON_TZ=America/Los_Angeles
15 1 * * * cd /var/www/IQwurksPunch && /usr/bin/flock -n /tmp/iqwurks-backup-cron.lock /usr/bin/php iqwurks backup:run >> storage/logs/cron-backup.log 2>&1
# END IQWURKSPUNCH BACKUP
```

This runs daily at:

```text
1:15 AM
```

Review backup status regularly with:

```bash
./iqwurks backup:list
./iqwurks backup:verify
```

A backup should not be considered reliable until verification passes.

---

## 19. Database Restore

Restoring replaces the active database with a selected verified backup.

Use restore only when:

- The active database is damaged
- Incorrect data cannot be repaired safely
- A failed upgrade must be rolled back
- A known previous database state is required

### Before Restoring

1. Inform employees and supervisors.
2. Stop normal kiosk use.
3. Review the backup list.
4. Select the correct backup.
5. Verify the selected backup.
6. Preview the restore.
7. Confirm the backup date and size.
8. Ensure sufficient disk space.

### List Backups

```bash
./iqwurks backup:list
```

### Verify the Selected Backup

```bash
./iqwurks backup:verify BACKUP_FILENAME
```

### Preview the Restore

```bash
./iqwurks backup:restore BACKUP_FILENAME
```

Preview mode does not replace the active database.

### Apply the Restore

```bash
./iqwurks backup:restore \
    BACKUP_FILENAME \
    --apply \
    --confirm=BACKUP_FILENAME
```

The `--confirm` value must exactly match the selected backup filename.

### Restore Safety Features

The restore workflow includes:

- Filename validation
- Backup integrity verification
- Foreign-key verification
- Exact confirmation
- Maintenance-mode handling
- Pre-restore safety backup
- WAL checkpoint handling
- Stale sidecar-file cleanup
- Active-database replacement
- Post-restore integrity checking
- Migration-history checking
- Rollback handling

### After Restoring

Run:

```bash
./iqwurks database:check
./iqwurks scheduler:check
./iqwurks doctor
```

Then verify:

- Kiosk access
- Supervisor login
- Employee records
- Recent punches
- Payroll reports
- Notification recipients
- Report schedule
- Email history
- Application version

---

## 20. Database Health Check

Run:

```bash
./iqwurks database:check
```

The command checks:

- Database path
- File size
- Readability
- Writability
- Directory writability
- SQLite version
- Journal mode
- Integrity
- Foreign-key violations
- Application table count
- Applied migration count
- Page size
- Page count
- Unused pages
- Check duration

A healthy database should report:

```text
Status: HEALTHY
Integrity check: ok
Foreign-key violations: 0
Journal mode: wal
```

---

## 21. SQLite WAL Mode

IQwurksPunch uses SQLite WAL mode.

The application configures:

```sql
PRAGMA foreign_keys = ON;
PRAGMA busy_timeout = 10000;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA wal_autocheckpoint = 1000;
```

WAL mode improves concurrency among:

- Kiosk requests
- Supervisor requests
- Scheduler operations
- Backup operations
- Diagnostic commands

Possible SQLite sidecar files include:

```text
iqwurks.sqlite-wal
iqwurks.sqlite-shm
```

Do not manually copy only the active `.sqlite` file while the application is running.

Use the IQwurksPunch backup commands instead.

---

## 22. Maintenance Mode

Maintenance mode prevents normal web use during administrative work.

### Enable Maintenance Mode

```bash
./iqwurks maintenance:on
```

### Enable It With a Reason

```bash
./iqwurks maintenance:on Planned database maintenance
```

Everything after `maintenance:on` becomes the maintenance reason.

The console does not currently provide a universal `--help` option. Therefore, do not run:

```bash
./iqwurks maintenance:on --help
```

That command would activate maintenance mode with `--help` as the reason.

### Check Maintenance Status

```bash
./iqwurks maintenance:status
```

### Disable Maintenance Mode

```bash
./iqwurks maintenance:off
```

### Maintenance State File

```text
storage/cache/maintenance.json
```

When inactive, this file should normally be absent.

### When to Use Maintenance Mode

Use maintenance mode during:

- Database restoration
- High-risk database repair
- Application upgrade
- Migration troubleshooting
- Permission repair
- Production configuration changes
- Emergency maintenance

---

## 23. Mail Diagnostics

Run:

```bash
./iqwurks mail:check
```

Mail diagnostics may check:

- SMTP configuration
- SMTP hostname
- DNS resolution
- TCP connectivity
- TLS readiness
- Mail transport readiness
- Delivery-log activity

A diagnostic check is different from sending a payroll report.

### Empty Mail Log Warning

Log rotation may create a new empty:

```text
storage/logs/mail.log
```

Until a successful email is recorded, the System Doctor may report:

```text
Mail activity: WARN
```

This is not necessarily a mail configuration failure.

Review:

- The detailed warning
- Previous rotated mail logs
- SMTP configuration
- Manual email delivery
- Scheduled delivery history

---

## 24. System Doctor

Run:

```bash
./iqwurks doctor
```

The System Doctor checks:

- Application version
- PHP version
- PHP extensions
- Composer dependencies
- Configuration files
- Application timezone
- Mail configuration
- Mail configuration permissions
- Runtime directories
- Disk capacity
- Maintenance mode
- Database health
- Migration history
- Database backups
- Scheduler cron
- Automatic backup cron
- Scheduler activity
- Mail activity

Results are classified as:

```text
PASS
WARN
FAIL
```

### PASS

The check succeeded.

### WARN

The check found something requiring review, but the application may remain operational.

Examples include:

- A newly rotated empty mail log
- Old but still available operational activity
- A recommended configuration improvement

### FAIL

The check found a condition that can affect safe operation.

Failures should be corrected before normal production use continues.

---

## 25. Console Help

Display the command list:

```bash
./iqwurks help
```

Available commands include:

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

Display the application version:

```bash
./iqwurks version
```

The console does not currently implement a universal per-command:

```text
--help
```

Do not append `--help` to operational commands unless the command documentation explicitly supports it.

---

## 26. Application Logs

Application logs are stored under:

```text
storage/logs
```

Common logs include:

```text
scheduler.log
cron-scheduler.log
cron-backup.log
mail.log
php-fpm-error.log
```

The physical kiosk browser log is stored under:

```text
/home/kiosk/.local/state/iqwurks-kiosk/browser.log
```

### Log Rotation

The validated production logrotate policy provides:

- Daily rotation
- Rotation at 5 MB
- Compression
- Delayed compression
- Date-based archive names
- Thirty application-log rotations
- Fourteen kiosk-browser-log rotations

Do not delete current logs while troubleshooting unless a backup copy has been retained.

---

## 27. Local Frontend Assets

Bootstrap and Bootstrap Icons are stored locally under:

```text
public/assets/vendor
```

This allows the application interface to operate without depending on an external frontend CDN.

### Verify Asset Checksums

Run from the project root:

```bash
cd /var/www/IQwurksPunch

sha256sum --check public/assets/vendor/SHA256SUMS
```

Do not run the command from inside `public/assets/vendor` because the checksum paths are relative to the project root.

Expected files include:

- Bootstrap CSS
- Bootstrap JavaScript bundle
- Bootstrap Icons CSS
- Bootstrap Icons WOFF font
- Bootstrap Icons WOFF2 font
- License files

---

## 28. Physical Kiosk Recovery

The validated physical kiosk uses:

- LightDM
- Openbox
- Chromium
- Restricted `kiosk` account
- Automatic login
- Full-screen browser
- Browser restart loop

### Browser Recovery

If Chromium exits unexpectedly, the kiosk launcher:

1. Records the exit.
2. Waits two seconds.
3. Starts Chromium again.

### Application Startup Recovery

If IQwurksPunch is not yet available, the kiosk launcher:

1. Tests the local kiosk URL.
2. Records that it is waiting.
3. Waits two seconds.
4. Tries again.
5. Starts Chromium after the application responds.

### Restart Chromium

From a supervisor shell, Chromium may be terminated to test recovery.

The launcher should restart it automatically.

### Restart the Kiosk Session

```bash
systemctl restart lightdm
```

This interrupts the local graphical session.

Use it only when the physical kiosk can safely be restarted.

### Kiosk Log

```text
/home/kiosk/.local/state/iqwurks-kiosk/browser.log
```

Review this log when:

- Chromium does not start
- Chromium repeatedly exits
- The application is unavailable during startup
- The kiosk remains blank
- Recovery does not occur

---

## 29. Service Health

The main production services include:

```text
nginx
php8.5-fpm
cron
lightdm
snapd
```

Check one service:

```bash
systemctl status nginx
```

Check whether a service is active:

```bash
systemctl is-active nginx
```

Check whether a service starts automatically:

```bash
systemctl is-enabled nginx
```

Repeat as needed for:

```text
php8.5-fpm
cron
lightdm
snapd
```

---

## 30. Network and Firewall

The validated installation uses:

```text
Server IP: 192.168.1.82
Trusted LAN: 192.168.1.0/24
```

Validated UFW rules allow the trusted LAN to access:

```text
22/tcp       SSH
80/tcp       IQwurksPunch
19999/tcp    Netdata
```

Display firewall status:

```bash
ufw status numbered
```

The validated deployment uses HTTP on a trusted local network.

Do not expose the application across an untrusted network without adding HTTPS and reviewing firewall access.

---

## 31. Routine Daily Review

Recommended daily checks:

1. Confirm that the kiosk is displaying the employee-number screen.
2. Confirm that the displayed date and time are correct.
3. Review employees currently clocked in.
4. Review recent punch activity.
5. Review payroll warnings.
6. Review scheduled-report status.
7. Review last email-report status.
8. Confirm the newest backup exists.
9. Investigate visible application errors.

Useful commands:

```bash
./iqwurks backup:list
./iqwurks scheduler:check
```

---

## 32. Routine Weekly Review

Recommended weekly checks:

1. Verify the newest database backup.
2. Review total backup storage.
3. Review scheduler diagnostics.
4. Review System Doctor results.
5. Review mail history.
6. Review punch corrections.
7. Review employee activation status.
8. Review payroll warnings.
9. Review disk capacity.
10. Review current logs for recurring failures.

Commands:

```bash
./iqwurks backup:verify
./iqwurks scheduler:check
./iqwurks doctor
```

---

## 33. Routine Monthly Review

Recommended monthly checks:

1. Verify all retained backups.
2. Review backup retention.
3. Review log rotation.
4. Review supervisor accounts.
5. Deactivate unused supervisor accounts.
6. Review employee status.
7. Review correction history.
8. Review firewall rules.
9. Review available disk space.
10. Perform a controlled recovery exercise when operationally appropriate.
11. Confirm service restart behavior.
12. Review documentation for needed updates.

Verify all backups:

```bash
./iqwurks backup:verify --all
```

Preview retention:

```bash
./iqwurks backup:prune --keep=30
```

---

## 34. Reboot Validation

After system maintenance or an operating-system update:

1. Reboot the computer.
2. Confirm Nginx is active.
3. Confirm PHP-FPM is active.
4. Confirm cron is active.
5. Confirm LightDM is active.
6. Confirm the physical kiosk starts.
7. Confirm the kiosk opens `/kiosk`.
8. Confirm LAN kiosk access.
9. Confirm supervisor login.
10. Confirm a protected page redirects unauthenticated users.
11. Confirm scheduler activity resumes.
12. Confirm automatic backup scheduling remains installed.
13. Run database diagnostics.
14. Run scheduler diagnostics.
15. Run the System Doctor.

Commands:

```bash
./iqwurks database:check
./iqwurks scheduler:check
./iqwurks doctor
```

---

## 35. Emergency Checklist

When a serious problem occurs:

1. Stop employees from using the kiosk.
2. Enable maintenance mode.
3. Record the visible error.
4. Record the current date and time.
5. Check available disk space.
6. Check service status.
7. Check the PHP-FPM error log.
8. Check application logs.
9. Run the database health check.
10. List available backups.
11. Verify the selected recovery backup.
12. Preview restore before applying it.
13. Apply a restore only when necessary.
14. Run post-restore diagnostics.
15. Disable maintenance mode.
16. Verify kiosk and supervisor access.
17. Document the incident and recovery actions.

Enable maintenance mode:

```bash
./iqwurks maintenance:on Emergency maintenance
```

Run diagnostics:

```bash
./iqwurks database:check
./iqwurks scheduler:check
./iqwurks doctor
```

Disable maintenance mode after recovery:

```bash
./iqwurks maintenance:off
```

---

## 36. Version 1.0 Administrative Workflows

Version 1.0 adds controlled payroll-period management and administrator-only
user management.

### Payroll Period Management

Open the payroll-period workspace at:

```text
http://SERVER_ADDRESS/payroll-periods
```

Active supervisors can review payroll-period records and use the established
review, approval, locking, exception, and note workflows. Creation, editing,
and removal actions apply additional authorization and lifecycle rules.

#### Create a Payroll Period

1. Open **Payroll Periods**.
2. Select **Create Payroll Period**.
3. Enter the period name, start date, and end date.
4. Review the date range.
5. Save the period.

Payroll periods use company-local dates. The service validates the dates,
limits the supported range, and rejects overlap with another active period.

Creation records immutable payroll-period history and an audit event.

#### Edit an Open Draft

Only an active, open draft can be edited.

1. Open the payroll-period detail page.
2. Select **Edit Payroll Period**.
3. Update the allowed fields.
4. Save the changes.

The application rejects edits to periods that have entered review, approval,
locking, archival, or voiding workflows. It also prevents an edited range from
overlapping another active period.

A real change records history and an audit event. Submitting identical values
does not create no-op history.

#### Review Removal and Retention Information

Active administrators see the removal and retention card on an eligible
payroll-period detail page.

Before an action is submitted, review:

- Period name and date range
- Current lifecycle status
- Employees with punches in the period
- Punch records in the period
- Report-artifact tracking status
- Lifecycle dependency records
- The action currently allowed by the service

Employee and punch counts use the configured company timezone and the
period's inclusive local dates.

CSV and PDF reports are generated in memory and downloaded on demand. They
are not retained as payroll-period-linked artifacts.

#### Delete an Eligible Draft

Permanent deletion is limited to an untouched open draft and requires the
exact confirmation shown by the interface.

Deleting the payroll-period record:

- Does not delete employee punch records
- Does not delete employees
- Does not erase unrelated audit records
- Is rejected after the period enters a protected workflow
- Is rejected when protected dependency records exist
- Records the administrator action in the audit log

If the service does not mark the period as deletable, do not bypass the
protection through direct database changes.

#### Archive a Payroll Period

Archival preserves the payroll-period record as historical data.

1. Open the period detail page.
2. Review the removal and retention analysis.
3. Enter a meaningful archival reason.
4. Submit the archive action.

Archived periods are separated from active periods and cannot continue through
the active approval workflow.

#### Void a Finalized Payroll Period

Voiding is used for an eligible finalized period that must remain visible as
historical data but must no longer operate as an active payroll period.

1. Open the period detail page.
2. Review the removal and retention analysis.
3. Enter a meaningful void reason.
4. Enter the exact confirmation shown by the interface.
5. Submit the void action.

An open draft cannot be voided. Voided periods are read-only and excluded from
active payroll-period association.

#### Removed Payroll Periods

The payroll-period list separates active and removed records. Use the removed
view to review archived and voided periods without treating them as active
operational records.

### Supervisor and Administrator Account Management

User management is restricted to active administrators.

Open the account list at:

```text
http://SERVER_ADDRESS/admin/users
```

Open user-management activity at:

```text
http://SERVER_ADDRESS/admin/users/activity
```

#### Create an Account

1. Open **User Management**.
2. Select **Create User**.
3. Enter a unique username and email address.
4. Select the supervisor or administrator role.
5. Choose whether the account starts active.
6. Enter and confirm a password of at least 12 characters.
7. Save the account.

Usernames are checked case-insensitively for uniqueness. Passwords are securely
hashed and are never stored or displayed as plain text.

Grant the administrator role only to people authorized to manage system
accounts and protected administrative actions.

#### Edit Account Details and Role

Administrators can update a managed account's username, email address, and
authorized role.

Role changes are recorded with the previous and new role in the user-management
audit history.

The signed-in administrator cannot remove their own administrator role. The
final active administrator is also protected from role removal.

#### Reset a Password

Use the account's **Reset Password** action. Enter and confirm a new password
of at least 12 characters.

The old password and password hash are never displayed or recovered. Successful
and blocked password-reset actions are auditable without recording password
contents.

#### Activate or Deactivate an Account

Deactivation is the supported way to remove access while retaining operational
and audit history.

The application prevents:

- Deactivation of the signed-in administrator's own account
- Deactivation of the final active administrator
- Management actions by inactive administrators
- Management actions by supervisors

There is no permanent user-deletion route. Reactivate an account only after
confirming that access should be restored.

#### Review User-Management Activity

The activity page lists `user.*` audit events, including:

- Account creation
- Account updates
- Role changes
- Password resets
- Activation and deactivation
- Blocked management attempts

Review this history during monthly access reviews and after any unexpected
account or privilege change.

---

## 37. Automated Tests

Administrators performing an upgrade or release validation should run:

```bash
cd /var/www/IQwurksPunch

php vendor/bin/phpunit
```

Current verified Version 1.0 development baseline:

```text
OK (438 tests, 3293 assertions)
```

A failed test should be investigated before the installation is considered release-ready.

---

## 38. Version Information

Display the installed version:

```bash
./iqwurks version
```

Expected Version 1.0 development output:

```text
IQwurksPunch 1.0.0-dev
```

The version is stored in:

```text
VERSION
```

---

## 39. Current Version 1.0 Development Limitations

Version 1.0 development does not include:

- A graphical guided installation wizard
- Persistent payroll-period-linked CSV or PDF artifacts
- Third-party payroll-provider export profiles
- Multiple companies
- Multiple physical locations
- Multiple independently managed kiosks
- Employee self-service
- Mobile administration
- A public REST API

CSV and PDF payroll reports are generated and downloaded on demand. Stable
Version 1.0 release promotion, final archive publication, tagging, and release
publication occur only after the complete release-readiness review passes.

---

## 40. Support Information to Collect

When documenting or reporting a problem, collect:

- IQwurksPunch version
- Operating-system version
- PHP version
- SQLite version
- Exact page or console command
- Exact error message
- Date and time
- Relevant log lines
- Database health result
- Scheduler diagnostic result
- System Doctor result
- Most recent verified backup
- Recent changes
- Whether the issue persists after a service restart
- Whether the issue persists after a reboot

Never include SMTP passwords, password hashes, employee PINs, or other secrets in a support report.
