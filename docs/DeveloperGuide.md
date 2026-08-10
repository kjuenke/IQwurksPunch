# IQwurksPunch Developer Guide

**Applies to:** IQwurksPunch 1.0.0
**Audience:** Developers, maintainers, contributors, and release reviewers

---

## 1. Purpose

This guide documents the development workflow and technical conventions used by IQwurksPunch.

It covers:

- Local project setup
- Application structure
- Controllers, services, and repositories
- Dependency registration
- Routing
- Twig views
- Database migrations
- SQLite transactions and WAL behavior
- Console commands
- Authentication
- Employee kiosk development
- Punch correction
- Payroll calculations
- Logging
- Automated testing
- Manual validation
- Git and release preparation

IQwurksPunch uses a custom PHP MVC architecture rather than a large application framework.

New work should preserve the project’s existing separation of responsibilities and operational reliability.

---

## 2. Development Principles

All development should prioritize:

- Correctness
- Data integrity
- Clear business rules
- Simple employee operation
- Secure supervisor access
- Auditability
- Recoverability
- Maintainable code
- Actionable errors
- Backward-compatible upgrades
- Production readiness

A feature is not complete merely because the primary screen works.

Completion also includes:

- Validation
- Error handling
- Logging
- Database safety
- Regression testing
- Documentation
- Upgrade behavior
- Recovery behavior

---

## 3. Supported Development Environment

The validated Version 1.0 development environment includes:

```text
Ubuntu Linux
PHP 8.5.4
SQLite 3.46.1
Composer 2.9.5
Git 2.53.0
PHPUnit 12.5.31
Nginx
PHP-FPM 8.5
```

The active project path is:

```text
/var/www/IQwurksPunch
```

Confirm the environment:

```bash
cd /var/www/IQwurksPunch

php -v
sqlite3 --version
composer --version
git --version
php vendor/bin/phpunit --version
```

---

## 4. Install Development Dependencies

From the project root:

```bash
cd /var/www/IQwurksPunch

composer install
```

Confirm autoloading:

```bash
php -r '
require "vendor/autoload.php";
echo "Composer autoload OK." . PHP_EOL;
'
```

Development dependencies include PHPUnit.

Production-only installations may use:

```bash
composer install \
    --no-dev \
    --optimize-autoloader
```

Release validation requires development dependencies so the automated suite can run.

---

## 5. Project Structure

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

### Source Directories

```text
app/
bootstrap/
config/
database/migrations/
public/
routes/
tests/
```

### Documentation Directories

```text
docs/
releases/
```

### Runtime Directories

```text
database/sqlite/
storage/backups/
storage/cache/
storage/exports/
storage/logs/
storage/sessions/
```

Runtime-generated files must not be committed.

---

## 6. PHP Coding Baseline

Every PHP source file should begin with:

```php
<?php
declare(strict_types=1);
```

Use:

- Strict types
- Explicit parameter types
- Explicit return types
- Descriptive names
- Small focused methods
- Prepared SQL statements
- Structured arrays with documented shapes
- Exceptions for unexpected failures
- Validation results for expected user errors

Avoid:

- Global mutable state
- Raw SQL in controllers
- Business logic in Twig
- Silent exception suppression
- Unvalidated request data
- Direct production database editing
- Duplicate payroll calculations
- Hard-coded SMTP recipients

---

## 7. PHP Syntax Validation

Every modified PHP file must pass:

```bash
php -l PATH_TO_FILE.php
```

Examples:

```bash
php -l app/Controllers/KioskController.php
php -l app/Services/PunchCorrectionService.php
php -l app/Core/Database.php
php -l public/index.php
php -l routes/web.php
```

A syntax error blocks further testing and release preparation.

### Validate All PHP Files

A complete syntax sweep can be run with:

```bash
find app bootstrap config database/migrations public routes tests \
    -type f \
    -name '*.php' \
    -print0 \
    | xargs -0 -n1 php -l
```

Review every result.

---

## 8. MVC Responsibilities

IQwurksPunch uses these primary layers:

```text
Request
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
SQLite
```

### Controller

Responsible for:

- Reading HTTP input
- Calling services
- Setting flash messages
- Selecting views
- Redirecting
- Returning HTTP responses

### Service

Responsible for:

- Business rules
- Validation
- Normalization
- Transactions
- Coordinating repositories
- Structured operation results

### Repository

Responsible for:

- SQL
- Parameter binding
- Row retrieval
- Inserts
- Updates
- Deletes
- Persistence-specific queries

### View

Responsible for:

- Presentation
- Forms
- Tables
- Messages
- User interaction
- Browser-side behavior

Do not move business rules into controllers or views for convenience.

---

## 9. Controllers

Controllers are stored under:

```text
app/Controllers
```

Controllers generally extend the shared base controller.

Typical responsibilities:

```php
public function index(): void
{
    $employees =
        $this->employeeService->all();

    $this->render(
        'employees/index.twig',
        [
            'employees' =>
                $employees,
        ]
    );
}
```

Controllers should:

- Pass normalized request data to services
- Handle expected validation failures cleanly
- Avoid opening database transactions directly
- Avoid duplicating service rules
- Redirect after successful POST requests
- Preserve useful user feedback

