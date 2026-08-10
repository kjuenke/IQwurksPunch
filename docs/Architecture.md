# IQwurksPunch Architecture Guide

**Applies to:** IQwurksPunch 1.0.0-dev
**Audience:** Developers, maintainers, system administrators, and technical reviewers

---

## 1. Purpose

This document describes the architecture of IQwurksPunch, including:

- Application request flow
- Custom MVC organization
- Dependency resolution
- Supervisor authentication
- Employee kiosk sessions
- Punch recording and correction
- Payroll calculation
- Reporting and email delivery
- Backup and restore
- Diagnostics
- Maintenance mode
- SQLite WAL operation
- Logging
- Production deployment
- Physical kiosk operation
- Security and data-integrity boundaries

IQwurksPunch is designed as a self-contained employee time-clock and payroll-preparation system for a dedicated Linux computer.

---

## 2. Architectural Goals

IQwurksPunch prioritizes:

- Reliability
- Simplicity
- Data integrity
- Auditability
- Local operation
- Minimal external dependencies
- Clear separation of responsibilities
- Recoverability
- Long-term maintainability
- Professional payroll preparation
- Safe employee kiosk operation

The system is intended to remain understandable and maintainable without requiring a large application framework.

---

## 3. High-Level Architecture

```text
Employee or Supervisor Browser
            |
            v
          Nginx
            |
            v
      public/index.php
            |
            v
     Authentication Guard
            |
            v
       Application Router
            |
            v
         Controller
            |
            v
          Service
            |
            v
        Repository
            |
            v
      SQLite Database
```

Additional production processes include:

```text
Cron
  |
  +--> ./iqwurks schedule:run
  |
  +--> ./iqwurks backup:run

Administrator Shell
  |
  +--> ./iqwurks database:check
  +--> ./iqwurks scheduler:check
  +--> ./iqwurks mail:check
  +--> ./iqwurks doctor
  +--> ./iqwurks backup:*
  +--> ./iqwurks maintenance:*

Physical Display
  |
  v
LightDM
  |
  v
Openbox
  |
  v
Chromium Kiosk
  |
  v
http://127.0.0.1/kiosk
```

---

## 4. Project Structure

```text
app/
    Console/
        Commands/
        LazyCommand.php

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
    index.php

releases/
routes/

storage/
    backups/
    cache/
    exports/
    logs/
    sessions/

tests/

composer.json
iqwurks
migrate.php
VERSION
```

---

## 5. Application Entry Points

IQwurksPunch has three primary execution entry points.

### Web Application

```text
public/index.php
```

Responsibilities include:

- Loading Composer autoloading
- Loading maintenance configuration
- Handling maintenance-mode responses
- Configuring PHP sessions
- Starting the session
- Bootstrapping the application
- Enforcing supervisor authentication
- Dispatching the router

### Console Application

```text
iqwurks
```

Responsibilities include:

- Loading Composer autoloading
- Reading the application version
- Registering available commands
- Resolving commands lazily
- Passing command arguments
- Returning process exit codes
- Reporting operational errors

### Migration Runner

```text
migrate.php
```

Responsibilities include:

- Loading migration files
- Comparing migration files with migration history
- Running pending migrations
- Recording successful migrations
- Reporting migration results

---

## 6. Web Request Lifecycle

A normal web request follows this sequence:

```text
1. Nginx receives the HTTP request.
2. Nginx serves an existing static asset directly when applicable.
3. Other requests are routed to public/index.php.
4. Composer autoloading is initialized.
5. Maintenance mode is checked.
6. Session configuration is applied.
7. The PHP session is started.
8. The application is bootstrapped.
9. AuthGuardService evaluates the request path.
10. Public requests continue without supervisor authentication.
11. Protected requests require an active authorized supervisor.
12. The Router matches the HTTP method and path.
13. The selected controller method is called.
14. The controller invokes services.
15. Services apply business rules.
16. Repositories read or write the database.
17. The controller renders Twig or sends a redirect or response.
```

---

## 7. Nginx Layer

The production web server is Nginx.

The Nginx document root is:

```text
/var/www/IQwurksPunch/public
```

Nginx responsibilities include:

- Listening for local and LAN HTTP requests
- Serving static assets
- Routing application requests to `public/index.php`
- Passing PHP execution to PHP-FPM
- Preventing arbitrary PHP-file execution
- Blocking hidden files
- Adding response-security headers
- Applying static-asset caching
- Writing web access and error logs

Only this PHP file is directly executable through Nginx:

```text
public/index.php
```

Requests for other `.php` files return HTTP 404.

This prevents source files under `app`, `config`, `database`, and other directories from being executed directly through the web server.

---

## 8. PHP-FPM Layer

PHP-FPM executes web application requests.

Production PHP-FPM configuration includes:

- Errors hidden from browser output
- Errors logged to the application log directory
- Memory limit
- Execution timeout
- Upload-size limits
- Strict session handling
- HTTP-only session cookies
- SameSite cookie policy
- OPcache
- Company-aligned fallback timezone

Application PHP-FPM error log:

```text
storage/logs/php-fpm-error.log
```

The validated socket is:

```text
/run/php/php8.5-fpm.sock
```

---

## 9. Router

The custom router is implemented by:

```text
app/Core/Router.php
```

Supported route methods include:

```text
GET
POST
```

Routes are registered in:

```text
routes/web.php
```

The router:

1. Reads the request URI.
2. Removes query-string information from path matching.
3. Reads the HTTP method.
4. Iterates registered routes for that method.
5. Converts numeric route parameters into regular-expression matches.
6. Calls the matching controller handler.
7. Returns HTTP 404 when no route matches.

Example route:

```php
$router->get(
    '/kiosk',
    [$kiosk, 'index']
);
```

Example numeric parameter route:

```php
$router->post(
    '/employees/punches/edit/{id}',
    [$punchCorrections, 'update']
);
```

The router currently restricts dynamic parameters to numeric values.

---

## 10. Controllers

Controllers are stored under:

```text
app/Controllers
```

Controllers are responsible for:

- Receiving request data
- Calling application services
- Choosing views
- Setting flash messages
- Returning redirects
- Returning HTTP status responses
- Coordinating request-specific behavior

Controllers should not contain complex persistence logic.

Examples include:

- `AuthController`
- `KioskController`
- `EmployeeController`
- `PunchCorrectionController`
- `SettingsController`
- Report controllers
- Notification controllers

The base controller provides shared view rendering:

```text
app/Controllers/Controller.php
```

---

## 11. Views and Twig

Views are stored under:

```text
app/Views
```

IQwurksPunch uses Twig templates.

The Twig environment is configured by:

```text
app/Core/View.php
```

View responsibilities include:

- HTML presentation
- Forms
- Tables
- Navigation
- Status messages
- User-facing warnings
- Browser-side interaction
- Kiosk inactivity countdown

The shared layout is:

```text
app/Views/layouts/base.twig
```

The layout receives:

- Application name
- Application version
- Company name
- Flash messages
- Page-specific variables

A custom Twig filter is available:

```text
company_date
```

It converts stored UTC timestamps into the configured company timezone.

---

## 12. Dependency Container

Shared application dependencies are resolved through:

```text
app/Core/Container.php
```

The container provides centralized construction of:

- Database connections
- Repositories
- Services
- Exporters
- Reporting components
- Mail components
- Settings components

Controllers generally request services through container methods.

Example:

```php
$this->employees =
    Container::employeeService();
```

Benefits include:

- Centralized dependency wiring
- Reduced repeated construction
- Consistent service configuration
- Easier architectural review
- Clear repository and service ownership

---

## 13. Service Layer

Services are stored under:

```text
app/Services
```

Services implement application and business rules.

Examples include:

- `AuthService`
- `AuthGuardService`
- `EmployeeService`
- `PunchService`
- `PunchCorrectionService`
- `PunchReportService`
- `CompanySettingsService`
- `LaborRulesService`
- `DatabaseBackupService`
- `DatabaseRestoreService`
- `DatabaseHealthService`
- `SchedulerDiagnosticService`
- `MailDiagnosticService`
- `SystemDoctorService`
- `MaintenanceModeService`

Service responsibilities include:

- Input normalization
- Validation
- Business-rule enforcement
- Transactions
- Coordinating repositories
- Applying payroll rules
- Formatting structured results
- Preventing unsafe operations

Controllers should delegate meaningful business logic to services.

---

## 14. Repository Layer

Repositories are stored under:

```text
app/Repositories
```

Repositories are responsible for database persistence.

Examples include:

- `UserRepository`
- `EmployeeRepository`
- `PunchRepository`
- `PunchCorrectionHistoryRepository`
- `CompanySettingsRepository`
- `LaborRulesRepository`
- `NotificationRecipientRepository`
- `ReportScheduleRepository`
- `EmailRepository`
- `AuditRepository`

Repositories should:

- Encapsulate SQL
- Bind parameters
- Return normalized rows
- Avoid presentation logic
- Avoid HTTP concerns
- Participate in transactions controlled by services

---

## 15. SQLite Connection

The primary SQLite connection is managed by:

```text
app/Core/Database.php
```

The connection configures:

```sql
PRAGMA foreign_keys = ON;
PRAGMA busy_timeout = 10000;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA wal_autocheckpoint = 1000;
```

It also configures PDO to use exceptions and appropriate fetch behavior.

The database connection sets a restrictive process `umask` so SQLite-created sidecar files remain group-writable by the production application group.

---

## 16. SQLite WAL Architecture

The active database is:

```text
database/sqlite/iqwurks.sqlite
```

WAL operation may create:

```text
database/sqlite/iqwurks.sqlite-wal
database/sqlite/iqwurks.sqlite-shm
```

WAL mode improves concurrency between:

- Employee kiosk requests
- Supervisor requests
- Scheduler executions
- Backup operations
- Diagnostic commands

The database directory must be writable because SQLite creates and manages sidecar files there.

The application’s backup and restore systems are WAL-aware.

---

## 17. Migration Architecture

Migration files are stored under:

```text
database/migrations
```

Migration history is stored in the database.

Each migration provides an `up()` method.

Some migrations also document a `down()` method, although SQLite column removal may require a table rebuild.

The migration architecture includes:

```text
010_add_punch_correction_support.php
011_add_kiosk_inactivity_timeout.php
```

The migration runner:

- Finds migration files
- Compares them to recorded migration history
- Runs only pending migrations
- Records successful migrations
- Stops and reports failures

A migration should never be recorded if its operation fails.

---

## 18. Supervisor Authentication Architecture

Supervisor authentication uses:

- `AuthController`
- `AuthService`
- `UserRepository`
- `AuthGuardService`
- PHP session storage

Login flow:

```text
1. Supervisor submits username and password.
2. AuthController passes credentials to AuthService.
3. AuthService loads the user through UserRepository.
4. Password verification is performed.
5. Active-account status is checked.
6. Authorized role is checked.
7. The PHP session identifier is regenerated.
8. Supervisor identity and role are stored in session.
9. Last-login information is updated.
10. The supervisor is redirected to the intended or default page.
```

Authorized roles are:

```text
admin
supervisor
```

---

## 19. Authentication Guard

Administrative request protection is centralized in:

```text
app/Services/AuthGuardService.php
```

The guard runs before router dispatch.

Public paths include:

- Application landing page
- Login
- Logout
- Setup
- Employee kiosk routes

All other paths require supervisor authentication.

For a protected request, the guard:

1. Reads the session user ID.
2. Loads the current user from the database.
3. Confirms the user still exists.
4. Confirms the account is active.
5. Confirms the role is authorized.
6. Refreshes normalized session identity.
7. Updates supervisor session activity.

When validation fails, the guard:

- Clears supervisor session data
- Preserves a safe intended GET URL when appropriate
- Sets a flash message
- Redirects to `/login`

This prevents removed, deactivated, or unauthorized users from retaining administrative access through an old session.

---

## 20. Session Architecture

Session configuration is applied in:

```text
public/index.php
```

Application sessions use:

```text
storage/sessions
```

Session cookie name:

```text
IQWURKSPUNCHSESSID
```

Session protections include:

- Strict session mode
- Cookie-only sessions
- HTTP-only cookies
- SameSite=Lax
- Secure cookies when HTTPS is detected
- Local session storage
- Session regeneration after login
- Session destruction during logout

The application uses one PHP session for both:

- Supervisor authentication state
- Temporary employee kiosk state

The two state groups use separate session keys.

---

## 21. Employee Kiosk Architecture

The employee kiosk is controlled by:

```text
app/Controllers/KioskController.php
```

Views include:

```text
app/Views/kiosk/index.twig
app/Views/kiosk/pin.twig
app/Views/kiosk/actions.twig
app/Views/kiosk/_clock.twig
app/Views/kiosk/_inactivity_timeout.twig
```

Kiosk flow:

```text
Employee-number screen
        |
        v
Employee lookup
        |
        v
PIN screen
        |
        v
PIN verification
        |
        v
Current-status and action screen
        |
        v
Punch creation
        |
        v
Clean employee-number screen
```

The kiosk does not require a supervisor login.

---

## 22. Kiosk Session State

Temporary kiosk session keys include:

```text
kiosk_employee_id
kiosk_employee_name
kiosk_authenticated
kiosk_last_activity
```

