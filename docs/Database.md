# IQwurksPunch Database and Recovery Guide

**Applies to:** IQwurksPunch 0.6.0
**Database engine:** SQLite
**Audience:** Administrators, developers, and recovery operators

---

## 1. Purpose

This guide documents the IQwurksPunch database architecture and the procedures used to:

- Inspect database health
- Apply migrations
- Operate safely in SQLite WAL mode
- Create verified backups
- Manage backup retention
- Preview database restoration
- Restore a verified backup
- Recover from database damage
- Validate the application after recovery

IQwurksPunch stores essential business records in SQLite, including:

- Supervisor accounts
- Employees
- Employee PIN hashes
- Punch history
- Company settings
- Labor rules
- Notification recipients
- Report schedules
- Email history
- Audit records
- Punch-correction history

The database must be treated as critical business data.

---

## 2. Active Database Location

The production database is:

```text
/var/www/IQwurksPunch/database/sqlite/iqwurks.sqlite
```

From the project root, the relative path is:

```text
database/sqlite/iqwurks.sqlite
```

Confirm the configured path by reviewing the application database configuration and running:

```bash
cd /var/www/IQwurksPunch

./iqwurks database:check
```

The health check reports the active database path.

---

## 3. SQLite Sidecar Files

Because IQwurksPunch uses SQLite WAL mode, the database directory may also contain:

```text
iqwurks.sqlite-wal
iqwurks.sqlite-shm
```

Depending on an interrupted operation or older database state, a journal file may also appear:

```text
iqwurks.sqlite-journal
```

These are SQLite-managed files.

Do not:

- Delete active sidecar files manually
- Move sidecar files while the application is operating
- Copy only `iqwurks.sqlite` as a live backup
- Replace the database while Nginx, PHP-FPM, cron, or console operations may be writing to it

Use the IQwurksPunch backup and restore commands instead.

---

## 4. SQLite Runtime Configuration

The primary application database connection configures:

```sql
PRAGMA foreign_keys = ON;
PRAGMA busy_timeout = 10000;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA wal_autocheckpoint = 1000;
```

### Foreign-Key Enforcement

```sql
PRAGMA foreign_keys = ON;
```

This causes SQLite to enforce defined relationships between records.

Foreign-key enforcement protects against conditions such as:

- Punches referencing nonexistent employees
- Correction history referencing nonexistent records
- Related records becoming orphaned
- Invalid deletion of referenced data

### Busy Timeout

```sql
PRAGMA busy_timeout = 10000;
```

SQLite waits up to ten seconds for a locked database operation to become available.

This reduces transient lock failures when several processes interact with the database, including:

- Employee kiosk requests
- Supervisor requests
- Scheduled reports
- Database backups
- Diagnostic commands

### WAL Journal Mode

```sql
PRAGMA journal_mode = WAL;
```

Write-Ahead Logging improves concurrency by allowing readers and a writer to operate with less blocking than the traditional rollback journal.

### Synchronous Mode

```sql
PRAGMA synchronous = NORMAL;
```

Normal synchronous mode provides an appropriate balance between reliability and performance when operating in WAL mode.

### Automatic WAL Checkpoint

```sql
PRAGMA wal_autocheckpoint = 1000;
```

SQLite periodically transfers committed WAL data into the main database file.

---

## 5. Database Directory Permissions

The database directory must be writable by the PHP-FPM application user.

Validated ownership:

```text
www-data:www-data
```

Validated directory mode:

```text
0770
```

Apply:

```bash
cd /var/www/IQwurksPunch

chown -R www-data:www-data database/sqlite
chmod 0770 database/sqlite
```

Review:

```bash
ls -ld database/sqlite
ls -la database/sqlite
```

The application also configures a restrictive process `umask` so newly created SQLite sidecar files remain group-writable.

### Important

SQLite must be able to create and modify files in the database directory, not only the main database file.

A writable database file inside a non-writable directory is insufficient for WAL operation.

---

## 6. Database File Permissions

Review:

```bash
ls -l database/sqlite/iqwurks.sqlite
```

The database should normally be owned by:

```text
www-data:www-data
```

A suitable mode is typically:

```text
0660
```

Apply when necessary:

```bash
chown www-data:www-data \
    database/sqlite/iqwurks.sqlite

chmod 0660 \
    database/sqlite/iqwurks.sqlite
```

Do not make the database world-writable.

Avoid:

```text
0777
0666
```