Use the Post/Redirect/Get pattern where appropriate.

---

## 10. Services

Services are stored under:

```text
app/Services
```

A service should own one coherent business capability.

Examples:

```text
AuthService
AuthGuardService
CompanySettingsService
PunchCorrectionService
DatabaseBackupService
DatabaseRestoreService
DatabaseHealthService
SchedulerDiagnosticService
MailDiagnosticService
SystemDoctorService
MaintenanceModeService
```

Service methods should return structured, predictable results.

Example shape:

```php
return [
    'success' =>
        true,

    'message' =>
        'Punch corrected successfully.',

    'punch_id' =>
        $punchId,
];
```

Expected validation problems should be reported clearly.

Unexpected persistence or system failures should be logged and propagated appropriately.

---

## 11. Repositories

Repositories are stored under:

```text
app/Repositories
```

Repositories encapsulate SQL and database-specific concerns.

Example pattern:

```php
$statement =
    $this->pdo->prepare(
        '
            SELECT
                id,
                employee_number,
                first_name,
                last_name,
                active
            FROM employees
            WHERE id = :id
            LIMIT 1
        '
    );

$statement->execute(
    [
        'id' =>
            $employeeId,
    ]
);
```

Repository requirements:

- Use prepared statements.
- Bind external values.
- Return `null` when a single optional row is absent.
- Return arrays for collections.
- Keep SQL formatting readable.
- Avoid HTML or HTTP behavior.
- Do not silently catch PDO exceptions.
- Do not commit or roll back transactions owned by a service.

---

## 12. Dependency Container

Shared dependencies are resolved through:

```text
app/Core/Container.php
```

When adding a repository or service:

1. Create the class.
2. Add any required imports to `Container.php`.
3. Add a static property when the dependency is shared.
4. Add a resolver method.
5. Construct dependencies through other container methods.
6. Use the new resolver in controllers or commands.
7. Validate the container file with `php -l`.
8. Run relevant tests.

Example conceptual resolver:

```php
public static function punchCorrectionService(): PunchCorrectionService
{
    if (
        self::$punchCorrectionService
        ===
        null
    ) {
        self::$punchCorrectionService =
            new PunchCorrectionService(
                self::database(),
                self::punchRepository(),
                self::punchCorrectionHistoryRepository(),
                self::auditService()
            );
    }

    return self::$punchCorrectionService;
}
```

Avoid constructing duplicate service graphs in individual controllers.

---

## 13. Routing

Routes are registered in:

```text
routes/web.php
```

Supported methods currently include:

```text
GET
POST
```

Example:

```php
$router->get(
    '/employees',
    [$employees, 'index']
);
```

Example with numeric identifier:

```php
$router->post(
    '/employees/punches/edit/{id}',
    [$punchCorrections, 'update']
);
```

When adding a route:

1. Use a clear resource-oriented path.
2. Select the correct HTTP method.
3. Resolve the controller through the container or established bootstrap pattern.
4. Confirm whether the route should be public or protected.
5. Add the route to the authentication allowlist only when it must be public.
6. Test valid and invalid requests.
7. Confirm unauthenticated behavior.
8. Run `php -l routes/web.php`.

Administrative routes should remain protected by default.

---

## 14. Public and Protected Routes

Public routes include the minimum required for:

- Landing
- Login
- Logout
- Initial setup
- Employee kiosk operation

Public paths include:

```text
/
/login
/logout
/setup
/kiosk
/kiosk/authenticate
/kiosk/verify-pin
/kiosk/punch
```

All other routes require an active user with an authorized role.

Do not make a route public merely to avoid an authentication problem.

Correct the authentication flow instead.

---

## 15. Authentication Development

Authentication components include:

```text
app/Controllers/AuthController.php
app/Services/AuthService.php
app/Services/AuthGuardService.php
app/Repositories/UserRepository.php
public/index.php
```

Authorized roles:

```text
admin
supervisor
```

Authentication development must preserve:

- Password hashing
- Password verification
- Active-account checks
- Role checks
- Session regeneration
- Intended-page handling
- Last-login updates
- Session destruction on logout
- Database revalidation on protected requests

Never store plain-text supervisor passwords.

Never trust only the role value already stored in the session.

The guard must reload the current user from the database.

---

## 16. Session Development

Application sessions are stored under:

```text
storage/sessions
```

Session cookie:

```text
IQWURKSPUNCHSESSID
```

Session configuration includes:

- Strict mode
- Cookie-only operation
- HTTP-only cookie
- SameSite=Lax
- Secure cookie under HTTPS
- Local session path

Supervisor and kiosk state share the PHP session but use separate keys.

Do not reuse supervisor session keys for kiosk state.

Runtime session files must remain ignored by Git.

---

## 17. Twig Views

Twig views are stored under:

```text
app/Views
```

Shared layout:

```text
app/Views/layouts/base.twig
```

Use Twig for:

- Escaped output
- Forms
- Tables
- Navigation
- Conditional presentation
- User messages
- Kiosk JavaScript

Avoid:

- SQL
- Repository access
- Business-rule calculations
- Secret values
- Complex payroll classification

