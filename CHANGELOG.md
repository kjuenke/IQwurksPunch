# IQwurksPunch Changelog

All notable changes to IQwurksPunch are documented in this file.

The project follows Semantic Versioning for stable releases and generally follows the Keep a Changelog format.

---

## [1.0.0] - 2026-08-10

### Added

#### Payroll Period Management

- Added controlled payroll-period creation with defined date ranges.
- Added overlap validation and company-timezone date handling.
- Added draft payroll-period editing.
- Added active and removed payroll-period lists.
- Added protected draft deletion with explicit confirmation.
- Added finalized-period archival and voiding with required reasons.
- Added employee, punch, and report-artifact status details before destructive
  actions.
- Preserved employee punches when payroll-period records are removed.
- Added payroll-period lifecycle and removal audit history.

#### Supervisor User Management

- Added administrator-controlled supervisor account creation.
- Added account editing, password reset, activation, and deactivation.
- Added role and status management.
- Added username uniqueness validation.
- Added last-administrator and active-session lockout protection.
- Added user-management activity history.
- Added dedicated audit events with before-and-after role details.

#### Authentication and Session Security

- Added an eight-hour supervisor inactivity timeout.
- Added supervisor-session synchronization with current database state.
- Added CSRF-token rotation after successful authentication.
- Added tests for login, token rotation, inactive accounts, role changes, and
  expired sessions.

### Changed

- Updated Dompdf from 3.1.5 to the patched 3.1.6 release.
- Aligned overtime-threshold validation across company settings and payroll
  calculation policy.
- Treat unsupported payroll punch types as incomplete payroll data.
- Require affected database rows for punch-update and punch-delete success.
- Clarified that CSV and PDF exports are streamed on demand rather than retained
  as payroll-period-linked artifacts.
- Updated installation, administration, architecture, development, deployment,
  backup, restore, recovery, and production-operation documentation.

### Fixed

- Restored the payroll-period active and removed list service contract.
- Corrected packaged Dompdf font-metadata readability guidance for the web
  service account.
- Added explicit mutation-failure handling for missing punch records.

### Validation

- Passed 448 automated tests with 3323 assertions.
- Passed PHP syntax validation and configured Twig-template compilation.
- Passed Composer validation and locked-dependency security audit.
- Passed SQLite integrity and foreign-key validation with 17 migrations.
- Passed scheduler, backup verification, restore preview, HTTP access, CSV/PDF
  export, and authenticated administrator workflow checks.
- Passed automatic service recovery and physical kiosk recovery after a full
  system reboot.
- Passed distribution-package preview with no unsafe entries.

---

## [0.9.0] - 2026-08-05

### Added

#### Installation Preflight Diagnostics

- Added installation environment validation.
- Added operating-system compatibility checks.
- Added PHP availability and version checks.
- Added required PHP-extension checks.
- Added required system-command checks.
- Added application-source validation.
- Added Composer dependency validation.
- Added runtime-directory validation.
- Added runtime-directory permission checks.
- Added available-disk-space validation.
- Added the `install:check` console command.
- Added PASS, WARN, and FAIL installation-readiness reporting.

#### Upgrade Readiness and Planning

- Added upgrade-readiness diagnostics.
- Added application-version review.
- Added database availability and integrity review.
- Added migration-state review.
- Added maintenance-mode review.
- Added backup-readiness review.
- Added Composer-state review.
- Added upgrade-journal review.
- Added the `upgrade:check` console command.
- Added read-only upgrade planning.
- Added planned-operation sequencing.
- Added the `upgrade:plan` console command.
- Added controlled upgrade preview.
- Added the `upgrade:preview` console command.

#### Controlled Upgrade Execution

- Added exact-confirmation enforcement before upgrade execution.
- Added guarded upgrade operations.
- Added ordered upgrade execution.
- Added blocking-failure handling.
- Added operation result reporting.
- Added process timeout handling.
- Added standard-output and standard-error capture.
- Added execution-directory support.
- Added controlled environment overrides.
- Added shell-bypassing process execution.
- Added the `upgrade:apply` console command.
- Added exit code `124` reporting for timed-out child processes.

#### Upgrade Journaling and Recovery

- Added persistent upgrade-execution journals.
- Added execution identifiers.
- Added start and completion timestamps.
- Added current and target version metadata.
- Added planned-operation counts.
- Added completed-operation counts.
- Added failed-operation metadata.
- Added final execution status.
- Added interruption assessment.
- Added recovery-required state.
- Added the `upgrade:status` console command.
- Added the `upgrade:recovery-check` console command.
- Added detection of executions that started but did not record normal completion.

#### Distribution Package Planning

- Added an authoritative package-inclusion plan.
- Added required source-directory validation.
- Added required root-file validation.
- Added explicit runtime-directory handling.
- Added private and generated data exclusions.
- Added unsafe-entry detection.
- Added package source-file and directory counts.
- Added package source-size reporting.

#### Portable Package Manifest

- Added `PACKAGE-MANIFEST.json`.
- Added manifest schema versioning.
- Added application-version metadata.
- Added package base-name metadata.
- Added archive-filename metadata.
- Added manifest-listed files and directories.
- Added packaged permission modes.
- Added SHA-256 digests for packaged files.
- Added deterministic manifest ordering.

#### Controlled Package Staging

- Added temporary release staging.
- Added normalized source-directory permissions.
- Added normalized runtime-directory permissions.
- Added normalized source-file permissions.
- Added executable permissions for `iqwurks`.
- Added executable permissions for `migrate.php`.
- Added empty generated runtime directories.
- Added cleanup of temporary staging workspaces.

Package modes are normalized as follows:

- Source directories: `0755`
- Generated runtime directories: `0770`
- Normal files: `0644`
- Application entry points: `0750`

#### Versioned Distribution Archives

- Added versioned GNU TAR distribution archives.
- Added the `package:build` console command.
- Added read-only package preview mode.
- Added explicit `--build` archive creation.
- Added deterministic archive ownership metadata.
- Added deterministic archive timestamp handling.
- Added preservation of controlled package permissions.
- Added package output under `storage/exports/packages`.
- Added Git ignore rules for generated distribution packages.

#### Independent Package Verification

- Added the `package:verify` console command.
- Added optional expected SHA-256 validation.
- Added archive readability validation.
- Added safe archive-path validation.
- Added single top-level package-root validation.
- Added package-root name validation.
- Added package-filename validation.
- Added manifest JSON validation.
- Added manifest-schema validation.
- Added application-version validation.
- Added required installation-path validation.
- Added file checksum validation.
- Added file permission validation.
- Added directory permission validation.
- Added unlisted-entry detection.
- Added empty runtime-directory validation.
- Added forbidden-path validation.
- Added symbolic-link rejection.
- Added temporary extraction-workspace cleanup.
- Added mandatory `deployment/README.md` verification.

Required installation paths include:

- `PACKAGE-MANIFEST.json`
- `CHANGELOG.md`
- `composer.json`
- `composer.lock`
- `config/mail.example.php`
- `deployment/README.md`
- `iqwurks`
- `LICENSE`
- `migrate.php`
- `README.md`
- `ROADMAP.md`
- `VERSION`

#### Deployment Templates

- Added reusable production deployment templates under `deployment`.
- Added an Nginx server-block template.
- Added a PHP-FPM configuration template.
- Added a scheduler, retry, and backup cron template.
- Added an application logrotate template.
- Added a LightDM kiosk-login template.
- Added an Openbox kiosk-autostart template.
- Added a Chromium kiosk-launcher template.
- Added UFW trusted-network guidance.
- Added deployment-template placeholder documentation.

