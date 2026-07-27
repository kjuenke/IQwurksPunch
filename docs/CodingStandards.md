# IQwurksPunch Coding Standards

Version: 1.0

## Purpose

This document defines the coding standards for IQwurksPunch.

All new code, bug fixes, refactoring, migrations, console commands, templates, and documentation must follow these standards.

The goals are:

* Consistency
* Reliability
* Readability
* Maintainability
* Security
* Ease of contribution
* Long-term product stability

---

# 1. PHP Version

IQwurksPunch targets:

```text
PHP 8.5 or newer
```

All PHP files must begin with:

```php
<?php
declare(strict_types=1);
```

The `declare(strict_types=1);` statement must appear immediately after the opening PHP tag.

No executable code may appear before it.

---

# 2. File Organization

The primary application structure is:

```text
app/
    Console/
        Commands/

    Controllers/

    Core/

    Repositories/

    Services/

    Views/

bootstrap/

config/

database/
    migrations/
    seeds/
    sqlite/

docs/

public/

routes/

storage/
    backups/
    logs/

tests/
```

Each class must be stored in the directory matching its namespace.

Example:

```text
App\Services\EmployeeService
```

must be stored in:

```text
app/Services/EmployeeService.php
```

---

# 3. One Class Per File

Each PHP class, interface, or trait must be stored in its own file.

The filename must match the class name exactly.

Examples:

```text
EmployeeService.php
PunchRepository.php
CommandInterface.php
```

Anonymous classes are permitted only for database migration files.

---

# 4. Namespaces

Namespaces must match the directory structure.

Examples:

```php
namespace App\Controllers;
```

```php
namespace App\Services;
```

```php
namespace App\Repositories;
```

```php
namespace App\Console\Commands;
```

Namespace declarations must appear after `declare(strict_types=1);`.

---

# 5. Imports

Class imports must use `use` statements.

Example:

```php
use App\Core\Database;
use App\Repositories\EmployeeRepository;
use App\Services\EmployeeService;
```

Imports should be grouped together near the top of the file.

Unused imports must be removed.

Fully qualified class names should not be scattered through application code when a normal import can be used.

---

# 6. Class Naming

Classes must use PascalCase.

Examples:

```text
EmployeeController
PunchReportService
ReportScheduleRepository
```

Interfaces must use a descriptive suffix.

Example:

```text
CommandInterface
```

Avoid vague class names such as:

```text
Manager
Helper
Utility
Data
Common
```

unless the responsibility is clearly defined.

---

# 7. Method Naming

Methods must use camelCase.

Examples:

```php
findByEmployeeNumber()
sendDailyPayrollReport()
updateSchedule()
markSent()
```

Method names should describe an action or result.

Prefer:

```php
findByEmployeeNumber()
```

instead of:

```php
employee()
```

Prefer:

```php
sendDailyPayrollReport()
```

instead of:

```php
send()
```

when the broader meaning is important.

---

# 8. Property Naming

Properties must use camelCase.

Examples:

```php
private EmployeeService $employees;

private ReportScheduleService $schedule;

private EmailRepository $emails;
```

Property names should describe the role of the dependency.

Avoid single-letter names except for short local loop variables where meaning remains obvious.

---

# 9. Constructor Standards

Dependencies should be assigned in the constructor.

Example:

```php
public function __construct(
    EmployeeRepository $employees
)
{
    $this->employees = $employees;
}
```

Controllers may obtain services through the application container where available.

Manual dependency construction should be gradually replaced by centralized dependency management.

Constructors should not perform business operations such as:

* Sending email
* Running reports
* Creating punches
* Writing logs
* Modifying database records

Constructors should only initialize dependencies and configuration.

---

# 10. Method Order

Classes should generally use this order:

1. Properties
2. Constructor
3. Public read methods
4. Public write methods
5. Private helper methods

For controllers:

1. Constructor
2. Index/list methods
3. Create/display methods
4. Store/update methods
5. Specialized actions

For repositories:

1. Constructor
2. Read methods
3. Create methods
4. Update methods
5. Deactivate/archive methods
6. Private query helpers

---

# 11. Controllers

Controllers are responsible for:

* Receiving HTTP input
* Calling services
* Selecting views
* Redirecting
* Setting flash messages
* Returning HTTP responses

Controllers must not contain:

* Raw SQL
* Payroll calculations
* Password hashing logic
* SMTP logic
* Complex validation
* Direct database schema knowledge

