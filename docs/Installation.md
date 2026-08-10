# IQwurksPunch Installation and Upgrade Guide

**Application:** IQwurksPunch
**Guide baseline:** Version 1.0
**Audience:** Linux administrators, deployment operators, and release maintainers

---

## 1. Purpose

IQwurksPunch is a self-hosted employee time-clock and payroll-preparation system designed for a dedicated Linux kiosk.

This guide covers:

- Verifying a distribution archive
- Performing a fresh installation
- Installing Composer dependencies
- Initializing the SQLite database
- Configuring Nginx and PHP-FPM
- Configuring SMTP
- Installing scheduled reports, retries, and backups
- Configuring log rotation and firewall access
- Configuring a physical Chromium kiosk
- Performing controlled upgrades
- Assessing interrupted upgrades
- Recovering from failed upgrades
- Building and verifying release packages

The application does not currently install operating-system services automatically. Nginx, PHP-FPM, cron, logrotate, firewall, LightDM, Openbox, and Chromium configuration remain administrator-controlled.

---

## 2. Supported Deployment Model

The validated production model uses:

- Ubuntu Linux
- PHP 8.5 or newer
- PHP-FPM
- SQLite
- Composer
- Nginx
- Cron
- GNU `tar`
- `flock`
- Logrotate
- UFW or another firewall
- LightDM, Openbox, and Chromium for the physical kiosk

The validated application path is:

```text
/var/www/IQwurksPunch
```

The validated web routes are:

```text
Employee kiosk:       http://SERVER_ADDRESS/kiosk
Supervisor login:     http://SERVER_ADDRESS/login
Operations dashboard: http://SERVER_ADDRESS/dashboard
Initial setup:        http://SERVER_ADDRESS/setup
```

The physical kiosk normally opens:

```text
http://127.0.0.1/kiosk
```

---

## 3. Distribution Package Contents

A release archive is named:

```text
iqwurkspunch-VERSION.tar.gz
```

The archive contains one versioned root directory:

```text
iqwurkspunch-VERSION/
```

A package includes:

- Application source
- Database migrations
- Configuration examples
- Documentation
- Automated tests
- Composer metadata
- Empty runtime directories
- `PACKAGE-MANIFEST.json`
- `VERSION`
- `iqwurks`
- `migrate.php`

A package intentionally excludes:

- `vendor/`
- `.git/`
- `.env`
- `config/mail.php`
- Runtime SQLite databases
- SQLite WAL and SHM files
- Runtime logs
- Runtime backups
- Runtime exports
- Runtime cache files
- Runtime session files
- Machine inventory files
- `firewall-rules.txt`
- `installed-packages.txt`

Composer dependencies are installed after extraction.

---

## 4. Package Security Model

Every official package should be accompanied by a published SHA-256 digest.

The archive also contains:

```text
PACKAGE-MANIFEST.json
```

The manifest records:

- Application version
- Expected package filename
- Expected package root
- Every packaged file
- Every packaged directory
- File SHA-256 digests
- Packaged file modes
- Packaged directory modes

The `package:verify` command independently checks:

- Archive SHA-256 when supplied
- Gzip and TAR readability
- One versioned top-level root
- Safe archive paths
- Manifest schema
- Package identity
- Application version
- Every manifest-listed file
- Every manifest-listed directory
- Every file SHA-256 digest
- File permissions
- Directory permissions
- Required installation files
- Required empty runtime directories
- Unexpected archive entries
- Symbolic links
- Forbidden files
- Runtime data leakage
- Temporary verification cleanup

Package verification is read-only.

---

# Part I — Fresh Installation

## 5. Prepare the Server

Update the operating system:

```bash
apt update
apt full-upgrade -y
```

Install the production components appropriate for the target Ubuntu release:

```bash
apt install -y \
    nginx \
    sqlite3 \
    composer \
    cron \
    curl \
    unzip \
    git \
    logrotate \
    ufw
```

Install PHP 8.5 or newer with:

- PHP CLI
- PHP-FPM
- SQLite support
- XML support
- DOM support
- XMLWriter support
- JSON support
- Filter support
- Hash support
- Iconv support
- Tokenizer support
- Phar support

Package names vary by Ubuntu release and repository.

Confirm PHP:

```bash
php --version
```

Confirm PHP-FPM:

```bash
php-fpm8.5 --version
```

Confirm SQLite:

```bash
sqlite3 --version
```

Confirm Composer:

```bash
composer --version
```

Confirm GNU TAR:

```bash
tar --version
```

Confirm required system commands:

```bash
command -v php
command -v composer
command -v sqlite3
command -v curl
command -v unzip
command -v nginx
command -v cron
command -v flock
command -v logrotate
command -v tar
```

---

## 6. Obtain the Release Archive

Place the release archive in a temporary installation location.

Example:

```text
/root/iqwurkspunch-1.0.0.tar.gz
```

Set shell variables:

```bash
ARCHIVE="/root/iqwurkspunch-1.0.0.tar.gz"
EXPECTED_SHA256="PUBLISHED_64_CHARACTER_SHA256"
```

Replace both values with the actual release information.

---

## 7. Verify the Published Archive Digest

Before extraction:

```bash
sha256sum "$ARCHIVE"
```

Compare the output with the published release checksum.

An exact command-line comparison can be performed with:

```bash
test "$(
    sha256sum "$ARCHIVE" | awk '{print $1}'
)" = "$EXPECTED_SHA256" \
    && echo 'Archive checksum matches.' \
    || echo 'ERROR: Archive checksum does not match.'
```

