# IQwurksPunch Payroll Review and Approval Design

**Target release:** IQwurksPunch 0.7.0  
**Feature area:** Payroll Review, Approval, and Locking  
**Status:** Design approved for implementation

---

## 1. Purpose

Version 0.7 introduces a controlled payroll-review workflow.

The feature will allow supervisors to:

- Create a payroll period
- Review calculated payroll
- Identify unresolved exceptions
- Record review notes
- Submit payroll for approval
- Approve payroll
- Lock an approved payroll period
- Reopen a payroll period with a required reason
- Preserve immutable workflow history
- Prevent unauthorized changes to locked payroll data

The design builds on the existing payroll calculators, reports, punch-correction system, audit logging, and supervisor authentication.

---

## 2. Goals

The Version 0.7 workflow must provide:

1. A defined payroll period with start and end dates.
2. A clear workflow status.
3. A record of who performed each workflow action.
4. A timestamp for each action.
5. Required reasons for reopening or unlocking payroll.
6. Protection against silent changes to approved payroll.
7. Exception visibility before approval.
8. Approval status in payroll reports and exports.
9. Immutable workflow history.
10. Transaction-safe state changes.

---

## 3. Non-Goals

Version 0.7 will not include:

- Direct payroll-provider submission
- ACH or employee payment processing
- Tax calculation
- General-ledger integration
- Multi-company payroll
- Multiple approval chains
- Digital signatures
- External employee approval
- Cloud synchronization
- Automatic payroll-period creation
- Automatic payroll-period approval

These may be considered in later releases.

---

## 4. Payroll Period Definition

A payroll period represents a specific inclusive date range.

Example:

```text
Start date: 2026-07-13
End date:   2026-07-19
```

Each period must have:

- Unique identifier
- Start date
- End date
- Display name
- Workflow status
- Created timestamp
- Creating supervisor
- Optional review timestamp
- Optional reviewing supervisor
- Optional approval timestamp
- Optional approving supervisor
- Optional lock timestamp
- Optional locking supervisor
- Last update timestamp

Payroll periods may not overlap.

The system must reject a new period when any portion of its date range overlaps an existing period.

---

## 5. Payroll Period Statuses

Version 0.7 will use these statuses:

```text
open
under_review
approved
locked
```

### Open

The period exists but formal review has not begun.

Allowed actions:

- View payroll
- View exceptions
- Add review notes
- Correct punches
- Begin review

### Under Review

A supervisor is actively reviewing the period.

Allowed actions:

- View payroll
- View exceptions
- Add review notes
- Correct punches
- Return to open
- Approve when requirements are satisfied

### Approved

A supervisor has approved the period.

Allowed actions:

- View payroll
- Export reports
- Send approved reports
- Lock the period
- Reopen with a required reason

Punch corrections affecting the approved period must be blocked until the period is reopened.

### Locked

The period is finalized and protected.

Allowed actions:

- View payroll
- Export reports
- View workflow history
- Reopen with an authorized supervisor and required reason

Punch corrections affecting the locked period must be blocked.

---

## 6. Status Transitions

Allowed transitions:

```text
open
  |
  v
under_review
  |
  v
approved
  |
  v
locked
```

Controlled reverse transitions:

```text
under_review -> open
approved     -> under_review
locked       -> under_review
```

Rules:

- `open -> under_review` requires an authenticated supervisor.
- `under_review -> approved` requires approval validation.
- `approved -> locked` requires explicit confirmation.
- `under_review -> open` may include an optional reason.
- `approved -> under_review` requires a reason.
- `locked -> under_review` requires a reason.
- Direct `open -> approved` is not allowed.
- Direct `open -> locked` is not allowed.
- Direct `under_review -> locked` is not allowed.
- Direct `locked -> approved` is not allowed.

---

## 7. Database Tables

Version 0.7 will add three primary tables:

```text
payroll_periods
payroll_period_history
payroll_review_notes
```

A fourth table may be added for persisted exception resolution:

```text
payroll_exception_resolutions
```

---

## 8. `payroll_periods` Table

Proposed schema:

```sql
CREATE TABLE payroll_periods
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    period_name TEXT NOT NULL,

    start_date TEXT NOT NULL,

    end_date TEXT NOT NULL,

    status TEXT NOT NULL DEFAULT 'open',

    created_by_user_id INTEGER NOT NULL,

    reviewed_by_user_id INTEGER NULL,

    approved_by_user_id INTEGER NULL,

    locked_by_user_id INTEGER NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    review_started_at DATETIME NULL,

    approved_at DATETIME NULL,

    locked_at DATETIME NULL,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY
    (
        created_by_user_id
    )
    REFERENCES users(id),

    FOREIGN KEY
    (
        reviewed_by_user_id
    )
    REFERENCES users(id),

    FOREIGN KEY
    (
        approved_by_user_id
    )
    REFERENCES users(id),

    FOREIGN KEY
    (
        locked_by_user_id
    )
    REFERENCES users(id),

    CHECK
    (
        status IN
        (
            'open',
            'under_review',
            'approved',
            'locked'
        )
    ),

    CHECK
    (
        start_date <= end_date
    )
);
```

Indexes:

```sql
CREATE INDEX idx_payroll_periods_dates
ON payroll_periods
(
    start_date,
    end_date
);

CREATE INDEX idx_payroll_periods_status
ON payroll_periods
(
    status
);
```

The service layer will enforce non-overlapping date ranges.

---

## 9. `payroll_period_history` Table

This table stores immutable workflow events.

Proposed schema:

```sql
CREATE TABLE payroll_period_history
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    payroll_period_id INTEGER NOT NULL,

    action TEXT NOT NULL,

    previous_status TEXT NULL,

    new_status TEXT NOT NULL,

    reason TEXT NULL,

    user_id INTEGER NOT NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY
    (
        payroll_period_id
    )
    REFERENCES payroll_periods(id)
    ON DELETE RESTRICT,

    FOREIGN KEY
    (
        user_id
    )
    REFERENCES users(id)
);
```

Supported actions may include:

```text
created
review_started
returned_to_open
approved
locked
reopened
note_added
exception_resolved
exception_reopened
```

History records must not be edited or deleted through normal application workflows.

---

## 10. `payroll_review_notes` Table

Review notes provide period-specific supervisor documentation.

Proposed schema:

```sql
CREATE TABLE payroll_review_notes
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    payroll_period_id INTEGER NOT NULL,

    note TEXT NOT NULL,

    created_by_user_id INTEGER NOT NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY
    (
        payroll_period_id
    )
    REFERENCES payroll_periods(id)
    ON DELETE RESTRICT,

    FOREIGN KEY
    (
        created_by_user_id
    )
    REFERENCES users(id)
);
```

Notes are append-only in Version 0.7.

A note should not be silently edited after it is recorded.

---

## 11. Exception Resolution Table

The optional `payroll_exception_resolutions` table will track whether a detected payroll exception has been reviewed.

Proposed schema:

```sql
CREATE TABLE payroll_exception_resolutions
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    payroll_period_id INTEGER NOT NULL,

    employee_id INTEGER NULL,

    exception_key TEXT NOT NULL,

    exception_type TEXT NOT NULL,

    exception_date TEXT NULL,

    description TEXT NOT NULL,

    resolution_status TEXT NOT NULL
        DEFAULT 'open',

    resolution_note TEXT NULL,

    resolved_by_user_id INTEGER NULL,

    resolved_at DATETIME NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY
    (
        payroll_period_id
    )
    REFERENCES payroll_periods(id)
    ON DELETE RESTRICT,

    FOREIGN KEY
    (
        employee_id
    )
    REFERENCES employees(id),

    FOREIGN KEY
    (
        resolved_by_user_id
    )
    REFERENCES users(id),

    CHECK
    (
        resolution_status IN
        (
            'open',
            'resolved',
            'accepted'
        )
    ),

    UNIQUE
    (
        payroll_period_id,
        exception_key
    )
);
```

Resolution meanings:

- `open`: requires review
- `resolved`: underlying data was corrected
- `accepted`: supervisor reviewed and accepted the condition without changing punch data

Approval must be blocked while required exceptions remain `open`.

---

## 12. Exception Types