Example flow:

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
Database
```

Controllers should remain small and readable.

---

# 12. Services

Services contain business rules.

Examples:

* Employee validation
* PIN verification
* Punch rules
* Payroll calculations
* Report scheduling
* Email report generation

Services may call repositories and other services.

Services must not render Twig templates or issue redirects.

Services should return structured results.

Example:

```php
return [
    'success' => false,
    'errors' => [
        'pin' => 'PIN must be exactly 4 digits.'
    ]
];
```

---

# 13. Repositories

Repositories are the only application layer that should contain SQL queries.

Repositories are responsible for:

* Reading records
* Creating records
* Updating records
* Returning database results

Repositories must not contain:

* HTML
* Redirects
* Flash messages
* Business decisions
* Email logic
* Payroll rules

Prepared statements must be used for user-controlled values.

Example:

```php
$stmt = $this->db->prepare(
    "
    SELECT *
    FROM employees
    WHERE employee_number = ?
    LIMIT 1
    "
);
```

String concatenation must not be used to insert untrusted input into SQL.

---

# 14. Database Connections

Application code must use:

```php
Database::connection()
```

or the dependency container.

Code must not create arbitrary new PDO connections throughout the application.

PDO must use exception mode.

Database access must use the configured database path.

---

# 15. Database Time Standard

All database timestamps must be stored in UTC.

SQLite `CURRENT_TIMESTAMP` is acceptable because it stores UTC.

Application views must convert UTC timestamps into the configured company timezone.

Timezone conversion must occur in a centralized formatter or service.

Raw UTC timestamps should not normally be displayed directly to users.

---

# 16. Migrations

All schema changes must use migration files.

Manual database changes are prohibited except for emergency recovery or development investigation.

Migration filenames must use ordered numeric prefixes.

Example:

```text
001_create_core_tables.php
002_add_employee_fields.php
006_create_report_schedule_settings.php
```

Released migrations must never be edited.

Corrections require a new migration.

Every migration must provide:

```php
public function up(): void
```

and:

```php
public function down(): void
```

where practical.

Migrations must be safe to run once and tracked in the `migrations` table.

---

# 17. Data Deletion

Business records should not normally be permanently deleted.

Use status fields such as:

```text
active
inactive
archived
```

Examples include:

* Employees
* Users
* Departments
* Schedules

Permanent deletion should be reserved for maintenance operations where data retention is not required.

---

# 18. Validation

User input must be validated before repository operations.

Validation belongs in services or dedicated validator classes.

Validation errors should be clear and specific.

Prefer:

```text
PIN must be exactly 4 digits.
```

Avoid:

```text
Invalid input.
```

Server-side validation is mandatory even when browser-side validation exists.

---

# 19. Passwords and PINs

Passwords and PINs must never be stored as plain text.

Use:

```php
password_hash()
```

for storage.

Use:

```php
password_verify()
```

for verification.

PINs and passwords must never be written to:

* Logs
* Audit records
* Error messages
* Email reports
* Debug output

---

# 20. Sessions

Sessions must be started centrally.

Controllers should not repeatedly call `session_start()` unless a framework component explicitly guards against inactive sessions.

Session values should use clear names.

Examples:

```php
$_SESSION['kiosk_employee_id']
$_SESSION['kiosk_authenticated']
```

Sensitive session state must be cleared after the kiosk workflow completes.

---

# 21. Flash Messages

Flash messages must use:

```php
Flash::success()
Flash::error()
Flash::warning()
Flash::info()
```

Controllers must not directly assign strings to:

```php
$_SESSION['flash']
```

Flash messages are arrays consumed by the view layer.

Messages should clearly describe the completed action or failure.

---

# 22. Views and Twig

Twig templates are responsible for presentation only.

Templates must not contain business logic.

Allowed logic includes:

* Conditional display
* Loops
* Formatting
* Showing validation errors
* Showing status badges

Templates must extend:

```twig
{% extends "layouts/base.twig" %}
```

Date and time values must use the centralized company-timezone filter where applicable.

Example:

```twig
{{ punch.punch_time|company_date }}
```

Templates should use Bootstrap components consistently.

---

# 23. Forms

Forms must explicitly define:

```html
method
action
```

Buttons must define:

```html
type="submit"
```

where appropriate.

Long-running actions should provide visual feedback.

Examples:

* Disable the button
* Show a spinner
* Change button text

The interface should prevent accidental duplicate submissions.

---

# 24. Routes

Routes must be grouped by module.

Recommended grouping:

* Home
* Setup
* Authentication
* Dashboard
* Employees
* Kiosk
* Reports
* Email Reports
* Settings

Controller imports should be grouped at the top of the routes file.

Each controller should normally be instantiated once.

Duplicate routes, duplicate imports, and duplicate controller instances are prohibited.

Route names must be descriptive and consistent.

Examples:

```text
/employees/create
/employees/edit/{id}
/reports/email
/admin/settings
```

---

# 25. Console Commands

Console commands must implement:

```php
CommandInterface
```

Each command must provide:

```php
name()
description()
execute()
```

Command names should use colon-separated namespaces.

Examples:

```text
schedule:run
backup:create
system:doctor
migrate:status
```

Commands must return standard exit codes:

```text
0 = success
1 = failure
```

Console commands must print useful, concise output.

Errors should be written to `STDERR`.

---

# 26. Scheduler Standards

Scheduled jobs must be safe to run repeatedly.

The scheduler must:

* Prevent overlapping processes
* Avoid duplicate report delivery
* Use company-local scheduling
* Store execution timestamps in UTC
* Log success and failure
* Return meaningful exit codes

Cron should trigger the scheduler frequently.

The application determines whether work is due.

---

# 27. Email Standards

All reports use the configured global recipient list.

Recipient configuration belongs in:

```text
config/mail.php
```

Email credentials must not be committed to a public repository.

Email sending must:

* Support multiple recipients
* Log successful sends
* Log failed sends
* Use the configured company name
* Use the configured company timezone
* Avoid exposing SMTP credentials in errors

---

# 28. Logging Standards

Application logging must eventually use the centralized logging framework.

Planned log files include:

```text
application.log
scheduler.log
mail.log
security.log
audit.log
```

Log entries should include:

* UTC timestamp
* Severity
* Component
* Message
* Relevant identifiers

Sensitive values must never be logged.

---

# 29. Exception Handling

Exceptions must not expose stack traces to end users in production.

Unhandled exceptions should:

1. Be caught by the global exception handler
2. Be written to the application log
3. Return an appropriate HTTP status
4. Display a professional error page

Development mode may provide additional diagnostic detail.

---

# 30. Formatting

Code must prioritize readability.

Use four spaces for indentation.

Tabs should not be used for PHP indentation.

Opening braces should follow the existing project style.

Long method calls may be split across lines.

Example:

```php
$result =
    $this->employees->update(
        $id,
        $_POST
    );