The kiosk starting page clears all temporary kiosk state.

This ensures a new transaction starts cleanly.

A valid employee-number submission creates a temporary kiosk transaction.

Successful PIN verification marks the temporary transaction as authenticated.

After a punch attempt, kiosk state is cleared.

Invalid employee lookup, invalid PIN, timeout, or stale form submission also clears kiosk state.

---

## 23. Kiosk Inactivity Architecture

The timeout value is stored in Company Settings:

```text
kiosk_inactivity_timeout_seconds
```

Allowed range:

```text
15–600 seconds
```

Default:

```text
60 seconds
```

The browser timer is implemented in:

```text
app/Views/kiosk/_inactivity_timeout.twig
```

It monitors:

- Keyboard activity
- Pointer activity
- Mouse movement
- Touch activity
- Input events
- Change events
- Wheel events
- Form submission

The browser:

1. Starts a local deadline.
2. Displays a warning during the final ten seconds.
3. Resets the deadline when activity occurs.
4. Sends throttled activity heartbeats to the server.
5. Uses `location.replace()` when the deadline expires.
6. Returns to the clean kiosk starting page.

The server independently tracks:

```text
kiosk_last_activity
```

Expired PIN or punch forms are rejected even when browser-side controls are bypassed.

This provides defense in depth.

---

## 24. Punch Recording Architecture

Normal kiosk punches are processed by:

- `KioskController`
- `PunchService`
- `PunchRepository`

The kiosk submits a punch type such as:

```text
clock_in
clock_out
```

The service:

- Validates the employee
- Validates the requested action
- Determines current status
- Enforces permitted transitions
- Stores the punch
- Returns a structured success or failure result

Punch timestamps are stored in UTC.

Application display converts them to the company timezone.

---

## 25. Punch State Model

IQwurksPunch supports punch types including:

```text
clock_in
clock_out
break_out
break_in
meal_out
meal_in
```

These represent transitions between employee states.

A valid sequence must not contain impossible transitions such as:

- Clocking out before clocking in
- Starting a break when not working
- Ending a break that has not started
- Starting a meal while already in a meal
- Ending a meal that has not started
- Creating overlapping inactive states

The payroll and correction services review punch sequences before using or changing them.

---

## 26. Punch Correction Architecture

Punch correction uses:

- `PunchCorrectionController`
- `PunchCorrectionService`
- `PunchRepository`
- `PunchCorrectionHistoryRepository`
- Audit services

Supervisor actions include:

- Add punch
- Edit punch
- Delete punch

Each correction requires:

- Authorized supervisor
- Employee
- Date and time
- Punch type
- Correction reason
- Typed confirmation for deletion

---

## 27. Punch Correction Transactions

Punch correction is performed inside a database transaction.

Conceptual flow:

```text
1. Begin transaction.
2. Load employee and affected punch data.
3. Normalize submitted values.
4. Require a correction reason.
5. Apply the proposed insertion, update, or deletion.
6. Load the resulting employee punch sequence.
7. Validate the complete sequence.
8. Create immutable correction history.
9. Create application audit record.
10. Commit the transaction.
```

When any stage fails:

```text
1. Roll back the transaction.
2. Preserve original punch records.
3. Do not create partial history.
4. Return a structured validation error.
```

---

## 28. Immutable Correction History

Punch correction history is stored separately from the current punch record.

It preserves:

- Correction action
- Employee
- Punch identifier
- Original values
- Corrected values
- Correction reason
- Correcting supervisor
- Correction timestamp

Correction history is not intended to be updated or deleted through normal application workflows.

This supports payroll accountability and post-event review.

---

## 29. Payroll Architecture

Payroll calculation components are stored under:

```text
app/Payroll
```

Payroll coordination is provided by services such as:

```text
PunchReportService
LaborRulesService
```

Payroll processing uses:

- Stored UTC punches
- Company timezone
- Punch rounding rules
- Meal policy
- Break policy
- Daily overtime threshold
- Weekly overtime threshold
- Double-time threshold
- Workweek start day

---

## 30. Payroll Timezone Boundaries

Punches are stored in UTC.

Payroll grouping occurs using the configured company timezone.

This affects:

- Local work date
- Daily payroll boundaries
- Workweek boundaries
- Report periods
- Scheduled delivery timing
- Displayed timestamps

The company timezone is loaded from Company Settings.

Invalid timezone configuration falls back to:

```text
America/Los_Angeles
```

---