Initial exception types may include:

- Missing clock-out
- Missing clock-in
- Unmatched break
- Unmatched meal
- Invalid punch sequence
- Overlapping punch activity
- Excessively long shift
- Zero-duration shift
- Punch outside expected payroll range
- Manual punch correction requiring review
- Employee with punches but no payable hours
- Payroll calculation warning

The exception system should reuse existing punch-sequence and payroll calculation logic where possible.

The same business rule should not be implemented separately in multiple places.

---

## 13. Approval Requirements

A payroll period may be approved only when:

1. Its current status is `under_review`.
2. The period still exists.
3. The acting user is active.
4. The acting user has the `admin` or `supervisor` role.
5. The payroll period contains valid dates.
6. Payroll can be calculated successfully.
7. No required exception remains open.
8. The user explicitly confirms approval.
9. The approval action is recorded in history.
10. The complete operation commits successfully.

Approval must occur inside a database transaction.

---

## 14. Lock Requirements

A payroll period may be locked only when:

1. Its current status is `approved`.
2. An approving supervisor has been recorded.
3. An approval timestamp exists.
4. The acting user is authorized.
5. The user explicitly confirms the lock action.
6. A history record is created.
7. The transaction commits successfully.

Lock confirmation should require typed text:

```text
LOCK
```

This reduces accidental finalization.

---

## 15. Reopening Requirements

An approved or locked payroll period may be reopened only when:

- The acting user is authorized.
- A nonempty reason is provided.
- The reason meets the minimum length requirement.
- The current status permits reopening.
- Approval and lock metadata are preserved in history.
- The period returns to `under_review`.
- A reopening history record is created.
- The entire operation succeeds in one transaction.

Suggested minimum reason length:

```text
10 characters
```

Reopening should clear active approval and lock fields from the current period record while preserving all prior values in immutable history.

Fields to clear when reopened:

```text
approved_by_user_id
approved_at
locked_by_user_id
locked_at
```

The original approval and lock events remain in `payroll_period_history`.

---

## 16. Punch Correction Lock Enforcement

The existing `PunchCorrectionService` must check payroll-period protection before changing a punch.

The check must apply to:

- Creating a manual punch
- Editing an existing punch
- Deleting a punch

A correction must be blocked when its effective company-local date falls within an `approved` or `locked` payroll period.

Suggested error:

```text
This punch falls within an approved or locked payroll period. Reopen the payroll period before making corrections.
```

The check must occur in the service layer.

Browser controls alone are not sufficient.

---

## 17. Company-Timezone Handling

Payroll-period dates represent company-local dates.

Punch timestamps remain stored in UTC.

To determine whether a punch falls within a payroll period:

1. Load the company timezone.
2. Convert the punch timestamp from UTC.
3. Determine the company-local calendar date.
4. Compare that date with the payroll period’s inclusive date range.

This rule must be applied consistently to:

- Punch correction
- Payroll calculation
- Exception detection
- Report generation
- Period locking
- Period reopening

---

## 18. Authorization

Authorized roles:

```text
admin
supervisor
```

Both roles may:

- Create payroll periods
- Begin review
- Add review notes
- Resolve exceptions
- Approve payroll
- Lock payroll
- Reopen payroll

A future release may introduce finer-grained permissions.

Version 0.7 will continue using the centralized `AuthGuardService`.

Every workflow action must use the authenticated database-validated user ID.

---

## 19. Application Components

Proposed classes:

```text
app/Controllers/PayrollPeriodController.php

app/Repositories/PayrollPeriodRepository.php
app/Repositories/PayrollPeriodHistoryRepository.php
app/Repositories/PayrollReviewNoteRepository.php
app/Repositories/PayrollExceptionResolutionRepository.php

app/Services/PayrollPeriodService.php
app/Services/PayrollApprovalService.php
app/Services/PayrollExceptionService.php
app/Services/PayrollPeriodProtectionService.php
```

Views:

```text
app/Views/payroll-periods/index.twig
app/Views/payroll-periods/create.twig
app/Views/payroll-periods/show.twig
app/Views/payroll-periods/history.twig
app/Views/payroll-periods/reopen.twig
```

---