```

Trailing whitespace must be removed.

Files must end with a newline.

PHP-only files should not use a closing `?>` tag.

---

# 31. Comments

Comments should explain why something exists, not restate obvious code.

Good:

```php
// SQLite stores CURRENT_TIMESTAMP in UTC.
```

Poor:

```php
// Set variable to true.
$enabled = true;
```

Large section comments should be used sparingly.

---

# 32. Documentation

Every user-visible feature must be documented.

Relevant documentation may include:

* README
* Administrator Guide
* Installation Guide
* Developer Guide
* Architecture
* Database Reference
* CHANGELOG
* Release Notes

Documentation should be updated in the same sprint as the feature.

---

# 33. Versioning

IQwurksPunch uses semantic versioning.

Format:

```text
MAJOR.MINOR.PATCH
```

Development suffixes may include:

```text
-dev
-alpha
-beta
-rc
```

Examples:

```text
0.3.0-dev
0.3.0-beta
0.3.0
0.3.1
```

The `VERSION` file is the authoritative application version.

---

# 34. Git Standards

Every commit must leave the application in a runnable state.

Commit messages must be descriptive.

Good examples:

```text
Add automatic payroll report scheduler
Create company settings administration page
Fix timezone conversion in email history
```

Poor examples:

```text
changes
fix
stuff
update
```

Secrets, generated logs, temporary files, and local IDE settings must not be committed.

---

# 35. Full-File Replacement Workflow

During guided development, existing files should normally be modified using this workflow:

1. Display the current complete file
2. Review the current implementation
3. Provide the complete replacement file
4. Replace the entire file
5. Run a syntax check
6. Test the affected feature

Partial edits should be used only when explicitly requested or when the change is extremely small and unambiguous.

This reduces:

* Duplicate methods
* Misplaced imports
* Missing braces
* Incorrect route placement
* Conflicting session behavior

---

# 36. Testing

Every changed PHP file must pass:

```bash
php -l path/to/file.php
```

Relevant manual workflows must also be tested.

Minimum regression areas include:

* Login
* Dashboard
* Employee management
* PIN management
* Kiosk authentication
* Clock In
* Clock Out
* Punch reports
* Payroll summary
* Company settings
* Manual email reports
* Scheduled email reports
* Console commands

---

# 37. Definition of Done

No sprint is complete until the applicable requirements in:

```text
docs/DefinitionOfDone.md
```

have been satisfied.

This includes:

* Syntax validation
* Functional testing
* Regression testing
* Documentation
* Version control
* Product Owner acceptance

---

# 38. Security

All code must assume user input is untrusted.

Required practices include:

* Prepared SQL statements
* Output escaping through Twig
* Password hashing
* PIN hashing
* Session validation
* Restricted administrative routes
* No credential logging
* No secrets committed to Git
* CSRF protection for all state-changing POST requests
* Cryptographically secure session-bound CSRF tokens
* Automatic CSRF fields in rendered POST forms
* Centralized server-side CSRF validation
* POST-only state-changing routes

Security issues take priority over feature development.

---

# 39. Product Quality Standard

Before completing a feature, ask:

```text
Would a paying customer expect this behavior?
```

A feature is not complete merely because it technically works.

Professional polish, understandable feedback, error handling, and reliable behavior are part of the implementation.

---

# 40. Final Principle

Every change should leave IQwurksPunch:

* Easier to understand
* Safer to operate
* Easier to support
* More reliable
* More professional
* Better documented

Code should be written for the developer who will maintain it years from now, not only for the developer writing it today.