Do not install an archive whose checksum does not match.

Test gzip integrity:

```bash
gzip -t "$ARCHIVE"
```

Test the TAR listing:

```bash
tar -tzf "$ARCHIVE" >/dev/null
```

Confirm the archive contains one versioned root:

```bash
tar -tzf "$ARCHIVE" \
    | cut -d/ -f1 \
    | sort -u
```

---

## 8. Extract the Application

Inspect the archive root name:

```bash
tar -tzf "$ARCHIVE" \
    | head -n 1
```

Extract under `/var/www`:

```bash
tar -xzf "$ARCHIVE" \
    --directory /var/www \
    --no-same-owner \
    --same-permissions
```

Rename the versioned directory to the production path.

Example:

```bash
mv \
    /var/www/iqwurkspunch-1.0.0 \
    /var/www/IQwurksPunch
```

Enter the project:

```bash
cd /var/www/IQwurksPunch
```

Confirm the package version:

```bash
cat VERSION
```

---

## 9. Install Composer Dependencies

The release archive does not contain `vendor/`.

Install the locked production dependencies:

```bash
cd /var/www/IQwurksPunch

COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader
```

For a development or release-validation system, omit `--no-dev`:

```bash
COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --prefer-dist \
    --no-interaction
```

Normalize dependency read access after either Composer installation command.
Some dependency archives preserve restrictive source-file modes that prevent
PHP-FPM from reading required runtime resources:

```bash
chmod -R o+rX vendor
```

Confirm that no dependency file is unreadable by the web process:

```bash
find vendor \
    -type f \
    ! -perm -004 \
    -print
```

The verification command should produce no output.

Validate Composer metadata:

```bash
composer validate \
    --no-check-publish \
    --no-interaction
```

Validate installed platform requirements:

```bash
composer check-platform-reqs
```

Confirm autoloading:

```bash
test -r vendor/autoload.php \
    && echo 'Composer autoloader is available.' \
    || echo 'ERROR: Composer autoloader is missing.'
```

---

## 10. Independently Verify the Extracted Release Archive

After Composer dependencies are installed, use the application’s verifier against the original archive:

```bash
cd /var/www/IQwurksPunch

./iqwurks package:verify \
    --archive="$ARCHIVE" \
    --sha256="$EXPECTED_SHA256"
```

A successful result includes:

```text
Mode: VERIFY
Status: PASS
Successful: yes
Checksum matches: yes
Verification failures: 0
Temporary workspace removed: yes
Changes made: no
```

Do not proceed when package verification fails.

---

## 11. Set Source Ownership and Permissions

Set the source tree to a controlled owner and web-service group:

```bash
cd /var/www

chown -R root:www-data IQwurksPunch
```

Set normal source directories:

```bash
find /var/www/IQwurksPunch \
    -type d \
    -exec chmod 0755 {} +
```

Set normal source files:

```bash
find /var/www/IQwurksPunch \
    -type f \
    -exec chmod 0644 {} +
```

Restore executable entry points:

```bash
chmod 0750 \
    /var/www/IQwurksPunch/iqwurks \
    /var/www/IQwurksPunch/migrate.php
```

---

## 12. Configure Writable Runtime Directories

The web application and console operations require writable runtime paths.

Create and normalize them:

```bash
install \
    -d \
    -o www-data \
    -g www-data \
    -m 2770 \
    /var/www/IQwurksPunch/database/sqlite \
    /var/www/IQwurksPunch/storage/backups \
    /var/www/IQwurksPunch/storage/cache \
    /var/www/IQwurksPunch/storage/exports \
    /var/www/IQwurksPunch/storage/logs \
    /var/www/IQwurksPunch/storage/sessions
```

Normalize any existing runtime contents:

```bash
chown -R www-data:www-data \
    /var/www/IQwurksPunch/database/sqlite \
    /var/www/IQwurksPunch/storage/backups \
    /var/www/IQwurksPunch/storage/cache \
    /var/www/IQwurksPunch/storage/exports \
    /var/www/IQwurksPunch/storage/logs \
    /var/www/IQwurksPunch/storage/sessions
```

Set runtime directory modes:

```bash
find \
    /var/www/IQwurksPunch/database/sqlite \
    /var/www/IQwurksPunch/storage/backups \
    /var/www/IQwurksPunch/storage/cache \
    /var/www/IQwurksPunch/storage/exports \
    /var/www/IQwurksPunch/storage/logs \
    /var/www/IQwurksPunch/storage/sessions \
    -type d \
    -exec chmod 2770 {} +
```

Set runtime file modes:

```bash
find \
    /var/www/IQwurksPunch/database/sqlite \
    /var/www/IQwurksPunch/storage/backups \
    /var/www/IQwurksPunch/storage/cache \
    /var/www/IQwurksPunch/storage/exports \
    /var/www/IQwurksPunch/storage/logs \
    /var/www/IQwurksPunch/storage/sessions \
    -type f \
    -exec chmod 0660 {} +
```

---

## 13. Run Installation Preflight

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks install:check
```

The preflight checks:

1. Operating system
2. PHP version
3. PHP extensions
4. System commands
5. Application source
6. Composer dependencies
7. Runtime directories
8. Disk capacity

A healthy system reports:

```text
Overall status: PASS
Failures: 0
```

Correct every failure before database initialization.

---

## 14. Configure SMTP

Copy the example configuration:

```bash
cd /var/www/IQwurksPunch