unless temporarily required during controlled troubleshooting and immediately corrected afterward.

---

## 7. Database Tables

The database contains application and migration tables.

Core data areas include:

### Authentication

- Supervisor users
- User roles
- Active-account status
- Last-login information

### Employees

- Employee number
- Employee name
- PIN hash
- Active status
- Department or other employee metadata

### Timekeeping

- Employee punches
- Punch type
- Punch time
- Punch source
- Punch notes
- Correction metadata

### Punch Correction

- Immutable punch-correction history
- Original punch values
- Corrected values
- Correction reason
- Correcting supervisor
- Correction time

### Company Configuration

- Company identity
- Address
- Contact information
- Company timezone
- Payroll settings
- Kiosk inactivity timeout

### Payroll Rules

- Daily overtime threshold
- Weekly overtime threshold
- Double-time threshold
- Workweek start day

### Reporting and Notifications

- Report schedules
- Notification recipients
- Email history

### Audit and Application State

- Audit records
- General settings
- Migration history

Display table names:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
.tables
"
```

Do not modify production tables manually unless following a controlled repair procedure with a verified backup.

---

## 8. Migration System

Database migrations are stored under:

```text
database/migrations
```

Migration history is stored in the database’s migration table.

List installed migration files:

```bash
find database/migrations \
    -maxdepth 1 \
    -type f \
    -name '*.php' \
    -printf '%f\n' \
    | sort
```

List recorded migrations:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
SELECT migration
FROM migrations
ORDER BY migration;
"
```

Version 0.6 should include and record 11 migrations.

The final two Version 0.6 migrations are:

```text
010_add_punch_correction_support.php
011_add_kiosk_inactivity_timeout.php
```

---

## 9. Applying Migrations

Before applying migrations:

1. Confirm the application version being installed.
2. Create a verified database backup.
3. Confirm sufficient disk space.
4. Confirm database-directory permissions.
5. Enable maintenance mode for a production upgrade when appropriate.
6. Review migration files.
7. Run the migration command.
8. Verify migration history.
9. Run database diagnostics.
10. Test the application.

Create a backup:

```bash
cd /var/www/IQwurksPunch

./iqwurks backup:create
./iqwurks backup:verify
```

Apply migrations:

```bash
php migrate.php
```

Check the result:

```bash
./iqwurks database:check
```

Confirm migration count:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
SELECT COUNT(*)
FROM migrations;
"
```

Expected for Version 0.6:

```text
11
```

---

## 10. Migration Safety

A migration should:

- Be deterministic
- Preserve existing data
- Avoid duplicate columns
- Avoid silent record deletion
- Use transactions when supported and appropriate
- Be safe when upgrading an existing installation
- Leave the database structurally valid
- Be recorded only after successful completion

Before releasing a migration, validate:

- Fresh installation
- Upgrade from the prior release
- Database integrity
- Foreign-key integrity
- Existing employee data
- Existing punch history
- Existing report settings
- Existing notification recipients
- Existing payroll configuration

A failed migration is a release blocker.

---

## 11. Version 0.6 Database Changes

### Migration 010

```text
010_add_punch_correction_support.php
```

This migration adds support for:

- Corrected punch metadata
- Correcting supervisor identification
- Correction reason
- Correction timestamp
- Immutable correction history

Punch corrections preserve the auditability of historical payroll records.

### Migration 011

```text
011_add_kiosk_inactivity_timeout.php
```

This migration adds:

```text
kiosk_inactivity_timeout_seconds
```

to Company Settings.

Default:

```text
60
```

Allowed application range:

```text
15–600 seconds
```

Existing installations receive the default automatically.

---

## 12. Database Health Command

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks database:check
```

The command reports:

- Overall database status
- Active database path
- Database size
- File readability
- File writability
- Directory writability
- SQLite version
- Journal mode
- Integrity check
- Foreign-key violations
- Application table count
- Applied migration count
- Page size
- Page count
- Unused-page count
- Diagnostic duration

A healthy Version 0.6 result includes:

```text
Status: HEALTHY
Journal mode: wal
Integrity check: ok
Foreign-key violations: 0
Applied migrations: 11
```

Any database-health failure should be investigated before payroll processing continues.

---

## 13. Manual Integrity Check

The preferred method is:

```bash
./iqwurks database:check
```

A direct SQLite integrity check can also be performed:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
PRAGMA integrity_check;
"
```

Expected:

```text
ok
```

Check foreign keys:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
PRAGMA foreign_key_check;
"
```

