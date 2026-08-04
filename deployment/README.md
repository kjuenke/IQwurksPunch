# IQwurksPunch Deployment Templates

These templates are based on the validated IQwurksPunch production deployment.

They are examples for administrators. They are not installed automatically and must be reviewed before use.

The templates intentionally contain placeholders instead of machine-specific addresses, usernames, paths, and timezones.

---

## Template Files

```text
deployment/
├── README.md
├── cron/
│   └── iqwurks-punch.crontab
├── kiosk/
│   └── iqwurks-kiosk-browser
├── lightdm/
│   └── 50-iqwurks-kiosk.conf
├── logrotate/
│   └── iqwurks-punch
├── nginx/
│   └── iqwurks-punch.conf
├── openbox/
│   └── autostart
├── php-fpm/
│   └── 99-iqwurks-punch.ini
└── ufw/
    └── README.md
```

---

## Placeholders

Replace every placeholder before installing a template.

| Placeholder | Purpose | Typical value |
|---|---|---|
| `{{APPLICATION_ROOT}}` | Absolute IQwurksPunch path | `/var/www/IQwurksPunch` |
| `{{PHP_FPM_SOCKET}}` | PHP-FPM Unix socket | `/run/php/php8.5-fpm.sock` |
| `{{SERVER_NAMES}}` | Nginx server names | `iqwurks iqwurks.local 192.0.2.10 _` |
| `{{TIMEZONE}}` | PHP and cron timezone | `America/Los_Angeles` |
| `{{KIOSK_USER}}` | Restricted kiosk account | `kiosk` |
| `{{KIOSK_HOME}}` | Kiosk user home directory | `/home/kiosk` |
| `{{KIOSK_URL}}` | Local employee-kiosk URL | `http://127.0.0.1/kiosk` |
| `{{CHROMIUM_BINARY}}` | Chromium executable | `/snap/bin/chromium` |
| `{{TRUSTED_SUBNET}}` | Trusted LAN subnet | `192.168.1.0/24` |

Find unresolved placeholders with:

```bash
grep -R -n '{{[A-Z_][A-Z_]*}}' deployment
```

Do not install a template while unresolved placeholders remain.

---

## Nginx Template

Template:

```text
deployment/nginx/iqwurks-punch.conf
```

Typical destination:

```text
/etc/nginx/sites-available/iqwurks-punch
```

Enable the site:

```bash
ln -s \
    /etc/nginx/sites-available/iqwurks-punch \
    /etc/nginx/sites-enabled/iqwurks-punch
```

Validate before reloading:

```bash
nginx -t
```

Reload after successful validation:

```bash
systemctl reload nginx
```

The template:

- Serves only the application’s `public` directory
- Routes requests through `public/index.php`
- Blocks arbitrary PHP execution
- Blocks hidden files
- Adds security-related headers
- Uses the configured PHP-FPM Unix socket
- Provides caching for local static assets

---

## PHP-FPM Template

Template:

```text
deployment/php-fpm/99-iqwurks-punch.ini
```

Typical destination:

```text
/etc/php/8.5/fpm/conf.d/99-iqwurks-punch.ini
```

Confirm the target PHP version before installation.

After installing the file:

```bash
systemctl restart php8.5-fpm
```

Confirm:

```bash
systemctl is-active php8.5-fpm
```

The template configures:

- Production-safe error display
- Application error logging
- Memory and execution limits
- Upload limits
- Secure session settings
- OPcache
- Application timezone

---

## LightDM Template

Template:

```text
deployment/lightdm/50-iqwurks-kiosk.conf
```

Typical destination:

```text
/etc/lightdm/lightdm.conf.d/50-iqwurks-kiosk.conf
```

The template configures:

- Automatic login for the restricted kiosk account
- Openbox as the kiosk session
- Immediate automatic login

Restarting LightDM interrupts the physical display.

Apply it only during an approved maintenance window:

```bash
systemctl restart lightdm
```

---

## Kiosk Browser Launcher

Template:

```text
deployment/kiosk/iqwurks-kiosk-browser
```

Typical destination:

```text
/usr/local/bin/iqwurks-kiosk-browser
```

Set ownership and permissions:

```bash
chown root:root \
    /usr/local/bin/iqwurks-kiosk-browser

chmod 0755 \
    /usr/local/bin/iqwurks-kiosk-browser
```

Validate Bash syntax:

```bash
bash -n \
    /usr/local/bin/iqwurks-kiosk-browser
```

The launcher:

- Waits until the kiosk URL responds
- Starts Chromium in full-screen kiosk mode
- Writes browser activity to the kiosk log
- Restarts Chromium after it exits
- Uses a configurable Chromium executable
- Uses a configurable kiosk URL