cp \
    config/mail.example.php \
    config/mail.php
```

Edit:

```bash
nano config/mail.php
```

Configure:

- SMTP hostname
- SMTP port
- SMTP username
- SMTP password
- Encryption
- Sender email
- Sender name

Protect the file:

```bash
chown root:www-data config/mail.php
chmod 0640 config/mail.php
```

Confirm Git ignores it:

```bash
git check-ignore -v config/mail.php 2>/dev/null \
    || true
```

The SMTP configuration contains only transport and sender information.

Payroll recipients are managed later through the IQwurksPunch Notification Center.

---

## 15. Initialize the Database

Review migration status:

```bash
cd /var/www/IQwurksPunch

php migrate.php status
```

Apply migrations:

```bash
php migrate.php migrate
```

Review the final status:

```bash
php migrate.php status
```

All migrations should display:

```text
[OK]
```

Confirm the database was created:

```bash
ls -lh database/sqlite
```

Restore runtime ownership after migration:

```bash
chown -R www-data:www-data database/sqlite
find database/sqlite -type d -exec chmod 2770 {} +
find database/sqlite -type f -exec chmod 0660 {} +
```

---

## 16. Validate Database Health

Run:

```bash
./iqwurks database:check
```

Expected essentials:

```text
Integrity: ok
Foreign-key violations: 0
Journal mode: wal
```

IQwurksPunch configures SQLite with:

```sql
PRAGMA foreign_keys = ON;
PRAGMA busy_timeout = 10000;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA wal_autocheckpoint = 1000;
```

Do not continue when database integrity fails or foreign-key violations are present.

---

## 17. Configure PHP-FPM

Confirm the PHP-FPM socket:

```bash
find /run/php \
    -maxdepth 1 \
    -type s \
    -name 'php*-fpm.sock' \
    -print
```

The expected PHP 8.5 socket is commonly:

```text
/run/php/php8.5-fpm.sock
```

Enable and start PHP-FPM:

```bash
systemctl enable --now php8.5-fpm
```

Confirm:

```bash
systemctl is-active php8.5-fpm
systemctl is-enabled php8.5-fpm
```

---

## 18. Configure Nginx

Create:

```bash
nano /etc/nginx/sites-available/iqwurks-punch
```

Use:

```nginx
server {
    listen 80;
    listen [::]:80;

    server_name _;

    root /var/www/IQwurksPunch/public;
    index index.php;

    client_max_body_size 12m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;

        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $document_root;

        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }

    location ^~ /assets/ {
        try_files $uri =404;

        expires 7d;

        add_header Cache-Control "public";
    }

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    server_tokens off;
}
```

Enable the site:

```bash
ln -s \
    /etc/nginx/sites-available/iqwurks-punch \
    /etc/nginx/sites-enabled/iqwurks-punch
```

Remove the default site when it is not needed:

```bash
rm -f /etc/nginx/sites-enabled/default
```

Validate:

```bash
nginx -t
```

Enable and reload Nginx:

```bash
systemctl enable --now nginx
systemctl reload nginx
```

Confirm:

```bash
systemctl is-active nginx
systemctl is-enabled nginx
```

---

## 19. Complete Initial Application Setup

Open:

```text
http://SERVER_ADDRESS/setup
```

Create the initial administrator account.

Then open:

```text
http://SERVER_ADDRESS/login
```

Log in and configure:

- Company name
- Company address
- Company timezone
- Kiosk inactivity timeout
- Payroll rounding
- Meal deduction
- Paid breaks
- Labor rules
- Notification recipients
- Report delivery schedules

The setup route should no longer permit initial account creation after an administrator exists.

---

## 20. Validate Application Routes

Test the kiosk:

```bash
curl \
    --silent \
    --output /dev/null \
    --write-out 'Kiosk HTTP %{http_code}\n' \
    http://127.0.0.1/kiosk
```

Test the login page:

```bash
curl \
    --silent \
    --output /dev/null \
    --write-out 'Login HTTP %{http_code}\n' \
    http://127.0.0.1/login
```

Test the dashboard redirect or response:

```bash
curl \
    --silent \
    --output /dev/null \
    --write-out 'Dashboard HTTP %{http_code}\n' \
    http://127.0.0.1/dashboard
```

---

## 21. Verify Local Frontend Assets

Run:

```bash
cd /var/www/IQwurksPunch

sha256sum --check \
    public/assets/vendor/SHA256SUMS
```

Test Bootstrap assets:

```bash
curl --head \
    http://127.0.0.1/assets/vendor/bootstrap/5.3.7/css/bootstrap.min.css
```

```bash
curl --head \
    http://127.0.0.1/assets/vendor/bootstrap/5.3.7/js/bootstrap.bundle.min.js
```

Test Bootstrap Icons:

```bash
curl --head \
    http://127.0.0.1/assets/vendor/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css
```

Each asset should return HTTP 200.

Confirm the kiosk does not reference the old primary CDN:

```bash
curl --silent http://127.0.0.1/kiosk \
    | grep -i 'jsdelivr' \
    && echo 'FAIL: External jsDelivr reference found.' \
    || echo 'PASS: Primary frontend assets are local.'
```

---

## 22. Configure Scheduled Reports

Edit root’s crontab:

```bash
crontab -e
```

Add:

```cron
* * * * * cd /var/www/IQwurksPunch && /usr/bin/flock -n /tmp/iqwurks-scheduler.lock /usr/bin/php iqwurks schedule:run >> storage/logs/cron-scheduler.log 2>&1
```

Confirm:

```bash
crontab -l
```

Run manually:

```bash
cd /var/www/IQwurksPunch