### Company Date Filter

The application provides:

```text
company_date
```

Use it for stored UTC timestamps that must be displayed in the company timezone.

---

## 18. Twig Validation

Twig templates should be parsed during release validation.

Because templates use the custom `company_date` filter, a standalone validation environment must register a placeholder filter before loading templates.

A suitable validation script should:

1. Load Composer autoloading.
2. Create a Twig filesystem loader for `app/Views`.
3. Create a Twig environment.
4. Register a dummy `company_date` filter.
5. Load every `.twig` file.
6. Report parse failures.
7. Exit nonzero on failure.

Do not assume a standalone Twig error about `company_date` means the template is invalid when the validation environment omitted the application’s custom filter.

---

## 19. Local Frontend Assets

Primary interface assets are stored under:

```text
public/assets/vendor
```

The frontend asset model includes:

```text
Bootstrap 5.3.7
Bootstrap Icons 1.13.1
```

The shared layout should reference only local asset paths.

Verify checksums from the project root:

```bash
cd /var/www/IQwurksPunch

sha256sum --check public/assets/vendor/SHA256SUMS
```

When updating a vendored dependency:

1. Record the exact version.
2. Include the upstream license.
3. Replace only required files.
4. Update all affected asset references.
5. Regenerate the checksum manifest.
6. Test CSS, JavaScript, icons, and fonts.
7. Confirm no obsolete CDN reference remains.
8. Document the dependency update.

Do not commit downloaded assets without their applicable license.

---

## 20. Database Connection

The primary connection is managed by:

```text
app/Core/Database.php
```

The SQLite connection configures:

```sql
PRAGMA foreign_keys = ON;
PRAGMA busy_timeout = 10000;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA wal_autocheckpoint = 1000;
```

Database changes must preserve these settings unless a deliberate architectural change is approved and documented.

Use the shared application PDO connection.

Do not create random SQLite connections with different behavior inside controllers.

---

## 21. SQLite WAL Development Rules

WAL mode can create:

```text
iqwurks.sqlite-wal
iqwurks.sqlite-shm
```

Development rules:

- Keep the database directory writable.
- Do not delete active sidecar files.
- Do not use direct file copies as the backup implementation.
- Use transactions for multi-record changes.
- Use the application backup service.
- Use the restore service for database replacement.
- Run database health checks after connection changes.
- Test concurrent web, scheduler, and backup activity.

A database feature is incomplete when it works only in rollback-journal mode.

---

## 22. Database Migrations

Migration files are stored under:

```text
database/migrations
```

Naming convention:

```text
NNN_descriptive_name.php
```

Migration examples include:

```text
010_add_punch_correction_support.php
011_add_kiosk_inactivity_timeout.php
```

A migration should:

- Declare strict types
- Define a class with a unique name
- Implement `up(PDO $pdo): void`
- Use deterministic SQL
- Preserve existing data
- Be safe for existing installations
- Avoid duplicate schema operations
- Fail loudly on unexpected conditions

Run migrations with:

```bash
php migrate.php
```

Validate a migration file:

```bash
php -l database/migrations/011_add_kiosk_inactivity_timeout.php
```

---

## 23. Migration Development Workflow

Before writing a migration:

1. Inspect the active schema.
2. Inspect existing migrations.
3. Confirm the next migration number.
4. Determine upgrade behavior.
5. Determine fresh-install behavior.
6. Identify default values.
7. Identify compatibility needs.
8. Plan rollback or recovery.

After writing it:

1. Run `php -l`.
2. Create a verified backup.
3. Apply it to an upgrade database.
4. Confirm migration history.
5. Inspect the resulting schema.
6. Run database health checks.
7. Run automated tests.
8. Test affected application pages.
9. Test backup creation.
10. Document the change.

Never alter an already released migration merely to make a new upgrade work.

Add a new migration.

---

## 24. Schema Inspection

Display tables:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
.tables
"
```

Display a table schema:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
.schema punches
"
```

Display migration history:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
SELECT migration
FROM migrations
ORDER BY migration;
"
```

Run application database diagnostics:

```bash
./iqwurks database:check
```

Application diagnostics are preferred over isolated raw checks because they report the configured database path and application expectations.

---

## 25. Transactions

Use transactions when multiple database operations represent one logical change.

Typical pattern:

```php
$this->pdo->beginTransaction();