---

## Openbox Autostart

Template:

```text
deployment/openbox/autostart
```

Typical destination:

```text
{{KIOSK_HOME}}/.config/openbox/autostart
```

After replacing the placeholders, set ownership and permissions:

```bash
chown {{KIOSK_USER}}:{{KIOSK_USER}} \
    {{KIOSK_HOME}}/.config/openbox/autostart

chmod 0755 \
    {{KIOSK_HOME}}/.config/openbox/autostart
```

Validate shell syntax:

```bash
sh -n \
    {{KIOSK_HOME}}/.config/openbox/autostart
```

The template:

- Disables screen blanking
- Disables display power management
- Sets a black background
- Hides the idle pointer
- Starts the IQwurksPunch kiosk launcher

---

## Logrotate Template

Template:

```text
deployment/logrotate/iqwurks-punch
```

Typical destination:

```text
/etc/logrotate.d/iqwurks-punch
```

Validate before use:

```bash
logrotate --debug \
    /etc/logrotate.d/iqwurks-punch
```

The template rotates:

- Application logs
- Scheduler logs
- Backup logs
- Email-retry logs
- Mail logs
- PHP-FPM application logs
- Physical-kiosk browser logs

---

## Cron Template

Template:

```text
deployment/cron/iqwurks-punch.crontab
```

Review the timezone and schedules before adding the entries to root’s crontab.

The template includes:

- Payroll scheduler execution
- Failed-email retry execution
- Verified SQLite backup execution

It intentionally excludes unrelated machine-level backup jobs.

Install reviewed entries with:

```bash
crontab -e
```

Confirm:

```bash
crontab -l
```

Run the application checks after installation:

```bash
cd {{APPLICATION_ROOT}}

./iqwurks scheduler:check
./iqwurks mail:retry
./iqwurks backup:verify
```

---

## UFW Guidance

Firewall guidance is stored in:

```text
deployment/ufw/README.md
```

Always add and verify the SSH rule before enabling UFW remotely.

The guidance includes:

- Trusted-LAN SSH access
- Trusted-LAN HTTP access
- Optional monitoring access
- Firewall review commands
- UFW enablement precautions

---

## Runtime Permissions

The application source is normally controlled by:

```text
root:www-data
```

Typical source modes:

```text
Directories: 0755
Files:       0644
iqwurks:     0750
migrate.php: 0750
```

Writable runtime directories normally use owner and group:

```text
www-data:www-data
```

with mode:

```text
2770
```

Writable runtime directories are:

```text
database/sqlite
storage/backups
storage/cache
storage/exports
storage/logs
storage/sessions
```

Runtime files normally use mode:

```text
0660
```

---

## Template Validation

List all template files:

```bash
find deployment \
    -type f \
    -printf '%p\n' \
    | sort
```

Find unresolved placeholders:

```bash
grep -R -n -o \
    '{{[A-Z_][A-Z_]*}}' \
    deployment \
    | sort
```

Validate the kiosk launcher:

```bash
bash -n \
    deployment/kiosk/iqwurks-kiosk-browser
```

Validate Openbox autostart:

```bash
sh -n \
    deployment/openbox/autostart
```

Check for trailing whitespace:

```bash
grep -R -n '[[:blank:]]$' deployment
```

Check for machine-specific values:

```bash
grep -R -n -E \
    '192\.168\.1\.82|192\.168\.1\.234|192\.168\.1\.254|d4:be:d9:ce:ee:0c' \
    deployment
```

The machine-specific search should return no output.

---

## Security Notes

Before applying the templates:

- Verify every placeholder.
- Confirm the PHP-FPM socket.
- Confirm the trusted subnet.
- Confirm the application path.
- Confirm the kiosk account has no `sudo` access.
- Confirm Nginx serves only the `public` directory.
- Confirm arbitrary PHP execution is blocked.
- Confirm hidden files are blocked.
- Confirm SMTP secrets remain only in `config/mail.php`.
- Confirm `config/mail.php` is not committed or packaged.
- Confirm HTTP access is restricted to a trusted LAN.
- Add HTTPS before untrusted-network exposure.

These templates contain no:

- SMTP passwords
- Supervisor passwords
- Employee PINs
- Password hashes
- SQLite databases
- SQLite sidecar files
- Runtime backups
- Runtime logs
- Runtime sessions
- Machine inventory

---

## Active-System Safety

The files under `deployment/` are documentation templates only.

Creating or editing them does not modify:

- `/etc/nginx`
- `/etc/php`
- `/etc/lightdm`
- `/etc/logrotate.d`
- `/usr/local/bin`
- `/home/kiosk`
- Root’s crontab
- UFW rules

An administrator must explicitly review, customize, install, and activate each template.