Supported deployment placeholders include:

- `{{APPLICATION_ROOT}}`
- `{{CHROMIUM_BINARY}}`
- `{{KIOSK_HOME}}`
- `{{KIOSK_URL}}`
- `{{KIOSK_USER}}`
- `{{PHP_FPM_SOCKET}}`
- `{{SERVER_NAMES}}`
- `{{TIMEZONE}}`
- `{{TRUSTED_SUBNET}}`

#### Installation and Upgrade Documentation

- Expanded `docs/Installation.md` into a package-oriented installation and upgrade guide.
- Added release-archive verification instructions.
- Added archive extraction instructions.
- Added Composer installation guidance.
- Added ownership and permission guidance.
- Added SMTP configuration guidance.
- Added database initialization and migration guidance.
- Added PHP-FPM installation guidance.
- Added Nginx installation guidance.
- Added scheduler and retry cron guidance.
- Added backup cron guidance.
- Added logrotate guidance.
- Added firewall guidance.
- Added kiosk deployment guidance.
- Added diagnostic-command guidance.
- Added controlled upgrade instructions.
- Added upgrade-status instructions.
- Added interrupted-upgrade recovery guidance.
- Added rollback guidance.
- Added package-building and verification guidance.
- Added release-security validation guidance.
- Added Version 0.9 release notes under `releases/0.9.0.md`.
- Included the complete tracked `releases/` directory in distribution archives.
- Required `releases/<application-version>.md` during package planning and independent archive verification.

#### GitHub Distribution Baseline

- Added the project repository to GitHub.
- Added the maintained main branch.
- Added maintained release branches.
- Added historical release tags.
- Added the Version 0.9 feature branch.
- Added dedicated server SSH authentication for GitHub.
- Added explicit storage-root SQLite ignore rules.
- Removed an obsolete empty tracked development database before publication.
- Confirmed that the active production database remains ignored and untracked.
- Confirmed that SMTP configuration remains ignored and untracked.
- Confirmed that machine inventory files remain untracked.

#### Automated Tests

- Added installation-preflight service and command tests.
- Added upgrade-readiness service and command tests.
- Added upgrade-plan service and command tests.
- Added upgrade-preview tests.
- Added exact-confirmation tests.
- Added safe process-runner tests.
- Added process-timeout tests.
- Added guarded upgrade-operation tests.
- Added controlled upgrade-apply tests.
- Added upgrade-journal tests.
- Added persisted upgrade-status tests.
- Added interrupted-upgrade assessment tests.
- Added recovery-check tests.
- Added package-plan tests.
- Added package-manifest tests.
- Added package-staging tests.
- Added archive-builder tests.
- Added package-verification tests.
- Added archive-checksum tests.
- Added archive-filename tests.
- Added required deployment-documentation tests.
- Added package command argument-validation tests.

The Version 0.9 test suite contains:

- 363 tests
- 2979 assertions

### Changed

- Updated the application version to `0.9.0`.
- Changed installation validation from undocumented manual inspection to a repeatable console workflow.
- Changed upgrade preparation from manual sequencing to explicit readiness, planning, and preview stages.
- Changed upgrade execution to require an exact confirmation phrase.
- Changed child-process execution to bypass the shell.
- Changed controlled operations to capture output, errors, exit status, and duration.
- Changed upgrade execution to preserve a persistent operation journal.
- Changed incomplete upgrade handling to require explicit recovery assessment.
- Changed release creation from source-tree copying to controlled package staging.
- Changed package creation to use GNU TAR with deterministic ownership and timestamp metadata.
- Changed package permissions to be normalized and independently verified.
- Changed release archives to include a portable SHA-256 manifest.
- Changed package verification to reject archives missing deployment documentation.
- Changed release archives to include reusable deployment templates.
- Changed installation documentation to use versioned package distribution as the primary installation workflow.
- Changed generated release archives to remain outside Git version control.
- Changed the project distribution workflow to use GitHub branches, tags, and release artifacts.

### Fixed

- Fixed distribution directories being archived with unintended `0777` permissions.
- Fixed archive verification relying only on source-side assumptions.
- Fixed stale packages without deployment templates being accepted by older verification rules.
- Fixed package verification not requiring deployment documentation.
- Fixed release archives lacking reusable production deployment examples.
- Fixed installation documentation containing an incorrect Bootstrap asset path.
- Fixed an obsolete SQLite database remaining tracked in the repository.
- Fixed storage-root SQLite files not being covered by explicit ignore rules.
- Fixed an invalid server-only SSH directive being present in the system SSH client configuration.
- Fixed the dedicated GitHub SSH key not being selected automatically for normal Git operations.

### Security and Data Integrity

- Upgrade execution requires exact typed confirmation.
- Upgrade readiness is revalidated before controlled execution.
- Child processes are started without shell interpolation.
- Process timeouts prevent indefinitely blocked controlled operations.
- Upgrade journals preserve incomplete and failed execution state.
- Interrupted upgrades are not silently treated as successful.
- Distribution archive paths reject absolute and traversal paths.
- Distribution archives reject symbolic links.
- Distribution archives reject forbidden private paths.
- Distribution archives exclude SMTP credentials.
- Distribution archives exclude active SQLite databases.
- Distribution archives exclude SQLite sidecar files.
- Distribution archives exclude logs, sessions, caches, exports, and backups.
- Distribution archives exclude Git metadata.
- Distribution archives exclude machine inventory files.
- Distribution archives exclude Composer-installed dependencies.
- Packaged file SHA-256 values are verified.
- Packaged permission modes are verified.
- World-writable archive entries are rejected during release validation.
- Release archives require exactly one top-level package directory.
- Temporary verification workspaces are removed after use.
- Deployment templates use placeholders rather than live machine values.
- The repository security audit found no tracked live SMTP credentials.
- The active production database remains ignored and untracked.
- Git object integrity validation passes.

### Database Changes

Version 0.9 does not add an application database migration.

Existing Version 0.8 application data remains compatible, including:

- Employees
- Users
- Punches
- Punch-correction history
- Company settings
- Labor rules
- Payroll periods
- Payroll workflow history
- Payroll review notes
- Payroll exception resolutions
- Email history
- Report delivery schedules
- Email delivery attempts
- Notification recipients
- Retry history

### Upgrade Notes

Installations upgrading from Version 0.8.0 should:

1. Verify the currently installed application version.
2. Create and verify a current database backup.
3. Review `docs/Installation.md`.
4. Obtain the Version 0.9.0 release archive.
5. Obtain the published archive SHA-256 digest.
6. Verify the archive checksum.
7. Run independent package verification.
8. Extract the release into a separate staging location.
9. Install Composer dependencies as documented.
10. Confirm ownership and runtime-directory permissions.
11. Run `./iqwurks upgrade:check`.
12. Resolve all blocking failures.
13. Review all warnings.
14. Run `./iqwurks upgrade:plan`.
15. Run `./iqwurks upgrade:preview`.
16. Record and review the exact confirmation phrase.
17. Execute the controlled upgrade only after reviewing the preview.
18. Run `./iqwurks upgrade:status`.
19. Run `./iqwurks upgrade:recovery-check`.
20. Run database diagnostics.
21. Run scheduler diagnostics.
22. Run mail diagnostics.
23. Run the System Doctor.
24. Run the complete automated test suite.
25. Confirm employee-kiosk operation.
26. Confirm supervisor access.
27. Confirm scheduled report delivery.
28. Confirm automatic retry processing.
29. Confirm automatic backups.
30. Confirm reboot recovery.