## 31. Payroll Classification

Payroll results distinguish:

```text
Regular Hours
Daily Overtime
Weekly Overtime
Double-Time
Total Overtime
Premium Hours
Total Payable Hours
```

Relationships:

```text
Total Overtime = Daily Overtime + Weekly Overtime

Premium Hours = Total Overtime + Double-Time

Payable Hours = Regular + Total Overtime + Double-Time
```

Weekly overtime conversion is limited to hours that remain regular after daily overtime and double-time classification.

This prevents double counting.

---

## 32. Labor Rules Architecture

Labor Rules is the authoritative source for:

- Daily overtime threshold
- Weekly overtime threshold
- Double-time threshold
- Workweek start day

The architecture includes:

- Labor Rules controller
- Labor Rules service
- Labor Rules repository
- Labor Rules settings view
- Database storage
- Validation

Legacy values in Company Settings remain available only as compatibility fallbacks.

---

## 33. Reporting Architecture

Reports are generated from shared payroll-service results.

Report types include:

- Daily payroll
- Weekly payroll
- Date-range Payroll Workspace
- Employee time cards
- Punch reports

Shared calculation structures are used by:

- Web reports
- Dashboard totals
- CSV exports
- PDF exports
- Email reports

This reduces disagreement between output formats.

---

## 34. Export Architecture

CSV and PDF exporters transform shared payroll result structures.

CSV exports include:

- Daily payroll
- Weekly payroll
- Payroll Workspace

PDF exports include:

- Daily payroll
- Weekly payroll register
- Payroll Workspace
- Employee time card

Exporters should not independently recalculate payroll rules.

They should format results produced by the payroll service layer.

---

## 35. Email Architecture

Email delivery uses:

- SMTP configuration
- Mail service
- Report email service
- Notification recipient repository
- Email history repository
- Logging

SMTP secrets are stored in:

```text
config/mail.php
```

Recipients are stored in the database.

This separates:

```text
Transport configuration
```

from:

```text
Business recipients
```

Email history records delivery attempts and status.

---

## 36. Scheduler Architecture

The scheduler command is:

```text
schedule:run
```

Production cron runs it once per minute.

Flow:

```text
1. Cron launches the console command.
2. flock prevents overlapping cron execution.
3. The command loads report-schedule settings.
4. Company timezone is resolved.
5. Current local schedule eligibility is evaluated.
6. Active subscribed recipients are loaded.
7. Duplicate-send protection is checked.
8. The payroll report is generated.
9. Email delivery is attempted.
10. Schedule state and email history are updated.
11. Structured logs are written.
```

The scheduler can safely return:

```text
No scheduled report is due.
```

without treating that condition as a failure.

---

## 37. Backup Architecture

Backup components include:

- `DatabaseBackupService`
- `DatabaseBackupVerificationService`
- `DatabaseBackupCatalogService`
- `DatabaseBackupRetentionService`
- Backup console commands
- Backup configuration
- Backup logging
- Cron integration

Backups are stored under:

```text
storage/backups
```

Backup creation uses SQLite:

```text
VACUUM INTO
```

This creates a consistent standalone copy of committed data while the live application remains in WAL mode.

---

## 38. Backup Workflow

The complete `backup:run` flow is:

```text
1. Acquire application backup lock.
2. Create timestamped backup.
3. Verify SQLite integrity.
4. Verify foreign-key integrity.
5. Record backup metadata.
6. Apply configured retention.
7. Record success or failure.
8. Release the lock.
```

Production cron also uses an outer `flock` lock.

Default retention:

```text
30 backups
```

---

## 39. Restore Architecture

Restore uses:

- `DatabaseRestoreService`
- Backup verification
- Backup catalog
- Maintenance mode
- SQLite checkpoint handling
- Safety backup creation

Restore is preview-only unless both are supplied:

```text
--apply
--confirm=BACKUP_FILENAME
```

The confirmation must exactly match the selected filename.

---

## 40. Restore Workflow

Conceptual restore flow:

```text
1. Parse and validate arguments.
2. Locate selected backup.
3. Verify backup integrity.
4. Verify backup foreign keys.
5. Enter maintenance protection.
6. Create safety backup of active database.
7. Checkpoint active WAL.
8. Remove stale sidecar files where required.
9. Replace active database.
10. Apply ownership and permissions.
11. Reopen restored database.
12. Run integrity check.
13. Run foreign-key check.
14. Verify migration history.
15. Report restored and safety-backup filenames.
16. Roll back when a critical stage fails.
```