./iqwurks schedule:run
```

A normal result may be:

```text
No scheduled report is due.
```

Run scheduler diagnostics:

```bash
./iqwurks scheduler:check
```

---

## 23. Configure Automatic Email Retries

Version 0.8 and later support retrying eligible failed payroll-report deliveries.

A typical five-minute retry schedule is:

```cron
*/5 * * * * cd /var/www/IQwurksPunch && /usr/bin/flock -n /tmp/iqwurks-mail-retry.lock /usr/bin/php iqwurks mail:retry --send >> storage/logs/cron-mail-retry.log 2>&1
```

Preview eligible retry work manually:

```bash
./iqwurks mail:retry
```

Run eligible retries manually:

```bash
./iqwurks mail:retry --send
```

The retry policy enforces:

- Attempt limits
- Retry delays
- Batch limits
- Duplicate-child prevention
- Permanent-failure exclusions
- Unsupported-type quarantine
- Malformed-record quarantine

The internal application lock and the external cron lock both protect send-mode retry execution.

---

## 24. Configure Automatic Backups

Add a verified daily backup schedule to root’s crontab:

```cron
# BEGIN IQWURKSPUNCH BACKUP
CRON_TZ=America/Los_Angeles
15 1 * * * cd /var/www/IQwurksPunch && /usr/bin/flock -n /tmp/iqwurks-backup-cron.lock /usr/bin/php iqwurks backup:run >> storage/logs/cron-backup.log 2>&1
# END IQWURKSPUNCH BACKUP
```

Change the timezone and schedule for the deployment.

Test:

```bash
cd /var/www/IQwurksPunch