Administrators should not bypass readiness failures or manually mark an incomplete upgrade journal as successful.

### Known Limitations

- Version 0.9 does not provide a browser-based installation wizard.
- Operating-system packages are not installed automatically.
- Deployment templates are not installed automatically.
- Deployment placeholders must be reviewed and replaced manually.
- Nginx configuration is not activated automatically.
- PHP-FPM configuration is not activated automatically.
- Firewall rules are not applied automatically.
- LightDM, Openbox, and Chromium are not installed automatically.
- Remote releases are not discovered or downloaded automatically.
- Release archives use published SHA-256 verification but are not cryptographically signed.
- Source rollback requires administrator review.
- The console does not yet provide a universal per-command `--help` option.
- Multiple companies and physical locations are not yet supported.
- The validated production deployment uses HTTP on a trusted local network.
- HTTPS should be added before exposure through an untrusted network.

### Release Validation

Final Version 0.9 automated-test result:

- 363 tests
- 2979 assertions

Validated release conditions include:

- Installation preflight passes on the production host.
- Upgrade readiness has no blocking failure.
- Upgrade planning passes.
- Upgrade preview passes.
- Exact confirmation enforcement passes.
- Upgrade journaling passes.
- Interrupted-upgrade assessment passes.
- Recovery checking passes.
- Distribution package preview passes.
- Distribution archive creation passes.
- Independent package verification passes.
- Required deployment-documentation verification passes.
- Archive permission validation passes.
- No world-writable archive entries are present.
- The archive contains one top-level package root.
- No forbidden private content is present.
- Deployment templates are included.
- PHP syntax validation passes.
- Git whitespace validation passes.
- Repository security review passes.
- Git object integrity validation passes.
- The local and GitHub Version 0.9 feature branches match.


---

## [0.8.0] - 2026-07-31

### Added

#### Weekly Payroll Email Delivery

- Added manual weekly payroll email delivery.
- Added scheduled weekly payroll email delivery.
- Added weekly delivery schedules using company-local weekday and time.
- Added preservation of the original weekly reference date during retries.

#### Payroll-Exception Reports

- Added scheduled open payroll-exception reports.
- Added actionable payroll-period and employee exception summaries.
- Added exception-report retry closure messages when the original exceptions have been resolved.

#### Approval and Operational Notifications

- Added payroll-approval notifications.
- Added operational-failure notifications.
- Added independent recipient subscriptions for both notification categories.
- Expanded Notification Center support for:
  - `daily_payroll`
  - `weekly_payroll`
  - `exception_reports`
  - `approval_notifications`
  - `operational_failures`

#### Payroll Email Attachments

- Added CSV and PDF attachments to daily payroll emails.
- Added CSV and PDF attachments to weekly payroll emails.
- Added shared in-memory payroll attachment generation.
- Added stable attachment filenames and content types.
- Added attachment count, filename, and raw-size history.

#### Report Delivery Schedules

- Added structured schedules for daily payroll reports.
- Added structured schedules for weekly payroll reports.
- Added structured schedules for exception reports.
- Added schedule enablement, timing, weekday settings, and last-delivery metadata.
- Added schedule identifiers to delivery-attempt history.

#### Email Delivery Attempt History

- Added detailed email delivery-attempt records.
- Added pending, sent, and failed statuses.
- Added manual, scheduled, system, and retry sources.
- Added attempt-number and maximum-attempt tracking.
- Added retry-parent relationships.
- Added permanent-failure status.
- Added delivery error messages.
- Added attachment metadata.
- Added start and completion timestamps.

#### Email Retry System

- Added retry planning and retry execution.
- Added retry support for:
  - `daily_payroll`
  - `weekly_payroll`
  - `exception_reports`
- Added daily report-date preservation.
- Added weekly reference-date preservation.
- Added configurable retry maximums, batch limits, and delays.
- Added delayed retry eligibility.
- Added duplicate retry-child prevention.
- Added preview and send modes.
- Added the console command:

```text
mail:retry
```

#### Retry Safety

- Added an internal nonblocking send-mode retry lock.
- Added support for an external cron lock.
- Added malformed retry-record quarantine.
- Added unsupported notification-type quarantine.
- Added permanent-failure exclusion from future retries.
- Added safe closure of resolved exception-report retry chains.

#### Attachment-Size Protection

- Added centralized attachment-size policy.
- Added a default 10 MiB combined raw attachment limit.
- Added `EmailAttachmentSizeExceededException`.
- Added attachment-size enforcement before SMTP transport creation.
- Added permanent failure classification for oversized messages.
- Added oversized-delivery attempt history.
- Added exclusion of oversized failures from retry processing.

#### Employee Kiosk

- Added automatic focus to the available Clock In or Clock Out button.
- Added Enter-key activation of the available authenticated punch action.

#### Automated Tests

- Added weekly payroll email tests.
- Added scheduled report-delivery tests.
- Added exception-report email tests.
- Added approval and operational notification tests.
- Added attachment-generation and normalization tests.
- Added delivery-attempt repository tests.
- Added retry planning, policy, eligibility, execution, and command tests.
- Added retry quarantine tests.
- Added exception-report closure tests.
- Added attachment-size policy tests.
- Added oversized-delivery integration tests.
- Added unsupported retry-type command tests.

The Version 0.8 test suite contains:

```text
251 tests
1138 assertions
```

### Changed

- Updated the application version to `0.8.0`.
- Expanded scheduling from daily payroll only to daily, weekly, and exception reports.
- Expanded Notification Center subscriptions.
- Changed scheduled reports to use configured retry-attempt limits.
- Changed failed delivery tracking to preserve complete attempt chains.
- Changed retries to wait for the configured delay.
- Changed daily and weekly retries to regenerate the original report period.
- Changed exception-report retries to close resolved conditions safely.
- Changed attachments to be generated in memory.
- Changed `MailService` to enforce attachment-size policy before SMTP setup.
- Changed retry preview to show every eligible failed record.
- Changed unsupported retry types to be quarantined during send mode.
- Changed the kiosk action screen to support Enter-key activation.

### Fixed

- Fixed scheduled delivery failures lacking detailed attempt history.
- Fixed daily retries potentially sending the wrong report date.
- Fixed weekly retries potentially sending the wrong reporting week.
- Fixed one failed delivery being able to create duplicate retry children.
- Fixed immediate retry loops after temporary failures.
- Fixed concurrent retry execution outside the cron lock.
- Fixed resolved exception conditions remaining in an open retry chain.
- Fixed malformed retry records remaining eligible indefinitely.
- Fixed unsupported retry types being silently hidden.
- Fixed oversized messages reaching the SMTP path.
- Fixed oversized failures being treated as temporary failures.
- Fixed the kiosk action screen requiring mouse or touch after PIN validation.

### Security and Data Integrity

- Retry metadata is validated before report regeneration.
- Invalid retry records are permanently quarantined.
- Unsupported event-driven notifications are not regenerated automatically.
- Retry-parent relationships prevent duplicate retry children.
- Internal and external locks protect retry execution.
- Attachment filenames are normalized.
- Attachment contents and metadata are validated.
- Attachment size is enforced before SMTP transport creation.
- Oversized attempts remain auditable while being excluded from retries.
- Notification types remain protected by an explicit allowlist.
- SQLite foreign-key enforcement and WAL mode remain enabled.

### Database Changes

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

Added notification-recipient fields:

```text
approval_notifications
operational_failures
```

### Release Validation

Final Version 0.8 automated-test result:

```text
OK (251 tests, 1138 assertions)
```

Final release baseline:

```text
Application version: 0.8.0
Database tables: 18
Applied migrations: 16
SQLite journal mode: wal
Database integrity: ok
Foreign-key violations: 0
```

---

## [0.7.0] - 2026-07-27

### Added

#### Payroll Review Periods

- Added payroll periods with company-local inclusive start and end dates.
- Added payroll-period names with a maximum length of 150 characters.
- Added a maximum period duration of 31 calendar days.
- Added overlapping-period prevention.
- Added payroll-period list, creation, and detail interfaces.
- Added direct links between payroll periods and matching Payroll Workspace reports.
- Added migration `012_create_payroll_review_tables.php`.

Payroll-period states are:

```text
open
under_review
approved
locked
```

#### Payroll Review and Approval Workflow

- Added transition from `open` to `under_review`.
- Added transition from `under_review` back to `open`.
- Added transition from `under_review` to `approved`.
- Added transition from `approved` to `locked`.
- Added reopening from `approved` or `locked` to `under_review`.
- Added explicit approval confirmation.
- Added exact `LOCK` confirmation for final locking.
- Added required reopening reasons of at least 10 characters.
- Added a maximum workflow-reason length of 1,000 characters.
- Added concurrency-aware state transitions.
- Added transactional state changes and immutable history creation.
- Added approval blocking while unresolved exceptions remain.
- Added approving and locking supervisor metadata.
- Added approval and lock timestamps.
- Added clearing of active approval and lock fields during reopening while preserving prior history.

#### Immutable Workflow Records

- Added `payroll_period_history`.
- Added immutable history for:
  - Period creation
  - Beginning review
  - Returning to open
  - Approval
  - Locking
  - Reopening
  - Review-note creation
  - Exception resolution
  - Exception acceptance
- Added previous and new status values.
- Added acting-user identification.
- Added preserved workflow reasons.
- Added workflow-history display on the payroll-period detail page.

#### Payroll Review Notes

- Added immutable supervisor review notes.
- Added note author and timestamp metadata.
- Added note creation while a period is open or under review.
- Added required nonblank note validation.
- Added a maximum review-note length of 1,000 characters.
- Added matching browser-side `maxlength` enforcement.
- Added user-facing immutable-record guidance.
- Added transactional note and history creation.

#### Payroll Exception Review

- Added payroll-exception synchronization from Payroll Workspace results.
- Added stable SHA-256 exception keys.
- Added support for:
  - Missing clock-out activity
  - Missing clock-in activity
  - Unmatched meal activity
  - Unmatched break activity
  - Overlapping punch activity
  - Zero-duration shifts
  - Invalid punch sequences
  - Payroll calculation warnings
- Added open, resolved, and accepted exception states.
- Added exception refreshing.
- Added deletion of stale open exceptions.
- Added reopening of resolved exceptions when the issue returns.
- Added preservation of accepted exceptions during refresh.
- Added optional resolution notes.
- Added required explanations when accepting an exception.
- Added a minimum accepted-exception explanation length of 10 characters.
- Added a maximum exception-note length of 1,000 characters.
- Added transactional exception status and history updates.

Exception states are:

```text
open
resolved
accepted
```

#### Punch Protection

- Added `PayrollPeriodProtectionService`.
- Added company-timezone conversion of stored UTC punch timestamps.
- Added service-layer protection for approved and locked payroll periods.
- Added protection when:
  - Creating a manual punch
  - Editing an existing punch
  - Deleting a punch
- Added validation of both original and proposed punch dates during edits.
- Added detailed protected-period error messages.
- Added shared protection-service registration through the dependency container.

Punch corrections are protected when the company-local punch date falls within an:

```text
approved
locked
```

payroll period.

#### Payroll Report Metadata

- Added exact payroll-period association to Payroll Workspace reports.
- Added partial-overlap detection.
- Added explicit no-association reporting.
- Prevented partial report ranges from inheriting approval or lock status.
- Added payroll-period metadata including:
  - Period ID
  - Period name
  - Date range
  - Workflow status
  - Created-by metadata
  - Review metadata
  - Approval metadata
  - Lock metadata
  - Open exception count
  - Total exception count
  - Approval-blocked state
- Added protected-period warnings for approved and locked reports.
- Added direct links from associated reports to payroll-period details.

#### CSV, PDF, and Email Metadata

- Added payroll-period metadata to Payroll Workspace CSV exports.
- Added payroll-period metadata to Payroll Workspace PDFs.
- Added payroll-period metadata to employee time-card PDFs.
- Added partial-overlap and no-association notices to exported reports.
- Added approved and locked punch-protection notices to PDFs.
- Added payroll-period metadata to manual and scheduled daily payroll emails.
- Added workflow status, exception counts, and approval metadata to email content.
- Added payroll-period association metadata to report-generation logs.

#### Authorization Hardening

- Added reusable database-backed authorization validation by user ID.
- Added active-account enforcement.
- Added administrator and supervisor role enforcement.
- Added database revalidation to payroll-period workflow actions.
- Added database revalidation to punch-correction actions.
- Added session synchronization from current database user records.
- Added session cleanup when an account is missing, inactive, or unauthorized.
- Added automated authorization tests for:
  - Active administrators
  - Active supervisors
  - Inactive accounts
  - Unauthorized roles
  - Missing accounts
  - Invalid user IDs
  - Session synchronization

#### CSRF Protection

- Added `CsrfService`.
- Added cryptographically secure 256-bit session tokens.
- Added constant-time token comparison.
- Added token creation, rotation, validation, and cleanup.
- Added automatic CSRF fields to rendered POST forms.
- Added centralized CSRF enforcement for all POST requests.
- Added safe same-host redirects after invalid CSRF submissions.
- Added route-specific fallback redirects.
- Added `303 See Other` responses for rejected POST requests.
- Added no-cache handling for rejected requests.
- Changed logout from GET to a CSRF-protected POST form.
- Removed the obsolete state-changing GET email-report route.
- Added automated CSRF tests.

#### Automated Tests

- Added payroll-period creation and validation tests.
- Added overlap-prevention tests.
- Added approval and workflow-transition tests.
- Added concurrency and rollback tests.
- Added payroll-period protection tests.
- Added protected punch-correction regression tests.
- Added payroll-exception synchronization tests.
- Added exception-resolution and acceptance tests.
- Added immutable review-note tests.
- Added review-note boundary tests.
- Added payroll report metadata tests.
- Added CSV and PDF metadata tests.
- Added payroll email metadata tests.
- Added authorization tests.
- Added CSRF tests.

The Version 0.7 test suite contains:

```text
136 tests
693 assertions
```

### Changed

- Updated the application version to `0.7.0`.
- Changed payroll review from informal report warnings into an explicit auditable workflow.
- Changed approved and locked payroll periods to protect matching punch dates.
- Changed punch protection to use the configured company timezone.
- Changed workflow actions to use database-validated authenticated users.
- Changed state transitions to verify the expected current database status.
- Changed approval to require zero unresolved exceptions.
- Changed reopening to clear active approval and lock metadata without erasing history.
- Changed reports to distinguish exact associations from partial overlaps.
- Changed Payroll Workspace CSV, PDF, and email outputs to include workflow metadata.
- Changed all POST requests to require CSRF validation.
- Changed `/logout` to POST only.
- Removed `/reports/send-daily-email`.
- Changed CSV generation to explicitly supply the PHP 8.5 escape argument.
- Added Payroll Periods to the Reports navigation.

### Fixed