A healthy result produces no rows.

### Important

A returned result of `ok` confirms SQLite structural integrity, but it does not prove that all business data is logically correct.

Also review:

- Employee records
- Punch sequences
- Payroll warnings
- Correction history
- Report settings
- Notification recipients

---

## 14. Confirming WAL Mode

Run:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
PRAGMA journal_mode;
"
```

Expected:

```text
wal
```

Check the configured busy timeout:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
PRAGMA busy_timeout;
"
```

A direct `sqlite3` shell session may have its own connection-specific timeout.

The authoritative application behavior is configured in:

```text
app/Core/Database.php
```

Run the application health check to confirm application-side configuration:

```bash
./iqwurks database:check
```

---

## 15. WAL Checkpoints

SQLite checkpoints move committed transactions from the WAL file into the main database file.

A manual passive status check can be performed with:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
PRAGMA wal_checkpoint(PASSIVE);
"
```

Do not routinely force checkpoints while the application is under load.

The restore service performs the necessary checkpoint and sidecar handling during controlled restoration.

A truncating checkpoint may be used only during a controlled maintenance or recovery procedure:

```sql
PRAGMA wal_checkpoint(TRUNCATE);
```

Do not run this casually during active kiosk operation.

---

## 16. Backup Architecture

IQwurksPunch does not create production backups by copying the active database file directly.

The backup service uses SQLite’s safe database-copy mechanism:

```text
VACUUM INTO
```

This creates a consistent standalone SQLite database containing committed data, including data represented in WAL mode.

The backup process then verifies:

- SQLite integrity
- Foreign-key integrity
- File readability
- File size
- Backup availability

A backup is considered successful only after verification passes.

---

## 17. Backup Configuration

Backup configuration is stored in:

```text
config/backup.php
```

Validated configuration:

```php
<?php
declare(strict_types=1);

return [
    'directory' =>
        dirname(
            __DIR__
        )
        .
        '/storage/backups',

    'retention_count' =>
        30,

    'lock_file' =>
        dirname(
            __DIR__
        )
        .
        '/storage/cache/backup.lock',
];
```

Default backup directory:

```text
storage/backups
```

Default retention:

```text
30 backups
```

Backup lock:

```text
storage/cache/backup.lock
```

Validate:

```bash
php -l config/backup.php
```

---

## 18. Backup File Naming

Backups use timestamped names similar to:

```text
iqwurks_20260721_141835_c7e72a.sqlite
```

The filename includes:

- Application identifier
- Date
- Time
- Random suffix
- SQLite extension

Do not rename backups unless there is a documented reason.

The restore workflow validates backup filenames and expects backups to be located in the configured backup directory.

---

## 19. Create a Verified Backup

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks backup:create
```

Successful output includes:

- Backup filename
- Full path
- File size
- Integrity result
- Creation time
- Duration

Immediately verify:

```bash
./iqwurks backup:verify
```

A backup should not be considered recoverable until verification passes.

---

## 20. List Available Backups

Run:

```bash
./iqwurks backup:list
```

The command reports:

- Number of backups
- Creation date
- File size
- Filename
- Total backup storage

Review the list before:

- Removing backups
- Applying retention
- Selecting a restore point
- Performing disaster recovery

---

## 21. Verify the Newest Backup

Run:

```bash
./iqwurks backup:verify
```

With no argument, the command verifies the newest available backup.

Verification includes:

- SQLite integrity
- Foreign-key violations
- File readability
- Verification duration

Expected essentials:

```text
[PASS] BACKUP_FILENAME
Integrity: ok
Foreign-key violations: 0
```

---

## 22. Verify a Named Backup

Run:

```bash
./iqwurks backup:verify BACKUP_FILENAME
```

Example:

```bash
./iqwurks backup:verify \
    iqwurks_20260721_141835_c7e72a.sqlite
```

Use this before selecting a specific restore point.

---

## 23. Verify All Backups

Run:

```bash
./iqwurks backup:verify --all
```

Use this during scheduled administrative review.

Investigate any backup that reports:

- Unreadable
- Invalid SQLite database
- Failed integrity check
- Foreign-key violations
- Missing file
- Unexpected size

Do not use a failed backup for restoration.

---

## 24. Complete Automatic Backup Workflow

Run:

```bash
./iqwurks backup:run
```

This command:

1. Acquires the backup lock.
2. Creates a timestamped backup.
3. Verifies the new backup.
4. Applies the configured retention policy.
5. Records the result.
6. Releases the lock.

The command does not accept arguments.

Use `backup:run` for automatic cron execution.

---

## 25. Backup Locking

The backup process uses a lock file to prevent overlapping backup workflows.

Configured lock:

```text
storage/cache/backup.lock
```

Production cron also uses:

```text
/tmp/iqwurks-backup-cron.lock
```

The outer cron lock prevents multiple cron-launched backup commands.

The application lock protects the backup workflow itself.

Do not remove a lock file merely because it exists.

Determine whether a process currently holds the lock.

Useful commands:

```bash
ps aux | grep '[i]qwurks backup'
```

and:

```bash
lsof storage/cache/backup.lock
```

when `lsof` is installed.

---

## 26. Automatic Backup Schedule

The validated production cron entry is:

```cron
# BEGIN IQWURKSPUNCH BACKUP
# Daily verified SQLite backup with automatic retention
CRON_TZ=America/Los_Angeles
15 1 * * * cd /var/www/IQwurksPunch && /usr/bin/flock -n /tmp/iqwurks-backup-cron.lock /usr/bin/php iqwurks backup:run >> storage/logs/cron-backup.log 2>&1
# END IQWURKSPUNCH BACKUP
```

This runs at:

```text
1:15 AM
```

in the configured cron timezone.

Review:

```bash
crontab -l
```

Review backup cron output:

```bash
tail -n 100 storage/logs/cron-backup.log
```

Confirm the newest backup:

```bash
./iqwurks backup:list
```

Verify it:

```bash
./iqwurks backup:verify
```

---

## 27. Backup Retention Preview

Preview backups that exceed a retention limit:

```bash
./iqwurks backup:prune --keep=30
```

Preview mode does not delete files.

Always review:

- Which backups will be kept
- Which backups will be removed
- Backup dates
- Backup sizes
- Available external copies

---

## 28. Apply Backup Retention

After reviewing the preview:

```bash
./iqwurks backup:prune \
    --keep=30 \
    --delete
```

This permanently deletes backups exceeding the requested count.

Before deleting:

1. Confirm the retention count.
2. Confirm the newest backups have passed verification.
3. Confirm required historical recovery points are preserved.
4. Confirm external or off-system copies exist when required.
5. Review available disk capacity.

Deleted backup files cannot be restored through IQwurksPunch unless another copy exists.

---

## 29. External Backup Copies

Backups stored only on the same computer do not protect against:

- Disk failure
- Computer theft
- Fire
- Electrical damage
- Filesystem corruption
- Accidental deletion of the entire application directory
- Operating-system loss

Regularly copy verified backup files to another protected location, such as:

- Encrypted USB storage
- Another trusted server
- A secure network share
- An approved encrypted cloud backup
- Offline removable media

Do not copy:

```text
database/sqlite/iqwurks.sqlite
```

as the normal off-system backup method.

Copy verified files from:

```text
storage/backups
```

---

## 30. Recommended Backup Policy

A basic production policy should include:

### Daily

- Automatic verified backup
- Automatic retention
- Cron log review when a failure occurs

### Weekly

- Verify the newest backup
- Confirm backup age
- Confirm total backup count
- Confirm available disk space

### Monthly

- Verify all retained backups
- Copy selected verified backups off the kiosk computer
- Confirm external backup readability
- Review retention settings
- Perform a controlled recovery exercise when practical

### Before Major Changes

Create a verified backup before:

- Applying migrations
- Upgrading IQwurksPunch
- Changing payroll rules
- Performing database repair
- Modifying production permissions
- Replacing system configuration
- Restoring another backup

---

## 31. Restore Philosophy

A database restore is a destructive production operation.

Restoration replaces the current active database state with the selected backup state.

Data recorded after the selected backup was created may be lost from the active database.

A restore should be used only when:

- The active database is damaged
- A failed upgrade must be rolled back
- Incorrect changes cannot be corrected through supported application workflows
- A verified historical state must be recovered
- Disaster recovery requires replacement of the active database

Do not restore merely to correct one ordinary punch.

Use supervisor punch correction for supported timekeeping adjustments.

---

## 32. Restore Preview

A restore preview performs validation without replacing the active database.

Run:

```bash
./iqwurks backup:restore BACKUP_FILENAME
```

The preview should report:

- Selected backup
- Backup date
- Backup size
- Integrity status
- Foreign-key status
- Intended restore behavior
- Exact command required to apply the restore

Always preview before applying a restore.

---

## 33. Apply a Restore

Apply a restore with:

```bash
./iqwurks backup:restore \
    BACKUP_FILENAME \
    --apply \
    --confirm=BACKUP_FILENAME
```

The confirmation value must exactly match the selected filename.

Example:

```bash
./iqwurks backup:restore \
    iqwurks_20260721_141835_c7e72a.sqlite \
    --apply \
    --confirm=iqwurks_20260721_141835_c7e72a.sqlite
```

The typed confirmation prevents accidental restoration of the wrong backup.

---

## 34. Restore Safety Features

The Version 0.6 restore workflow provides:

- Backup filename validation
- Backup-path validation
- Backup integrity validation
- Foreign-key validation
- Preview mode by default
- Explicit `--apply` requirement
- Exact filename confirmation
- Maintenance-mode integration
- Pre-restore safety backup
- WAL checkpoint handling
- Stale sidecar-file removal
- Controlled active-database replacement
- Ownership and permission handling
- Post-restore database validation
- Migration-history verification
- Rollback handling

Do not bypass these controls by manually replacing the database file.

---

## 35. Pre-Restore Checklist

Before applying a restore:

1. Notify employees and supervisors.
2. Stop normal kiosk use.
3. Confirm the selected backup date.
4. Confirm what data will be lost.
5. Confirm the selected backup passed verification.
6. Confirm the backup contains the expected migration history.
7. Confirm sufficient disk space.
8. Confirm the database directory is writable.
9. Confirm the application path is correct.
10. Confirm the command is being run from the project root.
11. Preview the restore.
12. Record the current application version.
13. Record the current database health result.
14. Confirm another verified backup exists.
15. Be prepared to verify all application functions afterward.

---

## 36. Maintenance Mode During Recovery

Enable maintenance mode before manual recovery work when the restore command has not already done so:

```bash
./iqwurks maintenance:on Database recovery
```

Check:

```bash
./iqwurks maintenance:status
```

After successful recovery:

```bash
./iqwurks maintenance:off
```

Confirm:

```bash
./iqwurks maintenance:status
```

Maintenance state is stored in:

```text
storage/cache/maintenance.json
```

Do not use:

```bash
./iqwurks maintenance:on --help
```

The current console treats everything after `maintenance:on` as the maintenance reason.

---

## 37. What the Restore Workflow Does

A successful restore generally performs these stages:

1. Validate command arguments.
2. Locate the selected backup.
3. Verify backup integrity.
4. Verify backup foreign keys.
5. Activate or confirm maintenance protection.
6. Create a safety backup of the active database.
7. Checkpoint the active WAL.
8. Prepare the database directory.
9. Remove stale WAL, SHM, or journal sidecars as required.
10. Replace the active database.
11. Apply appropriate ownership and permissions.
12. Open the restored database.
13. Run integrity validation.
14. Run foreign-key validation.
15. Verify migration history.
16. Report the safety-backup filename.
17. Complete or roll back the operation.
18. Leave the application in a known state.

Review the command output carefully.

---

## 38. Post-Restore Validation

Immediately after restoration, run:

```bash
./iqwurks database:check
./iqwurks scheduler:check
./iqwurks doctor
```

Confirm:

```text
Database status: HEALTHY
Integrity check: ok
Foreign-key violations: 0
Journal mode: wal
```

Then verify through the application:

- Supervisor login
- Employee list
- Employee active status
- Recent punches
- Punch-correction history
- Company settings
- Kiosk timeout
- Labor rules
- Notification recipients
- Report schedule
- Email history
- Daily payroll report
- Weekly payroll report
- Payroll Workspace
- CSV export
- PDF export

Create a new verified backup after the restored system is accepted:

```bash
./iqwurks backup:run
```

---

## 39. Restored Migration Compatibility

A backup may contain an older migration state.

After restoration:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
SELECT migration
FROM migrations
ORDER BY migration;
"
```

Compare with installed migration files:

```bash
find database/migrations \
    -maxdepth 1 \
    -type f \
    -name '*.php' \
    -printf '%f\n' \
    | sort