try {
    // Perform related repository operations.

    $this->pdo->commit();
} catch (Throwable $exception) {
    if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
    }

    throw $exception;
}
```

Transaction rules:

- The service owns the transaction.
- Repositories participate without committing independently.
- Validate final state before committing.
- Roll back on any exception.
- Do not leave partial audit or history records.
- Keep transaction scope focused.
- Avoid external SMTP operations inside a database transaction when possible.

---

## 26. Punch Correction Development

Punch correction components include:

```text
app/Controllers/PunchCorrectionController.php
app/Services/PunchCorrectionService.php
app/Repositories/PunchRepository.php
app/Repositories/PunchCorrectionHistoryRepository.php
app/Views/employees/punches/
```

Supported actions:

```text
add
edit
delete
```

Supported punch types:

```text
clock_in
clock_out
break_out
break_in
meal_out
meal_in
```

Every correction must require:

- Authorized supervisor
- Valid employee
- Valid punch type
- Valid date and time
- Nonempty correction reason
- Typed confirmation for deletion

---

## 27. Punch Sequence Validation

The correction service must validate the complete resulting employee punch sequence.

Examples of invalid sequences:

- `clock_out` before `clock_in`
- `break_in` without `break_out`
- `meal_in` without `meal_out`
- Starting two breaks without ending the first
- Starting two meals without ending the first
- Clocking in while already working
- Clocking out while already inactive

Validation must occur before commit.

When validation fails:

- Roll back the transaction.
- Preserve original punches.
- Do not create partial history.
- Return a useful message.
- Log unexpected internal failures.

---

## 28. Immutable Correction History

Correction history must preserve:

- Action
- Employee
- Punch identifier
- Original values
- Corrected values
- Reason
- Supervisor
- Timestamp

History should be append-only through normal application behavior.

Do not provide ordinary update or delete actions for correction-history rows.

Changes to history architecture require explicit data-integrity review.

---

## 29. Kiosk Development

Kiosk components include:

```text
app/Controllers/KioskController.php
app/Views/kiosk/index.twig
app/Views/kiosk/pin.twig
app/Views/kiosk/actions.twig
app/Views/kiosk/_inactivity_timeout.twig
```

Kiosk development priorities:

- Large clear controls
- Minimal steps
- Numeric input
- Clear current status
- Safe reset behavior
- No supervisor access
- No stale employee state
- No accidental punch submission
- Company-timezone display
- Keyboard, mouse, and touch support

The kiosk beginning page must clear unfinished kiosk state.

---

## 30. Kiosk Session Keys

Kiosk session state includes:

```text
kiosk_employee_id
kiosk_employee_name
kiosk_authenticated
kiosk_last_activity
```

When changing kiosk flow:

- Clear state on a fresh `/kiosk` request.
- Clear state after a successful punch.
- Clear state after a failed punch attempt.
- Clear state after invalid employee lookup.
- Clear state after invalid PIN.
- Clear state after timeout.
- Reject stale submissions server-side.

Browser-only protection is insufficient.

---

## 31. Kiosk Inactivity Development

Timeout value:

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

The browser timer:

- Tracks user activity.
- Restarts on interaction.
- Displays the final ten-second warning.
- Sends throttled activity heartbeats.
- Uses `window.location.replace()` on expiration.

The server:

- Stores last activity.
- Validates transaction age.
- Returns HTTP 204 for valid heartbeat state.
- Returns HTTP 409 for expired state.
- Rejects stale PIN submissions.
- Rejects stale punch submissions.

Any timeout change must be tested on both:

- PIN screen
- Punch-action screen

No timeout path may create a punch automatically.

---

## 32. Company Settings Development

Company Settings components include:

```text
CompanySettingsRepository
CompanySettingsService
Company Settings controller
app/Views/settings/index.twig
```

When adding a company setting:

1. Add a migration when schema storage changes.
2. Add repository read and write support.
3. Add service normalization and validation.
4. Add defaults.
5. Add view controls.
6. Add controller persistence.
7. Test existing installations.
8. Test invalid values.
9. Document the setting.
10. Confirm reports and kiosk behavior when applicable.

Do not trust raw form input in the repository.

Normalize and validate it in the service layer.

---

## 33. Payroll Development

Payroll calculations must have one authoritative implementation.

Shared results are used by:

- Web reports
- Dashboard
- CSV exports
- PDF exports
- Email reports

Do not recalculate overtime independently inside an exporter or template.

Payroll categories include:

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

Daily overtime and double-time must not be counted again as weekly overtime.

---

## 34. Payroll Time Handling

Stored punch timestamps use UTC.

Business interpretation uses the company timezone.

Payroll code must correctly handle:

- Local day boundaries
- Workweek boundaries
- Daylight-saving transitions
- Sunday or Monday workweek starts
- Date-range reports
- Scheduled-report dates

Avoid using the server’s default timezone implicitly.

Resolve the company timezone explicitly through the established settings service or repository.

---

## 35. Labor Rules Development

Labor Rules is authoritative for:

- Daily overtime threshold
- Weekly overtime threshold
- Double-time threshold
- Workweek start day

Company Settings should not become a second authoritative source for these rules.

Legacy values may remain as compatibility fallbacks.

Validation must preserve:

```text
Double-time threshold > Daily overtime threshold
```

Workweek start must remain within the supported values.

Changes require payroll regression tests.

---

## 36. CSV and PDF Export Development

Exporters should:

- Receive completed payroll result structures
- Format data
- Preserve category terminology
- Include company information when appropriate
- Include report period
- Include timezone
- Include warnings and review status where applicable
- Avoid recalculating payroll logic

When changing payroll result structures:

1. Update web reports.
2. Update dashboard usage.
3. Update CSV exporters.
4. Update PDF exporters.
5. Update email reports.
6. Update tests.
7. Confirm reconciliation across all outputs.

---

## 37. Email Development

SMTP transport settings are stored in:

```text
config/mail.php
```

This file is ignored by Git.

Notification recipients are stored in the database.

Do not add recipients back to SMTP configuration.

Email development should preserve:

- Multiple recipients
- Delivery logging
- Email history
- Clear report type
- Company identity
- Company timezone
- Payroll totals
- Review status
- Failure reporting

Avoid exposing SMTP passwords in logs or exception output.

---

## 38. Scheduler Development

Scheduler command:

```text
schedule:run
```

Production cron executes it every minute.

Scheduler development must preserve:

- Company-timezone evaluation
- Enabled schedule status
- Allowed delivery days
- Configured delivery time
- Active recipients
- Duplicate-send prevention
- Locking
- Structured logs
- Safe “not due” result

A “not due” outcome is normal and should return success.

Run:

```bash
./iqwurks schedule:run
./iqwurks scheduler:check
```

after scheduler changes.

---

## 39. Console Commands

Console commands are stored under:

```text
app/Console/Commands
```

Console entry:

```text
iqwurks
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