- Fixed approved and locked payroll periods remaining editable through punch correction.
- Fixed punch edits protecting only the proposed date instead of both the original and proposed dates.
- Fixed workflow transitions potentially relying on stale browser state.
- Fixed approval, locking, reopening, and exception changes lacking transactional immutable history.
- Fixed resolved exceptions remaining resolved when the underlying issue returned.
- Fixed partial-overlap reports potentially appearing to inherit a payroll-period status.
- Fixed logout changing application state through GET.
- Fixed the legacy email GET route bypassing CSRF validation.
- Fixed PHP 8.5 CSV deprecation warnings.
- Fixed payroll review notes having no application-level maximum length.
- Fixed controller-local authorization checks trusting only a positive session user ID.

### Security and Data Integrity

- Every POST request now requires a valid session-bound CSRF token.
- CSRF tokens use `random_bytes()` and `hash_equals()`.
- Logout is POST only and CSRF protected.
- Payroll workflow users are revalidated against the database.
- Inactive accounts and unauthorized roles cannot perform workflow actions.
- Payroll-period transitions use transactions and expected-state updates.
- Approval is blocked while open exceptions remain.
- Approved and locked periods protect matching company-local punch dates.
- Both original and proposed punch dates are protected during editing.
- Workflow history and review notes are append only.
- Exception resolutions and acceptances are preserved with immutable history.
- Reopening clears active metadata without deleting historical events.
- Payroll-period overlap prevention protects date-range integrity.
- Exact and partial report associations are distinguished.
- SQLite foreign-key enforcement and WAL mode remain enabled.

### Database Changes

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

Added supporting indexes for:

- Payroll-period dates
- Payroll-period status
- Workflow history
- Workflow users
- Review notes
- Exception status
- Exception employees

The validated Version 0.7 database contains:

```text
16 tables
12 applied migrations
```

### Upgrade Notes

Installations upgrading from Version 0.6.0 should:

1. Create and verify a database backup:

```bash
./iqwurks backup:create
./iqwurks backup:verify
```

2. Enable maintenance mode:

```bash
./iqwurks maintenance:on Version 0.7 upgrade
```

3. Install the Version 0.7 source.
4. Install or update Composer dependencies if required.
5. Run migrations:

```bash
php migrate.php
```

6. Confirm migration 012 is recorded.
7. Run database diagnostics:

```bash
./iqwurks database:check
```

8. Confirm:

```text
Integrity: ok
Foreign-key violations: 0
Journal mode: wal
Tables: 16
Migrations: 12
```

9. Log in as an active administrator or supervisor.
10. Create a test payroll period.
11. Confirm overlapping payroll periods are rejected.
12. Open the exact matching Payroll Workspace date range.
13. Refresh payroll exceptions.
14. Resolve or accept any test exceptions.
15. Begin payroll review.
16. Confirm approval is blocked while an open exception exists.
17. Approve the test payroll period.
18. Confirm matching punch corrections are blocked.
19. Lock the test payroll period using exact `LOCK` confirmation.
20. Reopen the test payroll period with a reason of at least 10 characters.
21. Confirm prior workflow events remain in history.
22. Confirm review-note length validation.
23. Confirm Payroll Workspace CSV metadata.
24. Confirm Payroll Workspace PDF metadata.
25. Confirm employee time-card PDF metadata.
26. Confirm daily payroll email metadata.
27. Confirm logout works through the POST form.
28. Confirm GET `/logout` returns a not-found response.
29. Confirm GET `/reports/send-daily-email` returns a not-found response.
30. Run the complete automated suite:

```bash
php vendor/bin/phpunit --display-deprecations
```

Expected:

```text
OK (136 tests, 693 assertions)
```

31. Run scheduler diagnostics:

```bash
./iqwurks scheduler:check
```

32. Run mail diagnostics:

```bash
./iqwurks mail:check
```

33. Run the System Doctor:

```bash
./iqwurks doctor
```

34. Disable maintenance mode:

```bash
./iqwurks maintenance:off
```

35. Perform browser smoke testing for:
    - Employee kiosk
    - Supervisor login
    - Dashboard
    - Employee administration
    - Punch correction
    - Payroll reports
    - Payroll Workspace
    - Payroll periods
    - Email reports
    - Settings
    - Logout

No manual data conversion is required beyond migration 012.

### Known Limitations

- Workflow metadata is associated only when a report range exactly matches one payroll period.
- Partial overlaps intentionally do not inherit approval or lock status.
- Advanced payroll-period list filtering is not yet implemented.
- Standalone reopen and workflow-history pages are not yet implemented.
- Export filenames do not yet include workflow-status suffixes.
- Department-level payroll-review summaries are not yet implemented.
- External payroll-provider export profiles are not yet implemented.
- Manual and scheduled weekly payroll emails are not yet implemented.
- Scheduled exception-report delivery is not yet implemented.
- PDF and CSV email attachments are not yet implemented.
- A guided installation wizard is not yet implemented.
- An automated application-upgrade command is not yet implemented.
- Release packaging is not yet implemented.
- The console does not yet provide a universal per-command `--help` option.
- Multiple companies and physical locations are not yet supported.
- The validated deployment uses HTTP on a trusted local network.
- HTTPS should be added before exposing the application across an untrusted network.

### Release Validation

Current validated baseline:

```text
Application version: 0.7.0
PHP version: 8.5.4
PHPUnit version: 12.5.31
Tests: 136
Assertions: 693
Database tables: 16
Applied migrations: 12
SQLite journal mode: wal
Database integrity: ok
Foreign-key violations: 0
```

All application, migration, route, and test PHP files pass syntax validation.

`git diff --check` reports no whitespace errors.

Version 0.7.0 completed production validation, backup verification, diagnostics, browser acceptance testing, and release preparation.

---

## [0.6.0] - 2026-07-21

### Added

#### Database Backup and Retention

- Added verified, timestamped SQLite database backups.
- Added backup integrity checks.
- Added foreign-key validation for backups.
- Added a backup catalog service.
- Added backup listing.
- Added configurable backup retention.
- Added retention preview mode.
- Added explicit retention deletion mode.
- Added backup execution locking.
- Added backup logging and failure reporting.
- Added automatic daily backup execution through cron.
- Added the following console commands:
  - `backup:create`
  - `backup:list`
  - `backup:prune`
  - `backup:run`
  - `backup:verify`

#### Database Restore

- Added safe database-restore previews.
- Added explicit `--apply` restore execution.
- Added exact backup-filename confirmation.
- Added backup verification before restoration.
- Added foreign-key verification before restoration.
- Added pre-restore safety backups.
- Added maintenance-mode integration during restoration.
- Added SQLite WAL checkpoint handling.
- Added stale SQLite sidecar-file cleanup.
- Added post-restore integrity validation.
- Added migration-history verification after restoration.
- Added restore rollback handling.
- Added the `backup:restore` console command.

#### Database Health and Diagnostics

- Added the `database:check` console command.
- Added SQLite integrity checking.
- Added foreign-key violation checking.
- Added database file and directory permission checks.
- Added SQLite version reporting.
- Added journal-mode reporting.
- Added application-table counts.
- Added migration counts.
- Added database page and unused-page statistics.
- Added database-size and diagnostic-duration reporting.

#### System Doctor

- Added the `doctor` console command.
- Added application-version checks.
- Added PHP-version checks.
- Added required PHP-extension checks.
- Added Composer dependency checks.
- Added configuration-file checks.
- Added application-timezone checks.
- Added SMTP configuration checks.
- Added SMTP configuration-permission checks.
- Added runtime-directory checks.
- Added disk-capacity checks.
- Added maintenance-mode checks.
- Added active-database health checks.
- Added migration-history checks.
- Added verified-backup checks.
- Added scheduler-cron checks.
- Added automatic-backup-cron checks.
- Added scheduler-activity checks.
- Added mail-activity checks.
- Added `PASS`, `WARN`, and `FAIL` diagnostic classifications.

