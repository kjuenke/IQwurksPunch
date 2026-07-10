# IQwurksPunch Definition of Done

Version: 1.0

---

## Purpose

The Definition of Done (DoD) defines the minimum quality standard that every sprint must satisfy before it can be considered complete.

No sprint may be closed until every applicable item has been completed.

---

# 1. Code Quality

Every modified PHP file must pass:

php -l

No syntax errors are permitted.

---

# 2. Application Integrity

The application must remain functional.

Minimum verification:

- Login
- Dashboard
- Employees
- Reports
- Settings
- Kiosk

---

# 3. Feature Verification

Every new feature must be manually tested.

Examples:

- Employee CRUD
- Scheduler
- Reports
- Email
- Console Commands

---

# 4. Regression Testing

Previously working functionality must continue working.

Regression failures block sprint completion.

---

# 5. Database Integrity

When migrations change:

- Fresh migration succeeds
- Existing migration succeeds
- No duplicate columns
- No data loss

---

# 6. Logging

New functionality must generate meaningful log entries when appropriate.

Silent failures are unacceptable.

---

# 7. Documentation

Documentation shall be updated whenever user-visible behavior changes.

Applicable documents include:

- README
- CHANGELOG
- Architecture
- Administrator Guide
- Developer Guide

---

# 8. Version Management

When applicable:

- VERSION updated
- CHANGELOG updated
- Release Notes updated

---

# 9. Version Control

Every sprint concludes with:

- Clean Git working tree
- Meaningful commit message
- Tag for release milestones

---

# 10. Product Owner Acceptance

The Product Owner must explicitly approve the sprint.

Examples:

Validated.

Looks good.

Proceed.

Without Product Owner approval the sprint remains open.

---

# 11. Professional Product Review

Before closing the sprint ask:

Would a paying customer expect this?

If the answer is no, improve the implementation.

Professional polish is considered part of the feature.

---

# Philosophy

IQwurksPunch values:

- Reliability
- Simplicity
- Maintainability
- Professional quality
- Long-term stability

Every sprint should leave the application better than it was before.