Each command should provide:

- A stable name
- A concise description
- Explicit argument validation
- Clear output
- Useful failure messages
- Correct process exit code
- Logging when operationally appropriate

---

## 40. Adding a Console Command

To add a command:

1. Create a command class under `app/Console/Commands`.
2. Implement the established command interface or command shape.
3. Define the command name.
4. Define the description.
5. Implement argument handling.
6. Resolve dependencies lazily.
7. Register it in `iqwurks`.
8. Add it to console help.
9. Add tests where practical.
10. Add documentation.
11. Run PHP syntax validation.
12. Run the command in success and failure cases.
13. Confirm exit codes.

Use focused services for substantive business logic.

The command class should primarily coordinate console input and output.

---

## 41. Command Arguments

The console does not currently provide universal per-command `--help`.

Each command interprets arguments individually.

Examples:

```bash
./iqwurks backup:verify --all
./iqwurks backup:prune --keep=30
./iqwurks backup:prune --keep=30 --delete
./iqwurks backup:restore BACKUP_FILENAME
./iqwurks maintenance:on Planned maintenance
```

Do not assume `--help` is harmless.

For example:

```bash
./iqwurks maintenance:on --help
```

activates maintenance mode with `--help` as the reason.

When adding argument support:

- Reject unknown arguments.
- Explain supported syntax.
- Avoid ambiguous positional values.
- Require explicit destructive-operation flags.
- Require typed confirmation for high-risk actions.
- Test invalid combinations.

---

## 42. Destructive Console Operations

High-risk commands should use multiple safeguards.

A restore requires:

```text
BACKUP_FILENAME
--apply
--confirm=BACKUP_FILENAME
```

Backup pruning requires:

```text
--delete
```

without which it remains a preview.

Future destructive commands should follow the same pattern:

1. Safe preview by default.
2. Explicit application flag.
3. Exact typed confirmation where appropriate.
4. Validation before modification.
5. Safety backup when applicable.
6. Clear result output.
7. Nonzero exit on failure.
8. Audit or operational logging.

---

## 43. Backup Development

Backup components include:

```text
DatabaseBackupService
DatabaseBackupVerificationService
DatabaseBackupCatalogService
DatabaseBackupRetentionService
```

Backup creation uses:

```text
VACUUM INTO
```

Development requirements:

- Remain safe under WAL mode.
- Include committed WAL data.
- Create timestamped files.
- Prevent overlapping runs.
- Verify every new backup.
- Check foreign-key integrity.
- Report filename and size.
- Preserve safe retention behavior.
- Never accept an unverified backup as successful.

Run after backup changes:

```bash
./iqwurks backup:create
./iqwurks backup:list
./iqwurks backup:verify
./iqwurks backup:run
```

---

## 44. Restore Development

Restore components include:

```text
DatabaseRestoreService
MaintenanceModeService
DatabaseBackupVerificationService
DatabaseBackupService
```

Restore development must preserve:

- Preview by default
- Exact filename validation
- Backup integrity validation
- Foreign-key validation
- Explicit `--apply`
- Exact `--confirm`
- Pre-restore safety backup
- WAL checkpoint
- Sidecar cleanup
- Post-restore validation
- Migration verification
- Rollback behavior

Restore changes require controlled testing.

Do not test destructive restore behavior against the only production database without a verified recovery plan.

---

## 45. Database Diagnostics Development

Database health is exposed through:

```text
database:check
```

The health service should return structured data usable by:

- The console command
- System Doctor
- Future dashboard health displays

Checks should remain read-only unless a separate repair operation is explicitly requested.

A diagnostic command must not silently change database state.

---

## 46. System Doctor Development

The System Doctor aggregates focused diagnostic services.

Current categories include:

- Application
- PHP
- Configuration
- Runtime directories
- Disk
- Maintenance
- Database
- Migrations
- Backups
- Scheduler
- Mail

Results use:

```text
PASS
WARN
FAIL
```

Guidelines:

- `PASS` means the requirement is satisfied.
- `WARN` means review is needed but operation may continue.
- `FAIL` means safe operation is compromised.
- Every warning or failure should include remediation guidance.
- Avoid marking expected normal conditions as failures.
- Do not expose secrets.