```

When the restored database is older than the installed application code, pending migrations may need to be applied:

```bash
php migrate.php
```

Before applying them:

1. Preserve the restore safety backup.
2. Confirm the application version.
3. Confirm the selected backup’s intended age.
4. Review the pending migrations.
5. Run database diagnostics afterward.

---

## 40. Disaster-Recovery Scenario: Damaged Active Database

Symptoms may include:

- Integrity-check failure
- SQLite malformed-database error
- Application HTTP 500 errors
- Payroll pages failing to load
- Database health reporting `FAIL`
- Missing or unreadable database file

Procedure:

1. Stop kiosk use.
2. Enable maintenance mode.
3. Record the exact error.
4. Preserve the damaged database and sidecars.
5. List available backups.
6. Verify candidate backups.
7. Select the newest valid recovery point.
8. Preview the restore.
9. Apply the restore with exact confirmation.
10. Run post-restore diagnostics.
11. Test application functions.
12. Disable maintenance mode.
13. Create a new verified backup.
14. Document the incident.

Do not delete the damaged database until the cause has been understood and required evidence has been preserved.

---

## 41. Disaster-Recovery Scenario: Missing Database File

When the active database file is missing:

1. Stop normal application use.
2. Enable maintenance mode.
3. Confirm the configured database path.
4. Check whether the file was moved.
5. Check filesystem and disk health.
6. List verified backups.
7. Verify the selected backup.
8. Use the restore workflow.
9. Confirm ownership and permissions.
10. Run migrations if required.
11. Run all diagnostics.
12. Test kiosk and supervisor access.
13. Disable maintenance mode.
14. Create a new verified backup.

Do not create an empty replacement database unless performing a deliberate fresh installation.

---

## 42. Disaster-Recovery Scenario: Incorrect Migration

When an upgrade migration causes a problem:

1. Stop kiosk use.
2. Enable maintenance mode.
3. Record the application version.
4. Record migration output.
5. Record database health.
6. Do not rerun the migration repeatedly without analysis.
7. Identify the pre-upgrade verified backup.
8. Verify it.
9. Preview restoration.
10. Restore the pre-upgrade backup when rollback is required.
11. Restore the matching application code version.
12. Run database diagnostics.
13. Test the application.
14. Investigate and correct the migration before another attempt.

Database and application versions must remain compatible.

---

## 43. Disaster-Recovery Scenario: Accidental Punch Changes

Use the application’s punch-correction workflow when possible.

Supervisors can:

- Add a missing punch
- Edit an incorrect punch
- Delete an incorrect punch
- Enter a required reason
- Preserve immutable correction history

Do not restore the entire database to correct one normal timekeeping error.

A full restore would also revert unrelated records created after the selected backup, including:

- Other employee punches
- Employee changes
- Settings changes
- Notification changes
- Email history
- Audit history

---

## 44. Disaster-Recovery Scenario: Disk Failure

When the original disk is unavailable:

1. Install Ubuntu on replacement hardware or storage.
2. Install required packages.
3. Restore the IQwurksPunch application source.
4. Restore system-level configuration.
5. Install Composer dependencies.
6. Recreate runtime directories.
7. Set ownership and permissions.
8. Restore `config/mail.php` securely.
9. Copy a verified database backup into the configured backup directory.
10. Run the supported restore workflow.
11. Apply pending migrations when required.
12. Restore cron entries.
13. Restore Nginx configuration.
14. Restore PHP-FPM configuration.
15. Restore logrotate configuration.
16. Restore physical kiosk configuration.
17. Restore firewall rules.
18. Run diagnostics.
19. Run automated tests when development dependencies are available.
20. Perform reboot validation.

Required system-level files include:

```text
/etc/nginx/sites-available/iqwurks-punch
/etc/php/8.5/fpm/conf.d/99-iqwurks-punch.ini
/etc/lightdm/lightdm.conf.d/50-iqwurks-kiosk.conf
/usr/local/bin/iqwurks-kiosk-browser
/home/kiosk/.config/openbox/autostart
/etc/logrotate.d/iqwurks-punch
```

---

## 45. Manual Database Inspection

Read-only inspection can be performed with:

```bash
sqlite3 database/sqlite/iqwurks.sqlite
```

Useful SQLite shell commands:

```text
.tables
.schema
.headers on
.mode column
.quit
```

Examples:

```sql
SELECT migration
FROM migrations
ORDER BY migration;
```

```sql
SELECT id, employee_number, first_name, last_name, active
FROM employees
ORDER BY employee_number;
```

```sql
SELECT id, employee_id, punch_time, type, source
FROM punches
ORDER BY id DESC
LIMIT 20;
```

Avoid manual writes in production.

Do not run `UPDATE`, `DELETE`, `INSERT`, `ALTER`, or `DROP` statements without:

- A verified backup
- A written repair plan
- Maintenance mode
- Exact record identification
- Post-change validation
- Incident documentation

---

## 46. Database Query Timezone Considerations

Punch timestamps are stored in UTC.

Application reports and views convert timestamps to the configured company timezone.

Direct SQLite queries show the stored UTC values unless conversion is performed manually.

Do not assume a raw timestamp is already in local time.

The validated company timezone is:

```text
America/Los_Angeles
```

Timezone-aware application reporting should be preferred over manual SQL for payroll interpretation.

---

## 47. Punch Correction and Database Integrity

The punch-correction service performs corrections inside database transactions.

Before committing, it validates the resulting employee punch sequence.

When validation fails:

- The correction is rejected.
- The transaction is rolled back.
- The original records remain unchanged.
- No partial correction should remain.

Correction history is immutable and should not be manually edited.

The history provides accountability for payroll-affecting changes.

---

## 48. Database Privacy and Security

The database may contain:

- Employee names
- Employee numbers
- PIN password hashes
- Work attendance history
- Payroll-preparation data
- Supervisor account details
- Email addresses
- Audit records

Protect:

- The active database
- Backup files
- External backup copies
- Console access
- SSH access
- Physical access to the kiosk computer

Do not:

- Email unencrypted database backups casually
- Store backups on public shares
- Commit databases to Git
- Publish backups in issue reports
- Share employee PIN hashes
- Share supervisor password hashes

---

## 49. Git Protection

Runtime database files should be ignored by Git.

The ignore policy includes:

```text
/database/sqlite/*.sqlite
/database/sqlite/*.sqlite-shm
/database/sqlite/*.sqlite-wal
/database/sqlite/*.sqlite-journal
```

Verify:

```bash
git check-ignore -v \
    database/sqlite/iqwurks.sqlite
```

Backups are also ignored:

```text
/storage/backups/*
```

The tracked placeholder remains:

```text
storage/backups/.gitkeep
```

Never force-add a production database or backup to Git.

---

## 50. Database Size and Capacity

Check database size:

```bash
ls -lh database/sqlite/iqwurks.sqlite
```

Check backup storage:

```bash
./iqwurks backup:list
```

Check filesystem capacity:

```bash
df -h /var/www/IQwurksPunch
```

The System Doctor also reports:

- Free disk space
- Total disk space
- Free percentage

Low disk space can affect:

- WAL growth
- Database writes
- Backup creation
- Export generation
- Log creation
- Session creation

Investigate before free capacity becomes critical.

---

## 51. Database Vacuum and Page Utilization

The database health command reports:

- Page size
- Page count
- Unused pages

Do not run manual production vacuum operations routinely without a reason.

Backup creation already uses SQLite’s safe database-copy process and produces compact standalone backup files.

When database optimization is required:

1. Create and verify a backup.
2. Enable maintenance mode.
3. Review disk capacity.
4. Use an approved maintenance procedure.
5. Run integrity checks afterward.
6. Test the application.
7. Create a new verified backup.

---

## 52. Backup Verification Is Not Restore Testing

A verified backup confirms that the backup:

- Opens as SQLite
- Passes integrity checking
- Has no foreign-key violations
- Is readable

It does not prove that:

- The selected backup contains the intended historical data
- All application features work with it
- System-level configuration can be rebuilt
- Operators know the recovery procedure
- Off-system copies are accessible

Periodically perform a controlled recovery exercise.

A recovery exercise should include:

- Selecting a verified backup
- Reviewing restore preview
- Restoring into a controlled environment or approved maintenance window
- Running database diagnostics
- Testing login
- Testing kiosk operation
- Testing reports
- Confirming migration compatibility
- Documenting results

---

## 53. Routine Database Checklist

### Daily

- Confirm automatic backup execution.
- Confirm no backup failure appears in logs.
- Confirm the application is accepting punches.
- Investigate database-related application errors.

### Weekly

Run:

```bash
./iqwurks database:check
./iqwurks backup:list
./iqwurks backup:verify
```

Confirm:

- Database is healthy.
- Journal mode is WAL.
- Foreign-key violations are zero.
- A recent verified backup exists.
- Backup count is reasonable.
- Disk capacity is sufficient.

### Monthly

Run:

```bash
./iqwurks backup:verify --all
./iqwurks backup:prune --keep=30
./iqwurks doctor
```

Also:

- Copy selected verified backups off-system.
- Confirm external copies are readable.
- Review database permissions.
- Review backup retention.
- Review recovery documentation.
- Consider a controlled recovery test.

---

## 54. Post-Upgrade Database Checklist

After upgrading IQwurksPunch:

- [ ] A pre-upgrade verified backup exists.
- [ ] The expected application version is installed.
- [ ] Composer dependencies are correct.
- [ ] All migration files are present.
- [ ] All migrations applied successfully.
- [ ] Migration count is correct.
- [ ] Database health is `HEALTHY`.
- [ ] SQLite journal mode is `wal`.
- [ ] Integrity check is `ok`.
- [ ] Foreign-key violations are zero.
- [ ] Supervisor login works.
- [ ] Employee records are present.
- [ ] Punch history is present.
- [ ] Punch correction works.
- [ ] Company settings are present.
- [ ] Kiosk timeout is configured.
- [ ] Labor rules are present.
- [ ] Notification recipients are present.
- [ ] Report schedule is present.
- [ ] Payroll reports work.
- [ ] CSV exports work.
- [ ] PDF exports work.
- [ ] A post-upgrade verified backup exists.

---

## 55. Recovery Acceptance Checklist

A recovery is complete only when:

- [ ] Maintenance mode is disabled.
- [ ] The active database is readable.
- [ ] The active database is writable.
- [ ] The database directory is writable.
- [ ] Integrity check returns `ok`.
- [ ] Foreign-key violations are zero.
- [ ] Journal mode is `wal`.
- [ ] Migration history matches the installed application.
- [ ] Supervisor login works.
- [ ] The kiosk works.
- [ ] Employee records are correct.
- [ ] Recent punches are correct for the selected recovery point.
- [ ] Correction history is available.
- [ ] Company settings are correct.
- [ ] Labor rules are correct.
- [ ] Notification recipients are correct.
- [ ] Report scheduling is correct.
- [ ] Payroll reports work.
- [ ] Scheduler diagnostics pass.
- [ ] System Doctor has no failures.
- [ ] A new verified backup was created.
- [ ] The incident and recovery were documented.

---

## 56. Commands Reference

### Database Health

```bash
./iqwurks database:check
```

### Create Backup

```bash
./iqwurks backup:create
```

### List Backups

```bash
./iqwurks backup:list
```

### Verify Newest Backup

```bash
./iqwurks backup:verify
```

### Verify Named Backup

```bash
./iqwurks backup:verify BACKUP_FILENAME
```

### Verify All Backups

```bash
./iqwurks backup:verify --all
```

### Preview Retention

```bash
./iqwurks backup:prune --keep=30
```

### Apply Retention

```bash
./iqwurks backup:prune \
    --keep=30 \
    --delete
```

### Complete Backup Workflow

```bash
./iqwurks backup:run
```

### Preview Restore

```bash
./iqwurks backup:restore BACKUP_FILENAME
```

### Apply Restore

```bash
./iqwurks backup:restore \
    BACKUP_FILENAME \
    --apply \
    --confirm=BACKUP_FILENAME
```

### Enable Maintenance

```bash
./iqwurks maintenance:on Database maintenance
```

### Disable Maintenance

```bash
./iqwurks maintenance:off
```

### Apply Migrations

```bash
php migrate.php
```

### System Diagnostics

```bash
./iqwurks scheduler:check
./iqwurks doctor
```

---

## 57. Version 0.6 Validation Baseline

The validated Version 0.6 production database reported:

```text
Status: HEALTHY
SQLite version: 3.46.1
Journal mode: wal
Integrity check: ok
Foreign-key violations: 0
Application tables: 12
Applied migrations: 11
Page size: 4096 bytes
Unused pages: 0
```

The exact database size and page count will increase as operational data is recorded.

The important acceptance conditions are:

```text
Status: HEALTHY
Journal mode: wal
Integrity check: ok
Foreign-key violations: 0
Applied migrations: 11
```

---

## 58. Final Principle

A database backup is valuable only when it is:

- Created successfully
- Verified successfully
- Stored safely
- Available when needed
- Compatible with the installed application
- Understood by the recovery operator
- Tested through a documented recovery process

IQwurksPunch database operations should prioritize data integrity over speed or convenience.