#### Scheduler and Mail Diagnostics

- Added the `scheduler:check` console command.
- Added scheduler-cron detection.
- Added scheduler-lock inspection.
- Added scheduler application-log inspection.
- Added scheduler cron-output inspection.
- Added recent scheduler-failure detection.
- Added report-schedule validation.
- Added scheduled-recipient validation.
- Added last-delivery validation.
- Added the `mail:check` console command.
- Added SMTP hostname-resolution checks.
- Added SMTP connectivity checks.
- Added TLS-readiness checks.
- Added mail-transport readiness checks.
- Added delivery-log activity checks.

#### Maintenance Mode

- Added web maintenance mode.
- Added optional maintenance reasons.
- Added maintenance activation timestamps.
- Added configurable retry-after behavior.
- Added persistent maintenance state.
- Added the following console commands:
  - `maintenance:on`
  - `maintenance:off`
  - `maintenance:status`

#### Supervisor Punch Correction

- Added supervisor punch-history management.
- Added manual missing-punch entry.
- Added punch editing.
- Added punch deletion.
- Added required correction reasons.
- Added typed punch-deletion confirmation.
- Added punch-sequence validation.
- Added transaction rollback for invalid punch sequences.
- Added supervisor identification on corrected punches.
- Added correction timestamps.
- Added correction audit records.
- Added immutable punch-correction history.
- Added support for correcting:
  - `clock_in`
  - `clock_out`
  - `break_out`
  - `break_in`
  - `meal_out`
  - `meal_in`
- Added migration `010_add_punch_correction_support.php`.

#### Authentication Hardening

- Added centralized supervisor route guarding.
- Added database revalidation of authenticated supervisors.
- Added active-account validation.
- Added explicit administrator and supervisor role authorization.
- Added session regeneration after login.
- Added intended-page preservation.
- Added complete supervisor-session cleanup during logout.
- Added local application session storage.
- Added strict session handling.
- Added HTTP-only session cookies.
- Added SameSite cookie protection.
- Added supervisor session-activity tracking.

#### Kiosk Inactivity Protection

- Added a configurable kiosk inactivity timeout.
- Added a permitted timeout range of 15 to 600 seconds.
- Added a default timeout of 60 seconds.
- Added a final ten-second timeout warning.
- Added keyboard-activity detection.
- Added mouse and pointer-activity detection.
- Added touch-activity detection.
- Added form-input activity detection.
- Added automatic cleanup of unfinished kiosk interactions.
- Added selected-employee cleanup.
- Added PIN and temporary-authentication cleanup.
- Added automatic return to the employee-number screen.
- Added server-side timeout validation.
- Added stale PIN-form rejection.
- Added stale punch-form rejection.
- Added migration `011_add_kiosk_inactivity_timeout.php`.
- Added a Kiosk Safety section to Company Settings.

#### Production Web Deployment

- Added and validated an Nginx production deployment.
- Added and validated PHP-FPM 8.5 operation.
- Added public-directory document-root isolation.
- Added front-controller routing through `public/index.php`.
- Added protection against arbitrary PHP-file execution.
- Added hidden-file protection.
- Added security-related HTTP response headers.
- Added static-asset caching.
- Added LAN-only firewall access for the application.
- Added PHP-FPM production overrides.
- Added application-specific PHP-FPM error logging.

#### Dedicated Physical Kiosk

- Added a restricted local `kiosk` Linux account.
- Added LightDM automatic login.
- Added an Openbox kiosk session.
- Added Chromium full-screen kiosk mode.
- Added local kiosk startup at `http://127.0.0.1/kiosk`.
- Added automatic Chromium restart after browser exit.
- Added application-availability checks before Chromium startup.
- Added retry behavior while the web application is unavailable.
- Added idle mouse-cursor hiding.
- Disabled screen blanking.
- Disabled display power management.
- Disabled sleep, suspend, and hibernation.
- Added and validated kiosk recovery after Chromium termination.
- Added and validated kiosk recovery after LightDM restart.
- Added and validated full reboot recovery.

#### SQLite WAL Mode

- Added SQLite foreign-key enforcement on the primary connection.
- Added a 10-second SQLite busy timeout.
- Added SQLite WAL journal mode.
- Added normal synchronous mode.
- Added automatic WAL checkpoint configuration.
- Added group-writable SQLite sidecar-file handling.
- Added WAL-aware backup behavior.
- Added WAL-aware restore behavior.

#### Log Rotation

- Added application log rotation.
- Added scheduler log rotation.
- Added cron log rotation.
- Added mail log rotation.
- Added PHP-FPM application error-log rotation.
- Added kiosk browser-log rotation.
- Added daily rotation.
- Added 5 MB size-based rotation.
- Added compressed log archives.
- Added date-based archive names.
- Added 30-rotation application-log retention.
- Added 14-rotation kiosk-browser-log retention.

#### Offline Frontend Assets

- Added local Bootstrap 5.3.7 CSS.
- Added local Bootstrap 5.3.7 JavaScript.
- Added local Bootstrap Icons 1.13.1 CSS.
- Added local Bootstrap Icons font files.
- Added vendored license files.
- Added a SHA-256 checksum manifest.
- Removed reliance on external frontend CDNs for primary interface assets.
- Added offline kiosk interface support.

#### Console Architecture

- Added lazy command loading.
- Expanded the console application to include:

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

#### Automated Tests

- Added punch-correction insertion tests.
- Added punch-correction editing tests.
- Added punch-correction deletion tests.
- Added required-correction-reason tests.
- Added punch-sequence validation tests.
- Added immutable-history tests.
- Added transaction rollback tests.
- Expanded the automated suite to 36 tests and 188 assertions.

### Changed

- Updated the application version to `0.6.0`.
- Changed the primary SQLite connection to WAL mode.
- Changed database backups to use a live-database-safe SQLite backup process.
- Changed restore operations to account for WAL and SQLite sidecar files.
- Changed administrative routes to require centralized authentication guarding.
- Changed supervisor sessions to be revalidated against the database.
- Changed login handling to regenerate the PHP session identifier.
- Changed logout handling to remove session state and expire the session cookie.
- Changed application sessions to use `storage/sessions`.
- Changed kiosk transaction handling to track server-side activity.
- Changed the kiosk starting page to clear unfinished employee state.
- Changed invalid employee-number and PIN responses to restart the kiosk flow safely.
- Changed punch correction to validate the complete resulting employee punch sequence before committing.
- Changed frontend references from CDN-hosted assets to locally stored assets.
- Changed production execution from the PHP development server to Nginx and PHP-FPM.
- Changed application and kiosk logs to use managed rotation.
- Changed the Git ignore policy to exclude runtime sessions, backups, temporary files, and safety copies.
- Updated the README for Version 0.6.
- Updated the roadmap to mark Version 0.6 complete.
- Added Version 0.6 release notes.

### Fixed

- Fixed unfinished kiosk transactions remaining visible when an employee walked away.
- Fixed stale kiosk PIN and punch forms being usable after the configured timeout.
- Fixed old kiosk session data potentially carrying into a new employee-number submission.
- Fixed supervisor sessions remaining valid after an account was removed or deactivated.
- Fixed unauthorized roles potentially retaining administrative session state.
- Fixed SQLite concurrency limitations caused by rollback-journal operation.
- Fixed direct active-database copying risks during backup creation.
- Fixed restore risks caused by stale WAL, SHM, or journal files.
- Fixed missing backup verification before restore execution.
- Fixed invalid punch corrections potentially leaving partial database changes.
- Fixed application frontend dependence on external CDN availability.
- Fixed unbounded application and kiosk browser log growth.
- Fixed runtime PHP session files appearing in Git status.
- Removed obsolete local safety-copy files.
- Fixed local Chromium recovery after an unexpected browser exit.
- Fixed kiosk startup dependency on the application already being available.