Manual replacement of the production database is discouraged because it bypasses these protections.

---

## 41. Database Health Architecture

Database health is provided by:

```text
DatabaseHealthService
```

and exposed through:

```text
database:check
```

Checks include:

- File existence
- File readability
- File writability
- Directory writability
- SQLite version
- Journal mode
- Integrity result
- Foreign-key violations
- Application table count
- Migration count
- Page statistics
- Database size
- Diagnostic duration

Results are structured for both direct console display and use by the System Doctor.

---

## 42. Diagnostic Architecture

Operational diagnostics are divided into focused services.

### Scheduler Diagnostic Service

Checks:

- Cron
- Lock
- Logs
- Failures
- Timezone
- Schedule
- Recipients
- Last delivery

### Mail Diagnostic Service

Checks:

- SMTP configuration
- Host resolution
- Connectivity
- TLS readiness
- Delivery-log activity

### System Doctor Service

Aggregates:

- Application checks
- PHP checks
- Configuration checks
- Runtime-directory checks
- Disk checks
- Database checks
- Migration checks
- Backup checks
- Scheduler checks
- Mail checks

Results use:

```text
PASS
WARN
FAIL
```

---

## 43. Maintenance Mode Architecture

Maintenance mode uses:

```text
MaintenanceModeService
```

Configuration:

```text
config/maintenance.php
```

State file:

```text
storage/cache/maintenance.json
```

The state contains information such as:

- Active status
- Reason
- Activation time

`public/index.php` checks maintenance state before normal application bootstrap.

When active, web requests receive:

- Maintenance response
- Retry guidance
- Maintenance reason
- Appropriate HTTP status

Console recovery commands remain available.

---

## 44. Logging Architecture

Logging components are stored under:

```text
app/Logging
```

They include:

- Logger interface
- Log handler interface
- File log handler
- Log levels
- Logger factory

Application components log to focused channels.

Examples include:

```text
scheduler.log
mail.log
cron-scheduler.log
cron-backup.log
php-fpm-error.log
```

Logging goals include:

- Operational traceability
- Failure visibility
- Diagnostic timestamps
- Duration reporting
- Contextual metadata
- Separation of structured application logs from raw cron output

---

## 45. Log Rotation Architecture

System log rotation is configured outside the repository:

```text
/etc/logrotate.d/iqwurks-punch
```

Application logs use:

- Daily rotation
- 5 MB size threshold
- Thirty retained rotations
- Compression
- Date-based archive names
- Recreated files owned by `www-data`

Kiosk browser logs use:

- Daily rotation
- 5 MB threshold
- Fourteen retained rotations
- Compression
- `copytruncate`
- Ownership by `kiosk`

A newly rotated empty mail log may cause a temporary diagnostic warning.

---

## 46. Local Frontend Assets

Primary interface assets are stored under:

```text
public/assets/vendor
```

Included libraries:

- Bootstrap 5.3.7
- Bootstrap Icons 1.13.1

The architecture no longer requires external CDN access for:

- Bootstrap CSS
- Bootstrap JavaScript
- Bootstrap Icons CSS
- Bootstrap Icons fonts

A checksum manifest is stored at:

```text
public/assets/vendor/SHA256SUMS
```

This supports:

- Offline kiosk operation
- Predictable asset availability
- Integrity verification
- Reduced external runtime dependency

---

## 47. Console Architecture

The console entry point is:

```text
iqwurks
```

Command classes are stored under:

```text
app/Console/Commands
```

Lazy loading is provided by:

```text
app/Console/LazyCommand.php
```

Available console commands include:

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

The console does not currently implement universal per-command `--help`.

Arguments are interpreted by each command individually.

---

## 48. Physical Kiosk Architecture

The dedicated kiosk uses system components outside the repository.

```text
LightDM
   |
   v
Automatic login as kiosk
   |
   v
Openbox
   |
   v
Openbox autostart
   |
   v
iqwurks-kiosk-browser
   |
   v
Chromium full-screen
   |
   v
http://127.0.0.1/kiosk
```

Important system files:

```text
/etc/lightdm/lightdm.conf.d/50-iqwurks-kiosk.conf
/usr/local/bin/iqwurks-kiosk-browser
/home/kiosk/.config/openbox/autostart
```

---

## 49. Kiosk Browser Recovery

The kiosk browser launcher is a persistent Bash loop.

Flow:

```text
1. Check whether the local kiosk URL responds.
2. If unavailable, log and wait two seconds.
3. Retry until the application responds.
4. Start Chromium in kiosk mode.
5. Wait for Chromium to exit.
6. Log the exit code.
7. Wait two seconds.
8. Restart Chromium.
```

This protects against:

- Browser crashes
- Browser termination
- Application startup delay
- Web-server startup delay
- Reboot timing differences

---

## 50. Physical Kiosk Security Boundary

The kiosk Linux account:

- Is separate from the root account
- Has no supervisor application session by default
- Has no `sudo` access
- Starts only the local kiosk workflow
- Uses Chromium incognito mode
- Uses full-screen kiosk mode
- Does not expose normal browser navigation controls

Application-level inactivity protection prevents an unfinished employee transaction from remaining available indefinitely.

---

## 51. Production Service Architecture

Primary production services include:

```text
nginx
php8.5-fpm
cron
lightdm
snapd
logrotate.timer
```

Optional monitoring includes:

```text
Netdata
```

Service responsibilities:

### Nginx

HTTP entry point and static files.

### PHP-FPM

PHP web request execution.

### Cron

Scheduler and backup command execution.

### LightDM

Graphical kiosk session startup.

### Snapd

Chromium package support in the validated deployment.

### Logrotate

Runtime log retention.

---

## 52. Network Architecture

Validated network:

```text
Server: 192.168.1.82
Trusted LAN: 192.168.1.0/24
Gateway: 192.168.1.254
```

Allowed LAN services:

```text
22/tcp       SSH
80/tcp       IQwurksPunch
19999/tcp    Netdata
```

The application uses HTTP only within the trusted LAN.

HTTPS is required before exposing IQwurksPunch through an untrusted network.

---

## 53. Security Boundaries

IQwurksPunch uses several security boundaries.

### Web Root Boundary

Only `public` is exposed through Nginx.

### Authentication Boundary

Administrative pages require a revalidated active supervisor.

### Kiosk Boundary

Employee kiosk routes are public but limited to employee punch workflows.

### Session Boundary

Supervisor and kiosk state use separate session keys.

### Database Boundary

Repositories encapsulate normal database access.

### Correction Boundary

Punch corrections require reasons, validation, transactions, and history.

### Restore Boundary

Restore requires preview, explicit application, and exact confirmation.

### Network Boundary

Firewall access is limited to the trusted LAN.

### Secret Boundary

SMTP secrets remain in ignored local configuration.

---

## 54. Runtime Data Boundaries

Runtime data is excluded from Git.

Ignored categories include:

- SQLite databases
- SQLite sidecar files
- Logs
- Backups
- Sessions
- Cache files
- Temporary files
- SMTP secrets
- Local safety copies

Tracked placeholders preserve required empty runtime directories.

This separates:

```text
Application source
```

from:

```text
Production runtime state
```

---

## 55. Error Handling

Error handling occurs at several levels.

### Validation Errors

Returned by services and shown to the user.

### Business-Rule Errors

Examples:

- Invalid punch sequence
- Invalid labor-rule thresholds
- Unauthorized correction
- Expired kiosk transaction

### Persistence Errors

PDO exceptions prevent silent database failures.

### Operational Errors

Console commands return error messages and nonzero exit codes.

### Web Runtime Errors

PHP-FPM logs application errors rather than displaying them in production.

### Diagnostic Warnings

Nonfatal conditions are reported as `WARN`.

Failures that threaten safe operation are reported as `FAIL`.

---

## 56. Transaction Boundaries

Database transactions are used when an operation must either complete fully or leave no partial state.

Important examples include:

- Punch correction
- Immutable correction-history creation
- Related audit logging
- Restore-stage database replacement and validation

A transaction should cover all database changes required for one logical operation.

External actions such as SMTP delivery require separate failure handling because they cannot be rolled back through SQLite.

---

## 57. Auditability

IQwurksPunch records audit information for important administrative activity.

Audit-related data may include:

- Acting supervisor
- Action name
- Record identifier
- Description
- Timestamp
- Correction reason
- Before and after values

Punch-correction history is immutable and separate from general audit records.

This protects payroll-affecting change history.

---

## 58. Time Handling

IQwurksPunch follows this model:

```text
Storage: UTC
Display: Company timezone
Scheduling: Company timezone
```

UTC storage avoids ambiguity across:

- Daylight-saving changes
- Server timezone differences
- Report generation
- Historical comparison

Views and reports convert timestamps using the configured company timezone.

---

## 59. Configuration Architecture

Application configuration is stored under:

```text
config
```

