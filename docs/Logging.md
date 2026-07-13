# Logging

IQwurksPunch uses a component-based file logging system for application diagnostics and production monitoring.

## Architecture

The logging system consists of:

- `LoggerInterface`
- `Logger`
- `LogHandlerInterface`
- `FileLogHandler`
- `LogLevel`
- `LoggerFactory`
- `config/logging.php`

Loggers are created with a component channel:

```php
$logger = LoggerFactory::create('scheduler');