### Security and Data Integrity

- Administrative routes now require a currently active administrator or supervisor account.
- Supervisor identity and role are revalidated against the database.
- Login regenerates the session identifier.
- Logout removes authentication state and expires the session cookie.
- Session cookies use HTTP-only and SameSite protections.
- SMTP credentials remain in ignored local configuration.
- Runtime session data is excluded from version control.
- Database backups are verified before being accepted.
- Database restores require an exact typed filename confirmation.
- Restore execution creates a pre-restore safety backup.
- Restore execution verifies the resulting active database.
- Punch correction reasons are required.
- Punch deletion requires typed confirmation.
- Punch corrections preserve immutable history.
- Invalid punch sequences are rolled back.
- Kiosk timeout never creates a punch automatically.
- Expired kiosk forms are rejected by the server.
- Nginx exposes only the `public` directory.
- Arbitrary PHP-file execution is blocked.
- Hidden files are blocked.
- Firewall access is restricted to the trusted local network.
- SQLite foreign-key enforcement remains enabled.
- Database integrity and foreign-key checks are available through diagnostics.
- WAL-safe backup behavior was tested against live application writes.

### Upgrade Notes

Existing installations upgrading to Version 0.6.0 should:

1. Create and verify a database backup.
2. Pull or install the Version 0.6.0 source.
3. Install or update Composer dependencies if required.
4. Confirm runtime-directory ownership and permissions.
5. Run all pending migrations:

```bash
php migrate.php
```

6. Confirm migrations 010 and 011 are recorded.
7. Run:

```bash
./iqwurks database:check
```

8. Open **Settings → Company Settings**.
9. Review the Kiosk Safety inactivity timeout.
10. Confirm that the timeout is between 15 and 600 seconds.
11. Test kiosk timeout behavior on the PIN screen.
12. Test kiosk timeout behavior on the punch-options screen.
13. Confirm that no punch is created during a timeout.
14. Review supervisor punch-correction access.
15. Test adding, editing, and deleting a non-production punch.
16. Confirm correction reasons and immutable history.
17. Review `config/backup.php`.
18. Create a manual backup:

```bash
./iqwurks backup:create
```

19. Verify the newest backup:

```bash
./iqwurks backup:verify
```

20. Preview a restore without applying it.
21. Install or confirm the automatic backup cron entry.
22. Confirm the scheduler cron entry.
23. Install or confirm the logrotate policy.
24. Configure Nginx and PHP-FPM for production.
25. Confirm the local session directory is writable.
26. Confirm Bootstrap and Bootstrap Icons are served locally.
27. Verify the vendored frontend assets:

```bash
sha256sum --check public/assets/vendor/SHA256SUMS
```

28. Run scheduler diagnostics:

```bash
./iqwurks scheduler:check
```

29. Run mail diagnostics:

```bash
./iqwurks mail:check
```

30. Run the System Doctor:

```bash
./iqwurks doctor
```

31. Run the full automated test suite:

```bash
php vendor/bin/phpunit
```

The expected Version 0.6.0 result is:

```text
OK (36 tests, 188 assertions)
```

32. Reboot the production computer.
33. Confirm Nginx and PHP-FPM recover.
34. Confirm cron recovers.
35. Confirm the physical kiosk recovers.
36. Confirm local and LAN kiosk access.
37. Confirm supervisor login.
38. Confirm scheduled-report activity.
39. Confirm scheduled-backup activity.

No manual data conversion is required beyond the normal migration process.

### Known Limitations

- Manual weekly payroll email delivery is not yet implemented.
- Scheduled weekly payroll email delivery is not yet implemented.
- Scheduled exception-report delivery is not yet implemented.
- PDF and CSV email attachments are not yet implemented.
- Payroll-period approval is not yet implemented.
- Payroll-period locking is not yet implemented.
- Reopening approved payroll is not yet implemented.
- Supervisor review notes are not yet implemented.
- Third-party payroll export profiles are not yet implemented.
- A guided installation wizard is not yet implemented.
- An automated application-upgrade command is not yet implemented.
- Release packaging is not yet implemented.
- The console does not yet provide a universal per-command `--help` option.
- Multiple companies and multiple physical locations are not yet supported.
- The validated production deployment uses HTTP on a trusted local network.
- HTTPS should be added before the application is exposed through an untrusted network.
- A newly rotated empty mail log can temporarily produce a System Doctor warning until a successful email is logged.

---

## [0.5.0] - 2026-07-20

### Added

#### Payroll Workspace

- Added date-range payroll reporting.
- Added employee filtering.
- Added department filtering.
- Added report-level payroll totals.
- Added employee daily-detail sections.
- Added payroll-week summaries within date-range reports.
- Added payroll warnings and review status throughout the workspace.

#### Configurable Labor Rules

- Added a dedicated Labor Rules administration page.
- Added configurable daily overtime thresholds.
- Added configurable weekly overtime thresholds.
- Added configurable double-time thresholds.
- Added configurable Sunday or Monday workweek starts.
- Added persistent labor-rule storage through the repository and service layers.
- Added Labor Rules navigation under Settings.
- Added validation requiring the double-time threshold to exceed the daily overtime threshold.

#### Payroll Calculations

- Added explicit daily-overtime totals.
- Added explicit weekly-overtime totals.
- Added explicit double-time totals.
- Added total-overtime totals.
- Added premium-hour totals.
- Prevented daily overtime from being counted again as weekly overtime.
- Restricted weekly-overtime conversion to hours that remained classified as regular.
- Standardized payroll relationships:

```text
Total Overtime = Daily Overtime + Weekly Overtime

Premium Hours = Total Overtime + Double-Time

Payable Hours = Regular + Total Overtime + Double-Time
```

#### CSV Exports

- Added Payroll Workspace CSV export.
- Expanded daily payroll CSV output.
- Expanded weekly payroll CSV output.
- Added columns for:
  - Regular hours
  - Daily overtime
  - Weekly overtime
  - Double-time
  - Total overtime
  - Premium hours
  - Total payable hours
- Added report-level totals to Payroll Workspace CSV output.

#### PDF Documents

- Added daily payroll PDF export.
- Added weekly payroll-register PDF export.
- Added Payroll Workspace PDF export.
- Added printable employee time-card PDF export.
- Added company information, report periods, and timezone information to PDFs.
- Added payroll warnings and review status to PDFs.
- Added employee and supervisor signature lines to employee time cards.
- Added regular, overtime, double-time, premium, and payable totals to PDF documents.

#### Dashboard and Email Reporting

- Added daily overtime, weekly overtime, double-time, total overtime, premium hours, and payable hours to the dashboard’s current-week totals.
- Added a Labor Rules quick action to the dashboard.
- Expanded daily payroll email content with:
  - Daily overtime
  - Double-time
  - Total overtime
  - Premium hours
  - Report-wide totals
  - Employees requiring review
- Added explanatory text clarifying that weekly overtime is calculated in weekly payroll reports.
- Added payroll totals to report-generation logs.

#### Automated Tests

- Added dedicated labor-rules payroll tests.
- Added configurable-threshold test coverage.
- Added double-time classification coverage.
- Added weekly-overtime conversion coverage.
- Added no-double-counting assertions.
- Added Sunday and Monday workweek validation coverage.
- Expanded the automated suite to 28 tests and 116 assertions.