## 20. Repository Responsibilities

### PayrollPeriodRepository

Responsible for:

- Create payroll period
- Find by ID
- List periods
- Detect overlapping periods
- Update status metadata
- Find period containing a local date
- Find protected period containing a local date

### PayrollPeriodHistoryRepository

Responsible for:

- Append workflow event
- List history for a period
- Never update or delete normal history records

### PayrollReviewNoteRepository

Responsible for:

- Add note
- List notes
- Preserve append-only behavior

### PayrollExceptionResolutionRepository

Responsible for:

- Synchronize detected exceptions
- Find open exceptions
- Mark resolved
- Mark accepted
- Reopen a resolution when the exception reappears

---

## 21. Service Responsibilities

### PayrollPeriodService

Responsible for:

- Date validation
- Period-name normalization
- Overlap prevention
- Period creation
- Period retrieval
- Period listings

### PayrollApprovalService

Responsible for:

- Status-transition validation
- Beginning review
- Returning to open
- Approval
- Locking
- Reopening
- Transactions
- History creation
- Authorization validation

### PayrollExceptionService

Responsible for:

- Detecting payroll exceptions
- Creating stable exception keys
- Synchronizing exception rows
- Resolving exceptions
- Accepting reviewed exceptions
- Reporting unresolved counts

### PayrollPeriodProtectionService

Responsible for:

- Determining whether a company-local date is protected
- Blocking punch correction
- Returning the protected payroll-period details
- Providing one authoritative protection check

---

## 22. Controller Responsibilities

`PayrollPeriodController` will coordinate:

- Payroll-period list
- Period creation form
- Period detail page
- Begin-review action
- Return-to-open action
- Approve action
- Lock action
- Reopen form
- Reopen action
- Add-note action
- Resolve-exception action
- Accept-exception action
- Workflow-history page

The controller must delegate business rules to services.

It must not directly update period status through SQL.

---

## 23. Proposed Routes

```text
GET  /payroll-periods
GET  /payroll-periods/create
POST /payroll-periods/create
GET  /payroll-periods/{id}
POST /payroll-periods/{id}/begin-review
POST /payroll-periods/{id}/return-open
POST /payroll-periods/{id}/approve
POST /payroll-periods/{id}/lock
GET  /payroll-periods/{id}/reopen
POST /payroll-periods/{id}/reopen
POST /payroll-periods/{id}/notes
POST /payroll-periods/{id}/exceptions/{exceptionId}/resolve
POST /payroll-periods/{id}/exceptions/{exceptionId}/accept
GET  /payroll-periods/{id}/history
```

All payroll-period routes are protected.

Dynamic identifiers remain numeric.

---

## 24. Payroll Period List

The list page should show:

- Period name
- Start date
- End date
- Status
- Employee count
- Total payable hours
- Open exception count
- Created by
- Last updated
- Primary action

Suggested status badges:

```text
Open
Under Review
Approved
Locked
```

The page should allow filtering by:

- Status
- Year
- Date range

Version 0.7 may initially provide a simple newest-first list without advanced filtering.

---

## 25. Payroll Period Detail Page

The detail page should contain:

1. Period header
2. Status badge
3. Workflow action panel
4. Payroll totals
5. Employee payroll table
6. Exception summary
7. Review notes
8. Approval metadata
9. Lock metadata
10. Workflow history link
11. Export links

The page must clearly distinguish:

- Calculated payroll
- Review state
- Approval state
- Lock state
- Outstanding exceptions

---

## 26. Workflow Actions

### Open Period

Primary action:

```text
Begin Review
```

### Under Review Period

Actions:

```text
Approve Payroll
Return to Open
Add Review Note
Resolve Exceptions
```

### Approved Period

Actions:

```text
Lock Payroll
Reopen Payroll
Export Approved Reports
```

### Locked Period

Actions:

```text
Reopen Payroll
Export Final Reports
View Workflow History
```

Destructive or high-impact actions require explicit confirmation.

---

## 27. Approval Confirmation

Approval should display:

- Payroll period
- Start and end dates
- Employee count
- Total regular hours
- Total overtime
- Total double-time
- Total payable hours
- Open exceptions
- Approval warning