./iqwurks backup:run
./iqwurks backup:list
./iqwurks backup:verify
```

A backup should not be considered usable until verification passes.

---

## 25. Configure Log Rotation

Create:

```bash
nano /etc/logrotate.d/iqwurks-punch
```

Use:

```text
/var/www/IQwurksPunch/storage/logs/*.log {
    daily
    rotate 30
    maxsize 5M
    missingok
    notifempty
    compress
    delaycompress
    dateext
    dateformat -%Y%m%d-%H%M%S
    create 0660 www-data www-data
    su www-data www-data
}

/home/kiosk/.local/state/iqwurks-kiosk/browser.log {
    daily
    rotate 14
    maxsize 5M
    missingok
    notifempty
    compress
    delaycompress
    dateext
    dateformat -%Y%m%d-%H%M%S
    copytruncate
    su kiosk kiosk
}
```

Validate:

```bash
logrotate --debug \
    /etc/logrotate.d/iqwurks-punch
```

Confirm the timer:

```bash
systemctl is-enabled logrotate.timer
systemctl is-active logrotate.timer
```

---

## 26. Configure the Firewall

Permit access only from the trusted network.

Replace:

```text
192.168.1.0/24
```

with the correct trusted subnet.

Allow SSH first:

```bash
ufw allow from 192.168.1.0/24 \
    to any port 22 \
    proto tcp \
    comment 'LAN SSH'
```

Allow IQwurksPunch:

```bash
ufw allow from 192.168.1.0/24 \
    to any port 80 \
    proto tcp \
    comment 'LAN IQwurksPunch'
```

Enable UFW only after SSH access has been allowed:

```bash
ufw enable
```

Review:

```bash
ufw status numbered
```

The validated deployment uses HTTP only on a trusted LAN.

Add HTTPS before exposing IQwurksPunch to an untrusted network.

---

## 27. Configure a Stable Network Address

Use either:

- A DHCP reservation
- A correctly configured static address

Record:

- Hostname
- IP address
- Network interface
- MAC address
- Gateway
- DNS server
- Trusted subnet

Confirm:

```bash
ip address show
ip route
hostname -I
```

---

# Part II — Physical Kiosk

## 28. Install Kiosk Components

Install:

```bash
apt install -y \
    xorg \
    openbox \
    lightdm \
    lightdm-gtk-greeter \
    unclutter
```

Install Chromium using the supported Ubuntu package or snap.

Example:

```bash
snap install chromium
```

Confirm:

```bash
/snap/bin/chromium --version
```

---

## 29. Create the Restricted Kiosk User

Create:

```bash
adduser \
    --disabled-password \
    --gecos '' \
    kiosk
```

The account must not have `sudo` access.

Create configuration directories:

```bash
install \
    -d \
    -o kiosk \
    -g kiosk \
    -m 0700 \
    /home/kiosk/.config/openbox
```

```bash
install \
    -d \
    -o kiosk \
    -g kiosk \
    -m 0700 \
    /home/kiosk/.local/state/iqwurks-kiosk
```

---

## 30. Configure LightDM Automatic Login

Create:

```bash
nano /etc/lightdm/lightdm.conf.d/50-iqwurks-kiosk.conf
```

Use:

```ini
[Seat:*]
autologin-user=kiosk
autologin-user-timeout=0
user-session=openbox
autologin-session=openbox
greeter-session=lightdm-gtk-greeter
```

Enable LightDM:

```bash
systemctl enable lightdm
```

---

## 31. Create the Kiosk Browser Launcher

Create:

```bash
nano /usr/local/bin/iqwurks-kiosk-browser
```

Use:

```bash
#!/usr/bin/env bash
set -u

readonly KIOSK_URL="http://127.0.0.1/kiosk"
readonly LOG_DIRECTORY="${HOME}/.local/state/iqwurks-kiosk"
readonly LOG_FILE="${LOG_DIRECTORY}/browser.log"

mkdir -p "${LOG_DIRECTORY}"
chmod 700 "${LOG_DIRECTORY}"

while true
do
    until /usr/bin/curl \
        --fail \
        --silent \
        --show-error \
        --max-time 3 \
        "${KIOSK_URL}" \
        >/dev/null
    do
        printf \
            '[%s] Waiting for IQwurksPunch at %s\n' \
            "$(/usr/bin/date --iso-8601=seconds)" \
            "${KIOSK_URL}" \
            >> "${LOG_FILE}"

        /usr/bin/sleep 2
    done

    printf \
        '[%s] Starting Chromium kiosk browser.\n' \
        "$(/usr/bin/date --iso-8601=seconds)" \
        >> "${LOG_FILE}"

    /snap/bin/chromium \
        --kiosk \
        --incognito \
        --no-first-run \
        --no-default-browser-check \
        --noerrdialogs \
        --disable-session-crashed-bubble \
        --disable-translate \
        --disable-features=Translate,TranslateUI \
        --password-store=basic \
        --disable-pinch \
        --overscroll-history-navigation=0 \
        "${KIOSK_URL}" \
        >> "${LOG_FILE}" \
        2>&1

    exitCode=$?

    printf \
        '[%s] Chromium exited with code %s. Restarting in two seconds.\n' \
        "$(/usr/bin/date --iso-8601=seconds)" \
        "${exitCode}" \
        >> "${LOG_FILE}"

    /usr/bin/sleep 2
done
```

Protect and validate:

```bash
chown root:root \
    /usr/local/bin/iqwurks-kiosk-browser

chmod 0755 \
    /usr/local/bin/iqwurks-kiosk-browser

bash -n \
    /usr/local/bin/iqwurks-kiosk-browser
```

---

## 32. Configure Openbox Autostart

Create:

```bash
nano /home/kiosk/.config/openbox/autostart
```

Use:

```sh
#!/bin/sh

/usr/bin/xset s off
/usr/bin/xset s noblank
/usr/bin/xset -dpms

/usr/bin/xsetroot -solid black

/usr/bin/unclutter \
    -idle 1 \
    -root &

/usr/local/bin/iqwurks-kiosk-browser &
```

Set ownership and permissions:

```bash
chown kiosk:kiosk \
    /home/kiosk/.config/openbox/autostart

chmod 0755 \
    /home/kiosk/.config/openbox/autostart
```

Validate:

```bash
sh -n \
    /home/kiosk/.config/openbox/autostart
```

---

## 33. Disable Sleep and Display Interruption

Mask sleep targets:

```bash
systemctl mask \
    sleep.target \
    suspend.target \
    hibernate.target \
    hybrid-sleep.target
```

Confirm:

```bash
systemctl status \
    sleep.target \
    suspend.target \
    hibernate.target \
    hybrid-sleep.target
```

---

## 34. Start and Test the Kiosk

Restart LightDM during an approved maintenance window:

```bash
systemctl restart lightdm
```

The physical screen should:

1. Log in automatically as `kiosk`.
2. Start Openbox.
3. Wait for IQwurksPunch.
4. Start Chromium.
5. Display the employee kiosk full-screen.

Review the browser log:

```bash
tail -n 100 \
    /home/kiosk/.local/state/iqwurks-kiosk/browser.log
```

Test automatic browser recovery:

```bash
pkill -u kiosk chromium
```

Chromium should restart after approximately two seconds.

---

# Part III — Installation Validation

## 35. Run Mail Diagnostics

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks mail:check
```

Review:

- SMTP configuration
- DNS resolution
- TCP connectivity
- TLS readiness
- Recent delivery status

Send a manual test or payroll report after diagnostics pass.

---

## 36. Run the Complete Diagnostic Suite

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks install:check
./iqwurks database:check
./iqwurks scheduler:check
./iqwurks doctor
```

Required results:

- No installation-preflight failures
- Database integrity `ok`
- Zero foreign-key violations
- Scheduler diagnostics pass
- System Doctor has no failures

A recent mail-activity warning may be acceptable immediately after log rotation when SMTP connectivity and historical delivery are otherwise confirmed.

---

## 37. Run Automated Tests

For development or release validation:

```bash
cd /var/www/IQwurksPunch

php vendor/bin/phpunit
```

The Version 1.0 development baseline at the time this guide was updated is:

```text
359 tests
2734 assertions
```

The release baseline may increase as Version 1.0 is completed.

Do not approve a release when automated tests fail.

---

## 38. Create the First Verified Backup

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks backup:create
./iqwurks backup:list
./iqwurks backup:verify
```

Record the newest verified backup filename.

Preview a restore:

```bash
./iqwurks backup:restore BACKUP_FILENAME
```

Do not apply a restore unless a controlled restore or recovery procedure is intended.

---

## 39. Test Maintenance Mode

Enable:

```bash
./iqwurks maintenance:on Installation validation
```

Check:

```bash
./iqwurks maintenance:status
```

Disable:

```bash
./iqwurks maintenance:off
```

Confirm:

```bash
./iqwurks maintenance:status
```

The console does not implement universal per-command `--help`.

Do not run:

```bash
./iqwurks maintenance:on --help
```

That command would treat `--help` as the maintenance reason.

---

## 40. Perform a Reboot-Recovery Test

Reboot:

```bash
reboot
```

After reboot, confirm:

```bash
systemctl is-active nginx
systemctl is-active php8.5-fpm
systemctl is-active cron
systemctl is-active lightdm
```

Confirm enablement:

```bash
systemctl is-enabled nginx
systemctl is-enabled php8.5-fpm
systemctl is-enabled cron
systemctl is-enabled lightdm
```

Validate web routes:

```bash
curl \
    --silent \
    --output /dev/null \
    --write-out 'Kiosk HTTP %{http_code}\n' \
    http://127.0.0.1/kiosk
```

```bash
curl \
    --silent \
    --output /dev/null \
    --write-out 'Login HTTP %{http_code}\n' \
    http://127.0.0.1/login
```

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks database:check
./iqwurks scheduler:check
./iqwurks doctor
```

Confirm:

- Physical kiosk starts automatically
- Chromium is full-screen
- Employee-number screen is visible
- LAN kiosk access works
- Supervisor login works
- Scheduler cron remains installed
- Retry cron remains installed
- Backup cron remains installed
- Firewall remains active

---

# Part IV — Controlled Upgrade

## 41. Upgrade Safety Principles

A safe upgrade requires:

- A verified release archive
- A verified archive checksum
- A recent verified database backup
- Sufficient disk capacity
- Healthy migration history
- Healthy database integrity
- Maintenance-mode protection
- Matching application and database rollback materials
- A tested recovery plan

Never restore only an old database while leaving incompatible newer application files in place.

Never restore only old application files while leaving an incompatible newer database in place.

Application source and database state must remain compatible.

---

## 42. Verify the New Release Before Deployment

Set:

```bash
NEW_ARCHIVE="/root/iqwurkspunch-NEW_VERSION.tar.gz"
NEW_SHA256="PUBLISHED_64_CHARACTER_SHA256"
```

Verify:

```bash
sha256sum "$NEW_ARCHIVE"
```

Test:

```bash
gzip -t "$NEW_ARCHIVE"
tar -tzf "$NEW_ARCHIVE" >/dev/null
```

Extract the package to a temporary release-validation directory:

```bash
VALIDATION_ROOT="$(mktemp -d /tmp/iqwurks-release-validation.XXXXXXXX)"

tar -xzf "$NEW_ARCHIVE" \
    --directory "$VALIDATION_ROOT" \
    --no-same-owner \
    --same-permissions
```

Enter the extracted package root and install dependencies:

```bash
cd "$VALIDATION_ROOT"/iqwurkspunch-NEW_VERSION

COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader
```

Verify the archive through the new release code:

```bash
./iqwurks package:verify \
    --archive="$NEW_ARCHIVE" \
    --sha256="$NEW_SHA256"
```

Remove the validation workspace only after verification succeeds:

```bash
rm -rf "$VALIDATION_ROOT"
```

---

## 43. Run Upgrade Readiness Checks

From the current production installation:

```bash
cd /var/www/IQwurksPunch

./iqwurks upgrade:check
```

The readiness check examines:

1. Application version
2. Composer state
3. Upgrade paths
4. Database health
5. Migration history
6. Verified backup
7. Maintenance mode
8. Disk capacity

A warning that maintenance mode is inactive is expected before an upgrade begins.

A failure must be corrected before deployment.

---

## 44. Review the Upgrade Plan

Run:

```bash
./iqwurks upgrade:plan
```

The plan describes:

1. Pre-upgrade validation
2. Maintenance protection
3. Safety backup
4. Composer dependencies
5. Migrations
6. Ownership and permissions
7. Post-upgrade validation
8. Return to service
9. Rollback reference

The plan is read-only.

---

## 45. Preview Controlled Upgrade Execution

Run:

```bash
./iqwurks upgrade:preview
```

The preview reports:

- Whether the upgrade can be applied
- Planned process commands
- Pending migrations
- Maintenance expectations
- Backup expectations
- Validation stages
- Rollback references
- Exact confirmation phrase

The preview changes nothing.

Record the exact confirmation phrase.

Example:

```text
UPGRADE 1.0.0
```

Use the phrase displayed by the command, not the example.

---

## 46. Preserve Local Configuration and Runtime State

Before replacing application source, preserve:

```text
config/mail.php
database/sqlite/
storage/backups/
storage/cache/
storage/exports/
storage/logs/
storage/sessions/
```

Also preserve system-level configuration:

```text
/etc/nginx/sites-available/iqwurks-punch
/etc/php/8.5/fpm/
 /etc/lightdm/lightdm.conf.d/50-iqwurks-kiosk.conf
/usr/local/bin/iqwurks-kiosk-browser
/home/kiosk/.config/openbox/autostart
/etc/logrotate.d/iqwurks-punch
root crontab
UFW rules
```

Do not copy packaged empty runtime directories over live runtime data.

Do not overwrite `config/mail.php` with `config/mail.example.php`.

---

## 47. Deploy the New Application Source

Stop at this point unless:

- Package verification passed
- Upgrade readiness has no failures
- A recent verified backup exists
- The exact upgrade confirmation phrase has been recorded
- A rollback copy of the current release is available

Deploy the release application files while preserving:

- Local SMTP configuration
- Active SQLite database and sidecars
- Backups
- Logs
- Exports
- Sessions
- Cache state required for recovery
- Upgrade journals

The controlled upgrade command does not download or copy a release archive. Release files must already be deployed to the intended application tree.

After deploying the source files, restore executable modes:

```bash
chmod 0750 \
    /var/www/IQwurksPunch/iqwurks \
    /var/www/IQwurksPunch/migrate.php
```

Restore runtime ownership:

```bash
chown -R www-data:www-data \
    /var/www/IQwurksPunch/database/sqlite \
    /var/www/IQwurksPunch/storage/backups \
    /var/www/IQwurksPunch/storage/cache \
    /var/www/IQwurksPunch/storage/exports \
    /var/www/IQwurksPunch/storage/logs \
    /var/www/IQwurksPunch/storage/sessions
```

---

## 48. Apply the Controlled Upgrade

Run the preview again from the deployed release:

```bash
cd /var/www/IQwurksPunch

./iqwurks upgrade:preview
```

Use the exact phrase shown by the preview:

```bash
./iqwurks upgrade:apply \
    --confirm="EXACT_PHRASE_FROM_PREVIEW"
```

The controlled upgrade engine:

- Requires exact confirmation
- Uses an exclusive upgrade lock
- Activates maintenance mode when needed
- Creates and verifies a pre-upgrade backup
- Installs locked Composer dependencies
- Runs migrations
- Restores production permissions
- Runs validation commands
- Creates and verifies a post-upgrade backup
- Deactivates maintenance only when it activated maintenance
- Stops after the first failed process stage
- Leaves maintenance active after a failure
- Records the execution in the upgrade journal

Do not interrupt a running upgrade unless the process is clearly hung and a recovery procedure is available.

---

## 49. Review Upgrade Status

Display the latest upgrade execution:

```bash
./iqwurks upgrade:status
```

Display a specific execution:

```bash
./iqwurks upgrade:status \
    --id=EXECUTION_ID
```

Status reporting includes:

- Execution ID
- Version
- Start and completion timestamps
- Success or failure
- Failed stage
- Backups
- Rollback availability
- Maintenance state
- Journal status
- Process results
- Stage results

The status command is read-only.

---

## 50. Assess an Interrupted Upgrade

Run:

```bash
./iqwurks upgrade:recovery-check
```

Possible classifications include:

```text
CLEAR
COMPLETE
ACTIVE
INTERRUPTED
INCONSISTENT
UNKNOWN
```

The assessment inspects:

- Latest journal status
- Recorded process ID
- Process existence
- Upgrade lock file
- Actual lock state
- Lock metadata
- Maintenance mode
- Operator action requirements

The recovery check changes nothing.

Do not start another upgrade when the result is:

```text
ACTIVE
INTERRUPTED
INCONSISTENT
UNKNOWN
```

until the condition has been reviewed.

---

## 51. Post-Upgrade Validation

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks version
php migrate.php status
./iqwurks install:check
./iqwurks database:check
./iqwurks scheduler:check
./iqwurks doctor
./iqwurks maintenance:status
```

For release validation, run:

```bash
php vendor/bin/phpunit
```

Confirm:

- Expected application version
- Every migration is applied
- Database integrity is `ok`
- Foreign-key violations are zero
- SQLite journal mode is `wal`
- Scheduler diagnostics pass
- Mail diagnostics have no blocking failure
- Maintenance mode is inactive
- Kiosk works
- Supervisor login works
- Dashboard works
- Reports work
- Scheduled reports continue
- Retry processing continues
- Backups continue

---

## 52. Upgrade Failure and Rollback

When an upgrade fails:

1. Leave maintenance mode active.
2. Run `upgrade:status`.
3. Run `upgrade:recovery-check`.
4. Record the failed stage.
5. Preserve the upgrade journal.
6. Verify the pre-upgrade backup.
7. Restore the matching previous application release.
8. Restore the matching verified database backup when required.
9. Restore local configuration.
10. Restore ownership and permissions.
11. Run database health checks.
12. Run migration status.
13. Run diagnostics.
14. Disable maintenance only after recovery validation passes.

A database rollback must use a backup that matches the restored application release.

The controlled upgrade engine intentionally does not perform an unsafe automatic database rollback.

---

# Part V — Release Packaging

## 53. Preview a Distribution Package

From a clean release source tree:

```bash
cd /var/www/IQwurksPunch

./iqwurks package:build
```

Preview mode reports:

- Version
- Package filename
- Artifact path
- Manifest SHA-256
- Entry count
- Source-file count
- Directory count
- Generated runtime directories
- Excluded entries
- Unsafe entries
- Source bytes

Preview mode creates no staging directory or archive.

---

## 54. Build a Distribution Package

Run:

```bash
./iqwurks package:build --build
```

The build process:

- Creates an approved staging tree
- Copies only manifest-approved files
- Generates empty runtime directories
- Normalizes file modes
- Normalizes directory modes
- Creates a deterministic GNU TAR archive
- Sets numeric owner and group metadata
- Normalizes archive timestamps
- Creates a gzip-compressed archive
- Independently verifies the finished archive
- Removes temporary staging

Expected packaged modes:

```text
Normal directories:          0755
Writable runtime directories: 0770
Normal files:                0644
iqwurks:                     0750
migrate.php:                 0750
Archive file:                0640
```

Generated packages are stored under:

```text
storage/exports/packages
```

Generated packages are ignored by Git.

---

## 55. Verify a Distribution Package

Verify the current-version default archive:

```bash
./iqwurks package:verify
```

Verify a named archive:

```bash
./iqwurks package:verify \
    --archive=PATH_TO_ARCHIVE
```

Verify a named archive against a published checksum:

```bash
./iqwurks package:verify \
    --archive=PATH_TO_ARCHIVE \
    --sha256=PUBLISHED_SHA256
```

A release must not be published unless verification reports:

```text
Status: PASS
Successful: yes
Checksum matches: yes
Verification failures: 0
Temporary workspace removed: yes
Changes made: no
```

---

## 56. Release Acceptance Checklist

### Package

- [ ] Package name matches the release version.
- [ ] Archive checksum has been recorded.
- [ ] Archive contains one versioned root.
- [ ] `PACKAGE-MANIFEST.json` is present.
- [ ] `package:verify` reports `PASS`.
- [ ] Verification failures are zero.
- [ ] No symbolic links are present.
- [ ] No secrets are present.
- [ ] No SQLite database is present.
- [ ] No runtime logs are present.
- [ ] No runtime backups are present.
- [ ] No runtime exports are present.
- [ ] No session files are present.
- [ ] No machine inventory files are present.
- [ ] No `vendor/` directory is present.
- [ ] Runtime directories are empty.
- [ ] Normal directories are `0755`.
- [ ] Writable runtime directories are `0770`.
- [ ] Normal files are `0644`.
- [ ] Console entry points are `0750`.

### Application

- [ ] Application version is correct.
- [ ] Composer metadata validates.
- [ ] Composer platform requirements pass.
- [ ] Installation preflight passes.
- [ ] All migrations are applied.
- [ ] Database integrity is `ok`.
- [ ] Foreign-key violations are zero.
- [ ] SQLite journal mode is `wal`.
- [ ] Automated tests pass.
- [ ] Maintenance mode is inactive.

### Operations

- [ ] Nginx is active and enabled.
- [ ] PHP-FPM is active and enabled.
- [ ] Cron is active and enabled.
- [ ] Scheduler cron is installed.
- [ ] Email-retry cron is installed.
- [ ] Backup cron is installed.
- [ ] Logrotate is configured.
- [ ] Firewall rules are active.
- [ ] SMTP diagnostics pass.
- [ ] A verified backup exists.
- [ ] Supervisor login works.
- [ ] Employee kiosk works.
- [ ] Reboot recovery passes.

---

## 57. Security Checklist

Before production use, confirm:

- Strong administrator and supervisor passwords
- Only active authorized users have supervisor access
- `config/mail.php` is not in Git
- `config/mail.php` is mode `0640`
- Nginx document root is `public`
- Arbitrary PHP execution is blocked
- Hidden files are blocked
- Runtime paths are not publicly served
- UFW or another firewall is active
- HTTP access is limited to the trusted LAN
- SSH access is limited appropriately
- Kiosk user has no `sudo` access
- Runtime directories are writable only as required
- Database files are excluded from release packages
- Database files are excluded from Git
- Backups are excluded from release packages
- Backups are excluded from Git
- Session files are excluded from release packages
- Session files are excluded from Git
- A recent verified backup exists
- Maintenance mode is inactive
- Diagnostics have no failures
- The kiosk cannot browse arbitrary sites during normal operation
- HTTPS is used before any untrusted-network exposure

---

## 58. Important Runtime Paths

```text
Database:
database/sqlite/iqwurks.sqlite

SQLite sidecars:
database/sqlite/iqwurks.sqlite-wal
database/sqlite/iqwurks.sqlite-shm

Backups:
storage/backups

Upgrade state and cache:
storage/cache

Exports and packages:
storage/exports

Logs:
storage/logs

Sessions:
storage/sessions

Maintenance state:
storage/cache/maintenance.json
```

Do not commit runtime data.

Do not include runtime data in release archives.

---

## 59. System-Level Configuration Files

The validated deployment uses system files outside the repository:

```text
/etc/nginx/sites-available/iqwurks-punch
/etc/nginx/sites-enabled/iqwurks-punch
/etc/php/8.5/fpm/
/etc/lightdm/lightdm.conf.d/50-iqwurks-kiosk.conf
/usr/local/bin/iqwurks-kiosk-browser
/home/kiosk/.config/openbox/autostart
/etc/logrotate.d/iqwurks-punch
root crontab
UFW rules
```

Maintain secure backup copies or deployment records for these files.

Do not store SMTP passwords, supervisor passwords, employee PINs, or password hashes in general deployment documentation.

---

## 60. Current Limitations

Version 1.0 does not currently provide:

- A graphical operating-system installer
- Automatic Ubuntu package installation
- Automatic Nginx installation
- Automatic PHP-FPM installation
- Automatic cron installation
- Automatic logrotate installation
- Automatic firewall installation
- Automatic LightDM installation
- Automatic Openbox installation
- Automatic Chromium installation
- Built-in HTTPS provisioning
- Cryptographic package signatures beyond published SHA-256 verification
- Automatic release download
- Automatic application-source replacement
- Unsafe automatic database rollback
- Universal per-command `--help`

These remain controlled administrator operations.

---

## 61. Deployment Record

After installation or upgrade, record:

- Application version
- Package filename
- Package SHA-256
- Installation date
- Upgrade execution ID when applicable
- Server hostname
- Server IP address
- Network interface
- MAC address
- Gateway
- DNS server
- Trusted subnet
- Application path
- Company timezone
- SMTP provider
- Scheduler cron
- Retry cron
- Backup cron
- Backup retention
- Kiosk timeout
- System configuration locations
- Latest verified backup
- Latest recovery test
- Latest reboot validation
- Automated-test result

Do not record secrets in the deployment record.