---

## 47. Logging Development

Logging components are under:

```text
app/Logging
```

Use focused channels.

Examples:

```text
scheduler
mail
backup
database
application
```

Log messages should include useful context such as:

- Operation
- Result
- Duration
- Record identifiers
- Timezone
- Recipient count
- Backup filename
- Process ID
- Error class

Do not log:

- Passwords
- Employee PINs
- PIN hashes
- SMTP passwords
- Session IDs
- Full sensitive configuration

Unexpected failures should not be silent.

---

## 48. Error Handling

Expected user errors should produce clear validation messages.

Examples:

- Invalid employee number
- Invalid PIN
- Expired kiosk transaction
- Invalid punch sequence
- Invalid labor-rule threshold
- Unknown console argument

Unexpected failures should:

- Be logged
- Return a safe user-facing message
- Preserve original data
- Return an appropriate process exit code in console commands
- Avoid exposing stack traces in production web output

Do not use broad `catch` blocks merely to hide errors.

---

## 49. Automated Tests

Run:

```bash
cd /var/www/IQwurksPunch

php vendor/bin/phpunit
```

Current Version 1.0 development result:

```text
OK (448 tests, 3323 assertions)
```

The suite includes coverage for:

- Payroll calculations
- Daily overtime
- Weekly overtime
- Double-time
- Workweek configuration
- Payroll reconciliation
- Export behavior
- Punch correction
- Required correction reasons
- Punch sequence validation
- Immutable correction history
- Transaction rollback
- Payroll-period lifecycle and removal safeguards
- Supervisor account-management authorization
- Authentication and session expiration
- Installation, upgrade, backup, restore, and package verification

All tests must pass before release.

---

## 50. Adding Tests

Tests are stored under:

```text
tests
```

Use focused unit tests for service and payroll logic.

A test should:

- Have a descriptive name
- Arrange a clear initial state
- Execute one behavior
- Assert the important outcome
- Assert database state when applicable
- Assert rollback behavior for failures
- Avoid depending on test execution order
- Clean up temporary files or databases

For database services, consider assertions for:

- Created record
- Updated record
- Deleted record
- History record
- Audit record
- Transaction rollback
- Foreign-key integrity

---

## 51. Manual Feature Validation

Automated tests do not replace manual application validation.

Minimum web validation includes:

- Supervisor login
- Logout
- Dashboard
- Employee list
- Employee add/edit
- Employee activation/deactivation
- Kiosk employee lookup
- PIN verification
- Punch submission
- Kiosk inactivity reset
- Punch correction
- Company Settings
- Labor Rules
- Reports
- CSV exports
- PDF exports
- Notification Center
- Manual email
- Report schedule

Document unexpected behavior before proceeding.

---

## 52. HTTP Validation

Useful local checks:

```bash
curl \
    --silent \
    --output /dev/null \
    --write-out 'Kiosk HTTP %{http_code}\n' \
    http://127.0.0.1/kiosk

curl \
    --silent \
    --output /dev/null \
    --write-out 'Login HTTP %{http_code}\n' \
    http://127.0.0.1/login

curl \
    --silent \
    --output /dev/null \
    --write-out 'Dashboard HTTP %{http_code}\n' \
    http://127.0.0.1/dashboard
```

Expected while logged out:

```text
Kiosk HTTP 200
Login HTTP 200
Dashboard HTTP 302
```

Administrative routes should redirect unauthenticated requests.

---

## 53. Operational Validation

Run:

```bash
./iqwurks database:check
./iqwurks scheduler:check
./iqwurks mail:check
./iqwurks doctor
```

Release expectations include:

```text
Database: HEALTHY
Scheduler: PASS
System Doctor: no failures
```

A temporary mail-activity warning can be acceptable immediately after log rotation when SMTP and delivery history have otherwise been confirmed.

---

## 54. Backup Validation

Run:

```bash
./iqwurks backup:run
./iqwurks backup:list
./iqwurks backup:verify
```

Also verify all retained backups during release preparation when practical:

```bash
./iqwurks backup:verify --all
```

A release affecting persistence, migrations, WAL, backup, or restore must include a fresh verified backup test.

---

## 55. Maintenance Validation

Test:

```bash
./iqwurks maintenance:on Release validation
./iqwurks maintenance:status
./iqwurks maintenance:off
./iqwurks maintenance:status
```

Confirm:

- Maintenance activates.
- Reason is shown.
- Web requests receive maintenance response.
- Console commands remain available.
- Maintenance state is removed when disabled.
- Normal HTTP access returns afterward.

Never leave maintenance mode active accidentally.

---

## 56. Physical Kiosk Validation

Production kiosk changes should be validated on the physical machine.

Confirm:

- LightDM logs in automatically.
- Openbox starts.
- Chromium starts full-screen.
- Local kiosk loads.
- Cursor hides.
- Screen does not blank.
- Chromium restarts after termination.
- Kiosk waits when the application is unavailable.
- Kiosk recovers after LightDM restart.
- Kiosk recovers after reboot.
- Timeout resets unfinished transactions.
- Activity restarts the timeout.
- Timeout creates no punch.

