# IQwurksPunch Changelog

All notable changes to IQwurksPunch will be documented in this file.

The format loosely follows the principles of Keep a Changelog and Semantic Versioning during stable releases.

---

## [0.3.0-dev] - In Development

### Added

- Added centralized logging configuration through `config/logging.php`.
- Added `LoggerFactory` for creating component-specific loggers.
- Added structured scheduler lifecycle and diagnostic logging.
- Added scheduler exception, duration, process, timezone, and memory metadata.
- Added separate raw cron-output logging through `cron-scheduler.log`.
- Added operational logging documentation.
- Console application framework
- Console command architecture
- Automatic report scheduler
- Cron integration
- Company-configurable scheduler
- Company timezone support
- Automatic payroll email delivery
- Email history
- Email logging
- Report schedule management

### Changed

- Updated the scheduled-report command to record disabled, not-due, successful, failed, and exceptional execution paths.
- Separated structured scheduler logs from cron command output.

### Planned

- Logging framework
- Exception handler
- Health monitoring
- Doctor command
- Backup system
- Dashboard health indicators
- Release management
- Documentation

---

## [0.2.0-alpha]

### Added

- Company Settings
- Manual payroll email reports
- Automatic report scheduling
- Email audit history
- Company branding
- Timezone-aware reporting
- Migration improvements

---

## [0.1.0]

### Added

- MVC framework
- Authentication
- Employee management
- Employee PIN management
- Kiosk interface
- Punch tracking
- Payroll reporting
- SQLite database
- Repository pattern
- Service layer