Suggested confirmation text:

```text
I confirm that I reviewed this payroll period and that it is ready for approval.
```

Approval should use a required checkbox or typed confirmation.

---

## 28. Lock Confirmation

Locking should display:

```text
Locking prevents punch corrections and marks this payroll period as final.
```

The supervisor must type:

```text
LOCK
```

The server must validate the typed value.

JavaScript confirmation alone is insufficient.

---

## 29. Reopen Form

The reopen form should display:

- Period name
- Current status
- Approval information
- Lock information
- Warning that reports may need to be regenerated
- Required reason field

The reason must be stored in immutable history.

Example reasons:

- Correct missing employee punch
- Correct approved overtime classification
- Resolve payroll exception discovered after approval
- Update employee time before final payroll submission

---

## 30. Reporting Changes

Payroll reports should include:

```text
Payroll period status
Period identifier
Approved by
Approved at
Locked by
Locked at
Open exception count
```

Approved reports should display:

```text
APPROVED
```

Locked reports should display:

```text
FINAL — LOCKED
```

Open or under-review reports should display:

```text
DRAFT
```

This status should appear consistently in:

- Web reports
- CSV exports
- PDF exports
- Email reports

---

## 31. Export Rules

Exports remain available for all statuses.

However, filenames should indicate status where practical.

Examples:

```text
payroll-2026-07-13-to-2026-07-19-draft.csv
payroll-2026-07-13-to-2026-07-19-approved.pdf
payroll-2026-07-13-to-2026-07-19-final.pdf
```

Exports must not change payroll-period state.

---

## 32. Email Rules

Email reports should include the payroll-period status.

For approved or locked reports, the email body should include:

- Approval status
- Approving supervisor
- Approval time
- Lock status when applicable
- Locking supervisor
- Lock time

Automatic scheduled reports should not automatically approve or lock payroll.

---

## 33. Transactions

The following operations must use database transactions:

- Create period and initial history
- Begin review and history
- Approve and history
- Lock and history
- Reopen and history
- Resolve exception and history
- Accept exception and history

Transaction pattern:

```text
1. Begin transaction.
2. Reload current period.
3. Validate current status.
4. Validate acting supervisor.
5. Validate exceptions when applicable.
6. Update payroll-period state.
7. Append immutable history.
8. Commit.
```

On failure:

```text
1. Roll back.
2. Preserve the original status.
3. Preserve prior history.
4. Return a clear error.
```

---

## 34. Concurrency

State transitions must verify the current database status immediately before update.

The service should not trust a status value previously loaded by the browser.

An update should include the expected current status where practical.

Example:

```sql
UPDATE payroll_periods
SET
    status = 'approved',
    approved_by_user_id = :user_id,
    approved_at = CURRENT_TIMESTAMP,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :id
  AND status = 'under_review';
```

A zero-row update indicates that the period changed concurrently or is no longer eligible.

---

## 35. Audit Logging

Workflow actions should be recorded in:

1. `payroll_period_history`
2. The existing general audit log where appropriate

General audit actions may include:

```text
payroll_period.created
payroll_period.review_started
payroll_period.approved
payroll_period.locked
payroll_period.reopened
payroll_exception.resolved
payroll_exception.accepted
```

The history table remains the authoritative payroll-period workflow record.

---

## 36. Validation Rules

### Period Name

- Required
- Trimmed
- Maximum 150 characters

### Start Date

- Required
- Valid `YYYY-MM-DD`

### End Date

- Required
- Valid `YYYY-MM-DD`
- Must be on or after start date

### Date Range

- Must not overlap another period
- Must not exceed a reasonable maximum duration

Suggested maximum:

```text
31 days
```

### Review Note

- Required when submitted
- Maximum 1,000 characters

### Reopen Reason

- Required
- Minimum 10 characters
- Maximum 1,000 characters

### Exception Resolution Note

- Required when accepting an exception without changing data
- Maximum 1,000 characters

---

## 37. Migration Plan

Version 0.7 should begin with:

```text
012_create_payroll_review_tables.php
```

The migration should create:

- `payroll_periods`
- `payroll_period_history`
- `payroll_review_notes`
- `payroll_exception_resolutions`
- Required indexes
- Required constraints

The migration must:

- Preserve all existing data
- Work on an upgraded Version 0.6 database
- Work on a fresh installation
- Pass PHP syntax validation
- Run inside the migration framework
- Appear in migration history
- Produce no foreign-key violations

---

## 38. Test Plan

Tests should cover at least:

### Payroll Period Creation

- Creates a valid period
- Rejects invalid dates
- Rejects end date before start date
- Rejects overlapping periods
- Records creation history

### Review Workflow

- Open period can enter review
- Incorrect transition is rejected
- Review history is created

### Approval

- Under-review period can be approved
- Open period cannot be approved
- Period with open exceptions cannot be approved
- Approval metadata is recorded
- Approval history is immutable

### Locking

- Approved period can be locked
- Under-review period cannot be locked
- Incorrect confirmation is rejected
- Lock metadata is recorded

### Reopening

- Approved period can be reopened with a reason
- Locked period can be reopened with a reason
- Empty reason is rejected
- Short reason is rejected
- Approval and lock fields are cleared
- Reopening history preserves the reason

### Punch Protection

- Punch creation is blocked in an approved period
- Punch update is blocked in an approved period
- Punch deletion is blocked in a locked period
- Punch correction succeeds after reopening
- Company timezone determines the protected local date

### Transactions

- Failed status transition rolls back
- Failed history insertion rolls back status update
- Failed approval preserves the prior period status

---

## 39. Implementation Sequence

Recommended implementation order:

### Phase 1

- Migration 012
- Payroll period repository
- Payroll period history repository
- Payroll period service
- Period creation tests

### Phase 2

- Payroll approval service
- Status transitions
- Transaction tests
- Workflow history tests

### Phase 3

- Payroll period controller
- Routes
- Period list
- Period detail page
- Workflow forms

### Phase 4

- Payroll exception service
- Exception resolution persistence
- Approval blocking
- Exception tests

### Phase 5

- Payroll period protection service
- Punch correction integration
- Protection tests

### Phase 6

- CSV status updates
- PDF status updates
- Email status updates
- Report reconciliation tests

### Phase 7

- Documentation
- Upgrade validation
- Production validation
- Release preparation

---

## 40. Operational Requirements

Version 0.7 changes must preserve:

- SQLite WAL mode
- Verified backups
- Safe restore
- Database health diagnostics
- Scheduler operation
- Mail diagnostics
- System Doctor
- Kiosk operation
- Nginx and PHP-FPM operation
- Local frontend assets
- Existing Version 0.6 payroll calculations
- Existing punch correction history

A new payroll workflow must not reduce Version 0.6 operational reliability.

---

## 41. Release Acceptance Criteria

Version 0.7 is ready for release when:

- Payroll periods can be created safely.
- Overlapping periods are rejected.
- Status transitions follow the approved workflow.
- Approval is blocked by unresolved exceptions.
- Approved and locked periods block punch correction.
- Reopening requires a reason.
- Workflow history is immutable.
- Reports display draft, approved, or final status.
- All migrations apply successfully.
- Database health remains clean.
- Foreign-key violations remain zero.
- All tests pass.
- Documentation is complete.
- Backup and restore validation passes.
- Production smoke testing passes.
- The working tree is clean.
- The release is tagged.

---

## 42. Version 0.7 Baseline

Version 0.7 begins from the Version 0.6 release baseline:

```text
Application version: 0.6.0
Tests: 36
Assertions: 188
Database tables: 12
Applied migrations: 11
SQLite journal mode: wal
Database integrity: ok
Foreign-key violations: 0
Scheduler checks: 9 passed
```

Version 0.7 development must preserve or improve this baseline.

---

## 43. Final Design Decision

IQwurksPunch Version 0.7 will implement payroll review as an explicit, auditable state machine.

The system will distinguish between:

```text
Calculated payroll
Reviewed payroll
Approved payroll
Final locked payroll
```

Every important transition will require authorization, validation, transaction safety, and immutable history.

This design provides a reliable foundation for future payroll export, accounting integration, and final Version 1.0 operational workflows.