System-level kiosk files are outside Git and must be documented separately.

---

## 57. Git Workflow

Before beginning work:

```bash
git status
git branch --show-current
git log --oneline --decorate -n 10
```

Create a focused branch:

```bash
git switch -c feature/DESCRIPTIVE-NAME
```

For a release:

```bash
git switch -c release/1.0.0
```

During development:

- Commit meaningful units of work.
- Avoid generated runtime files.
- Avoid secrets.
- Review diffs before staging.
- Keep migration and documentation changes together when appropriate.
- Do not rewrite released migration history.

---

## 58. Runtime Files and Git

Do not stage:

- Active SQLite databases
- WAL or SHM files
- Backups
- Logs
- Sessions
- Cache state
- Generated exports
- SMTP secrets
- Temporary editor files
- Local safety copies

Review untracked files:

```bash
git ls-files \
    --others \
    --exclude-standard \
    | sort
```

Review ignored status when necessary:

```bash
git check-ignore -v PATH
```

Never use `git add -f` on production data or secrets.

---

## 59. Review File Mode Changes

Git may report a modified file with zero content lines when only its executable mode changed.

Inspect:

```bash
git diff --summary
git diff -- FILE
stat -c '%A %a %n' FILE
git ls-tree HEAD FILE
```

Decide whether the mode change is intentional.

Console entry points may require executable mode.

Ordinary PHP source files usually do not.

Do not include accidental mode changes in a release.

---

## 60. Diff Validation

Run:

```bash
git diff --check
```

This detects issues such as:

- Trailing whitespace
- Whitespace errors
- Conflict markers in some contexts

Review the complete change summary:

```bash
git diff --stat
```

Review individual files:

```bash
git diff -- PATH
```

Review staged changes later with:

```bash
git diff --cached --stat
git diff --cached -- PATH
```

---

## 61. Documentation Requirements

Update documentation whenever behavior changes.

Potential files include:

```text
README.md
CHANGELOG.md
ROADMAP.md
docs/AdministratorGuide.md
docs/Architecture.md
docs/Database.md
docs/DeveloperGuide.md
docs/Installation.md
releases/VERSION.md
VERSION
```

Document:

- User-visible behavior
- Configuration
- Migrations
- Operational commands
- Recovery procedures
- Known limitations
- Test results
- Upgrade steps

Do not leave a completed feature listed as planned.

---

## 62. Version Management

The installed version is stored in:

```text
VERSION
```

Display it with:

```bash
./iqwurks version
```

The Version 1.0 stable release uses:

```text
1.0.0
```

Before release:

1. Update `VERSION`.
2. Confirm console output.
3. Update README.
4. Update CHANGELOG.
5. Update ROADMAP.
6. Add release notes.
7. Update applicable guides.
8. Run full validation.
9. Commit the release.
10. Create an annotated Git tag.

---

## 63. Semantic Versioning

IQwurksPunch generally follows Semantic Versioning.

### Patch

Example:

```text
0.6.1
```

Use for backward-compatible fixes.

### Minor

Example:

```text
0.8.0
```

Use for backward-compatible functionality and milestones.

### Major

Example:

```text
1.0.0
```

Use when declaring the stable public release or introducing incompatible public behavior.

Database migrations do not automatically require a major version when normal upgrade compatibility is preserved.

---

## 64. Release Branch Workflow

Example:

```bash
git switch -c release/1.0.0
```

Confirm:

```bash
git branch --show-current
cat VERSION
./iqwurks version
```

Expected:

```text
release/1.0.0
1.0.0
IQwurksPunch 1.0.0
```

Complete documentation and validation on the release branch before committing or tagging.

---

## 65. Release Validation Checklist

Before staging a release:

### Source

- [ ] Every modified PHP file passes `php -l`.
- [ ] Twig templates parse successfully.
- [ ] No obsolete temporary files remain.
- [ ] No accidental mode changes remain.
- [ ] `git diff --check` passes.

### Automated Tests

- [ ] PHPUnit passes.
- [ ] Expected test and assertion counts are confirmed.

### Database

- [ ] All migrations are applied.
- [ ] Database status is `HEALTHY`.
- [ ] Journal mode is `wal`.
- [ ] Integrity result is `ok`.
- [ ] Foreign-key violations are zero.
- [ ] Migration count is correct.

### Backups

- [ ] A new backup is created.
- [ ] The new backup is verified.
- [ ] Backup listing is correct.
- [ ] Retention preview is reviewed.
- [ ] Restore preview works.

### Application

- [ ] Kiosk returns HTTP 200.
- [ ] Login returns HTTP 200.
- [ ] Protected page redirects while logged out.
- [ ] Supervisor login works.
- [ ] Employee administration works.
- [ ] Punch correction works.
- [ ] Settings work.
- [ ] Labor Rules works.
- [ ] Reports work.
- [ ] CSV exports work.
- [ ] PDF exports work.
- [ ] Email delivery is reviewed.

### Kiosk

- [ ] Timeout works on PIN screen.
- [ ] Timeout works on action screen.
- [ ] Activity resets timeout.
- [ ] No timeout punch is created.
- [ ] Browser recovery works.
- [ ] Reboot recovery works.