### Changed

- Labor Rules is now the authoritative source for:
  - Daily overtime threshold
  - Weekly overtime threshold
  - Double-time threshold
  - Workweek start day
- Company Settings remains responsible for:
  - Company identity
  - Timezone
  - Punch rounding
  - Meal deductions
  - Paid-break allowance
- Removed visible overtime and workweek controls from Company Settings.
- Retained legacy Company Settings values only as compatibility fallbacks.
- Updated `PunchReportService` to obtain labor-rule values from `LaborRulesService`.
- Updated Payroll Workspace calculations to use the configured workweek and overtime thresholds.
- Updated daily, weekly, and date-range payroll views with consistent payroll categories.
- Updated dashboard payroll totals to use the expanded weekly result structure.
- Changed employee time-card PDFs to landscape orientation for improved readability.
- Standardized CSV and PDF terminology across all payroll outputs.
- Updated project documentation for the completed Reporting and Payroll Documents release.

### Fixed

- Fixed daily and weekly reports potentially using obsolete overtime values from Company Settings.
- Fixed daily overtime being included in weekly-overtime eligibility.
- Fixed double-time being reported as ordinary overtime.
- Fixed total overtime calculations that could include double-time.
- Fixed premium-hour totals to include overtime and double-time exactly once.
- Fixed payable-hour totals to reconcile with regular, overtime, and double-time categories.
- Fixed inconsistent payroll labels between web reports, CSV files, PDF files, email reports, and dashboard totals.
- Fixed weekly PDF exporter identification by using the existing `PayrollRegisterPdfExporter`.
- Removed temporary development backup files after successful validation.

### Security and Data Integrity

- Payroll calculations now follow one authoritative labor-rule configuration.
- Overtime and double-time classifications are independently represented.
- Weekly overtime cannot reclassify hours already assigned to daily overtime or double-time.
- Existing company settings remain available as safe migration fallbacks.
- Payroll warnings remain visible in web reports, PDF documents, and email reports.
- Employee payroll history remains preserved.

### Upgrade Notes

Existing installations upgrading to Version 0.5.0 should:

1. Pull the Version 0.5.0 source.
2. Run all pending database migrations.
3. Open **Settings → Labor Rules**.
4. Confirm the daily overtime threshold.
5. Confirm the weekly overtime threshold.
6. Confirm the double-time threshold.
7. Confirm the Sunday or Monday workweek start.
8. Review Company Settings for timezone, rounding, meals, and breaks.
9. Generate daily and weekly payroll reports.
10. Verify CSV and PDF downloads.
11. Send a manual payroll email.
12. Run the full automated test suite:

```bash
vendor/bin/phpunit
```

The expected Version 0.5.0 result is:

```text
28 tests
116 assertions
```

Legacy overtime and pay-period values remain in Company Settings storage for backward compatibility, but Labor Rules is authoritative.

### Known Limitations

- Manual weekly payroll email delivery is not yet implemented.
- Scheduled weekly payroll email delivery is not yet implemented.
- Scheduled exception-report delivery is not yet implemented.
- Supervisor punch correction is not yet available.
- Payroll approval and locking are not yet available.
- Automatic backup and restore workflows are not yet available.
- Health-check and diagnostic console commands remain planned.

---

## [0.4.0] - 2026-07-15

### Added

- Configurable payroll policies:
  - Daily overtime threshold
  - Weekly overtime threshold
  - Punch rounding interval and mode
  - Automatic meal deduction
  - Paid-break allowance
- Focused payroll calculation classes with automated PHPUnit coverage.
- Company-timezone-aware daily payroll reporting.
- Company-timezone-aware weekly payroll reporting.
- Daily and weekly payroll CSV downloads.
- Weekly overtime calculation separated from daily overtime.
- Employee punch-history validation and incomplete-shift warnings.
- Notification Center with:
  - Database-managed recipients
  - Daily payroll subscriptions
  - Weekly payroll subscriptions
  - Exception-report subscriptions
  - Recipient activation and deactivation
  - Recipient deletion
- Employee lifecycle management:
  - Activate employees
  - Deactivate employees while preserving payroll history
  - Permanently delete employees only when no punch history exists
- Live operations dashboard displaying:
  - Active and total employees
  - Employees currently clocked in
  - Today’s punch count
  - Current-week payroll totals
  - Payroll issues requiring review
  - Recent punch activity
  - Automatic-report status
  - Last email-report status
- Shared dependency container registrations for new repositories, services, and exporters.
- Focused payroll and payroll-export controllers.
- Improved report navigation.
- PHPUnit cache ignore rule.

### Changed

- Email recipients now come exclusively from the Notification Center database.
- SMTP configuration files now contain only transport and sender settings.
- Daily payroll email content includes:
  - Gross hours
  - Meal deduction
  - Unpaid breaks
  - Regular hours
  - Overtime hours
  - Payable hours
  - Payroll status
- Payroll calculations are shared across web reports, email reports, dashboard totals, and exports.
- Report routes were reorganized around focused controllers.
- Employee lists distinguish active, inactive, historical, and deletable records.
- Dashboard placeholders were replaced with live operational data.
- Navigation links were corrected to use the actual report and settings routes.

### Fixed

- Corrected company-timezone display for the kiosk’s last-punch time.
- Corrected payroll day boundaries so stored UTC punches are grouped by company-local date.
- Prevented malformed clock-out records from silently producing valid payroll totals.
- Prevented duplicate employee numbers when editing employees.
- Prevented permanent deletion of employees with payroll history.
- Removed obsolete configuration-based recipient counts.
- Removed accidentally tracked backup and generated files.

### Security and Data Integrity

- Payroll history is preserved when employees are deactivated.
- Permanent employee deletion is blocked when punch records exist.
- Notification recipients are validated and normalized before storage.
- Notification types are restricted through an explicit allowlist.
- SMTP secrets remain in the ignored local `config/mail.php` file.

### Known Limitations at Release

- PDF payroll registers were not included in Version 0.4.
- Date-range payroll reports were not available.
- Department filtering and payroll search were not available.
- Automated backup and restore workflows remained planned.
- Exception-report email delivery was prepared in the Notification Center but was not scheduled.

---

## [0.3.0] - 2026-07-13

### Added

- Centralized application logging.
- Component-specific log channels.
- Logger factory and file log handlers.
- Console application and command architecture.
- Automatic report scheduler.
- Cron integration.
- Scheduler locking and duplicate-send prevention.
- Company-configurable report schedule.
- Company timezone support.
- Automatic daily payroll email delivery.
- Email history and delivery logging.
- Release-management documentation.
- Definition of Done.
- Coding standards.
- Contributor guide.

### Changed

- Scheduler logs record startup, evaluation, completion, failures, duration, timezone, process, and memory details.
- Structured scheduler logs are separated from raw cron output.
- Application services are increasingly resolved through the shared dependency container.

### Fixed

- Improved scheduler diagnostics and exception handling.
- Improved mail-delivery logging.
- Standardized release and Git workflows.

---

## [0.2.0-alpha]

### Added

- Company settings.
- Manual payroll email reports.
- Automatic report scheduling foundation.
- Email audit history.
- Company branding.
- Timezone-aware report display.
- Migration improvements.

---

## [0.1.0]

### Added

- Custom MVC framework.
- Supervisor authentication.
- Employee management.
- Employee PIN management.
- Kiosk interface.
- Punch tracking.
- Initial payroll reporting.
- SQLite database.
- Repository and service layers.