Important files include:

```text
config/backup.php
config/maintenance.php
config/mail.php
config/mail.example.php
config/logging.php
```

Configuration categories:

### Source-Controlled Configuration

Examples:

- Backup directory and retention defaults
- Maintenance state location
- Logging structure
- Example mail configuration

### Local Secret Configuration

Example:

```text
config/mail.php
```

This file is ignored by Git and permission-restricted.

### Database-Managed Configuration

Examples:

- Company settings
- Labor rules
- Notification recipients
- Report schedule
- Kiosk inactivity timeout

---

## 60. Testing Architecture

Automated tests are stored under:

```text
tests
```

The current Version 1.0 development suite contains:

```text
448 tests
3323 assertions
```

Test coverage includes:

- Payroll rules
- Overtime classification
- Workweek handling
- Export behavior
- Punch correction
- Required correction reasons
- Sequence validation
- Immutable history
- Transaction rollback
- Payroll-period lifecycle and removal safeguards
- Supervisor account-management authorization
- Authentication and session expiration
- Installation, upgrade, backup, restore, and package verification

Tests run with:

```bash
php vendor/bin/phpunit
```

---

## 61. Architectural Validation Commands

### PHP Syntax

```bash
php -l PATH_TO_FILE.php
```

### Automated Tests

```bash
php vendor/bin/phpunit
```

### Database Health

```bash
./iqwurks database:check
```

### Scheduler Health

```bash
./iqwurks scheduler:check
```

### Mail Health

```bash
./iqwurks mail:check
```

### Complete Diagnostics

```bash
./iqwurks doctor
```

### Asset Integrity

```bash
sha256sum --check public/assets/vendor/SHA256SUMS
```

### Nginx Configuration

```bash
nginx -t
```

### PHP-FPM Configuration

```bash
php-fpm8.5 -t
```

---

## 62. Current Architectural Limitations

The current architecture does not include:

- Universal command-option parsing
- Universal per-command help
- REST API
- Multiple companies
- Multiple locations
- Independently configured multiple kiosks
- Database abstraction for other engines
- Browser-based installation automation
- Built-in HTTPS provisioning
- Distributed worker processing
- High-availability database operation

These are potential future architectural extensions.

---

## 63. Version 1.0 Administrative Architecture

Version 1.0 extends the established architecture with:

- Persisted payroll-period records and lifecycle metadata
- Active and removed payroll-period views
- Review, approval, locking, reopening, archival, and voiding workflows
- Protected deletion for untouched draft periods
- Company-timezone employee and punch analysis
- Lock-aware punch correction and payroll exports
- Immutable payroll-period workflow history
- Administrator-controlled supervisor account management
- Last-administrator and active-session lockout protection
- Dedicated account-management audit activity
- Eight-hour supervisor-session inactivity expiration
- CSRF-token rotation after authentication

These capabilities preserve the existing principles of:

- Service-layer validation
- Repository persistence
- Transaction safety
- Immutable history
- Explicit authorization
- Shared payroll result structures

---

## 64. Architectural Principles

New IQwurksPunch work should follow these principles:

1. Controllers coordinate requests.
2. Services own business rules.
3. Repositories own SQL.
4. Views own presentation.
5. UTC is used for stored timestamps.
6. Company timezone is used for business interpretation.
7. Payroll calculations have one authoritative implementation.
8. Administrative access is revalidated.
9. Kiosk access remains simple and restricted.
10. Payroll-affecting corrections require reasons and history.
11. Multi-record changes use transactions.
12. Backups must be verified.
13. Restores require explicit confirmation.
14. Runtime data stays out of Git.
15. Production errors are logged, not displayed.
16. Diagnostics should provide actionable results.
17. External runtime dependencies should be minimized.
18. Recovery behavior is part of feature completeness.

---

## 65. Final Architecture Summary

IQwurksPunch 1.0 consists of:

```text
Custom PHP MVC application
Twig interface
Service and repository layers
SQLite WAL database
Supervisor authentication guard
Public employee kiosk
Server-enforced kiosk timeout
Payroll calculation engine
CSV and PDF exporters
SMTP email reporting
Cron scheduler
Verified backup system
Guarded restore system
Operational diagnostics
Maintenance mode
Centralized logging
Nginx and PHP-FPM deployment
LightDM, Openbox, and Chromium physical kiosk
LAN firewall protection
```

The architecture is designed to keep employee operation simple while giving supervisors and administrators reliable control over payroll preparation, corrections, recovery, and production operation.