### Operations

- [ ] Scheduler diagnostics pass.
- [ ] Mail diagnostics are reviewed.
- [ ] System Doctor has no failures.
- [ ] Asset checksums pass.
- [ ] Nginx configuration passes.
- [ ] PHP-FPM configuration passes.
- [ ] Firewall rules are reviewed.
- [ ] Cron entries are present.
- [ ] Log rotation is configured.

### Documentation

- [ ] `VERSION` is correct.
- [ ] README is current.
- [ ] CHANGELOG is current.
- [ ] ROADMAP is current.
- [ ] Release notes exist.
- [ ] Administrator Guide is current.
- [ ] Installation Guide is current.
- [ ] Database Guide is current.
- [ ] Architecture Guide is current.
- [ ] Developer Guide is current.

---

## 66. Staging a Release

Stage only reviewed files.

Avoid blindly staging runtime directories.

Example pattern:

```bash
git add \
    .gitignore \
    VERSION \
    README.md \
    CHANGELOG.md \
    ROADMAP.md \
    releases/1.0.0.md \
    docs \
    app \
    config/backup.php \
    config/maintenance.php \
    database/migrations \
    iqwurks \
    migrate.php \
    public/index.php \
    public/assets/vendor \
    routes/web.php \
    tests
```

Before committing:

```bash
git status --short
git diff --cached --stat
git diff --cached --check
```

Inspect suspicious or unexpected files individually.

---

## 67. Release Commit and Tag

After complete validation:

```bash
git commit \
    -m "Release IQwurksPunch 1.0.0"
```

Create an annotated tag:

```bash
git tag \
    -a v1.0.0 \
    -m "IQwurksPunch v1.0.0"
```

Verify:

```bash
git log \
    --oneline \
    --decorate \
    -n 5

git tag \
    --list \
    --sort=-version:refname
```

Do not tag a release while the required validation is incomplete.

---

## 68. Definition of Done

The project Definition of Done is stored in:

```text
docs/DefinitionOfDone.md
```

Every applicable change should satisfy:

- Syntax validation
- Functional validation
- Regression testing
- Database integrity
- Logging
- Documentation
- Version management
- Clean version-control state
- Product-owner acceptance
- Professional product quality

A feature that creates avoidable operational risk is not done.

---

## 69. Security Review Questions

Before completing a change, ask:

- Does this create a new public route?
- Does this weaken supervisor authentication?
- Does this trust session data without database validation?
- Does this expose a secret?
- Does this permit unsafe database modification?
- Does this bypass correction history?
- Does this create a destructive command without confirmation?
- Does this store sensitive data in logs?
- Does this depend on browser-only validation?
- Does this expose files outside `public`?
- Does this introduce an external runtime dependency?
- Does this affect backup or restore compatibility?

Security concerns must be resolved before release.

---

## 70. Data-Integrity Review Questions

Ask:

- Can the operation leave partial data?
- Should it use a transaction?
- Are foreign keys preserved?
- Does it preserve historical payroll records?
- Does it preserve correction history?
- Does it work in WAL mode?
- Does backup contain the committed result?
- Can restore recover the result?
- Is migration behavior safe for existing data?
- Are UTC and company timezone handled correctly?
- Do all report outputs reconcile?

Data-integrity concerns are release blockers.

---

## 71. Troubleshooting Development Failures

### PHP Syntax Failure

Run:

```bash
php -l FILE.php
```

Correct the first reported syntax error before testing further.

### HTTP 500

Review:

```bash
tail -n 100 storage/logs/php-fpm-error.log
tail -n 100 /var/log/nginx/iqwurks-punch-error.log
```

### Database Failure

Run:

```bash
./iqwurks database:check
```

### Scheduler Failure

Run:

```bash
./iqwurks scheduler:check
```

Review:

```bash
tail -n 100 storage/logs/scheduler.log
tail -n 100 storage/logs/cron-scheduler.log
```

### Backup Failure

Review:

```bash
tail -n 100 storage/logs/cron-backup.log
./iqwurks backup:list
```

### Twig Failure

Confirm:

- Template syntax
- View path
- Required variables
- Custom filter registration
- Included partial path

### Authentication Loop

Review:

- User active status
- User role
- Session directory permissions
- Session cookie
- Guard public-path list
- Intended URL
- Login redirect

---

## 72. Version 1.0 Release Baseline

The verified Version 1.0 release baseline is:

```text
Application version: 1.0.0
Automated tests: 448
Assertions: 3323
Database tables: 18
Applied migrations: 17
SQLite journal mode: wal
Database integrity: ok
Foreign-key violations: 0
Scheduler checks: 9 passed
```

The System Doctor baseline has:

```text
0 failures
```

A temporary mail-activity warning may remain immediately after log rotation.

Future changes should not reduce this baseline without a documented and approved reason.

---

## 73. Final Development Standard

Every change should leave IQwurksPunch:

- Easier to operate
- Safer to recover
- More understandable
- Better documented
- Fully testable
- More reliable than before

The project’s technical standard is not merely that code runs.

The standard is that the complete production system can be understood, validated, operated, and recovered safely.
