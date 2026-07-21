# IQwurksPunch Production Installation Guide

**Applies to:** IQwurksPunch 0.6.0
**Target platform:** Dedicated Ubuntu Linux computer
**Validated environment:** Ubuntu 26.04 LTS, PHP 8.5, Nginx, PHP-FPM, SQLite, LightDM, Openbox, and Chromium

---

## 1. Purpose

This guide describes how to deploy IQwurksPunch as a production employee time clock on a dedicated Linux computer.

The completed installation provides:

- A local physical employee kiosk
- Employee access without Linux or application login accounts
- Supervisor access from trusted LAN computers
- Nginx and PHP-FPM production hosting
- SQLite database storage
- Automatic payroll-report scheduling
- Automatic verified database backups
- Log rotation
- Maintenance mode
- Database and application diagnostics
- Browser and reboot recovery
- LAN firewall restrictions

This guide assumes the application will be installed at:

```text
/var/www/IQwurksPunch
```

The validated production server uses:

```text
Hostname: iqwurks
IP address: 192.168.1.82
Trusted LAN: 192.168.1.0/24
Company timezone: America/Los_Angeles
```

Replace deployment-specific addresses and timezones where necessary.

---

## 2. Production Architecture

The validated deployment uses these components:

```text
Employee
    |
    v
Chromium kiosk
    |
    v
http://127.0.0.1/kiosk
    |
    v
Nginx
    |
    v
PHP-FPM 8.5
    |
    v
IQwurksPunch
    |
    v
SQLite database
```

Supervisors access the same Nginx installation over the trusted LAN:

```text
http://SERVER_ADDRESS/login
```

Background services use the console application:

```text
./iqwurks
```

Scheduled operations include:

- Payroll-report scheduling
- Verified database backups
- Log rotation
- Operating-system service supervision

---

## 3. Application URLs

### Local Physical Kiosk

```text
http://127.0.0.1/kiosk
```

### LAN Employee Kiosk

```text
http://192.168.1.82/kiosk
```

### Supervisor Login

```text
http://192.168.1.82/login
```

### Supervisor Dashboard

```text
http://192.168.1.82/dashboard
```

### Optional Netdata Monitoring

```text
http://192.168.1.82:19999
```

Replace `192.168.1.82` with the address assigned to the target computer.

---

## 4. Before Installation

Confirm that the computer has:

- A supported 64-bit Ubuntu installation
- A reliable local-network connection
- A fixed IP address or DHCP reservation
- Correct date and time
- Correct timezone
- Adequate disk space
- A working keyboard and display for initial setup
- Internet access during package installation
- SMTP credentials if payroll email will be used

Recommended operational practices:

- Use wired Ethernet when possible.
- Reserve the server address in the DHCP server.
- Use a UPS for the kiosk computer and network equipment.
- Record the server hostname and MAC address.
- Keep an external copy of verified database backups.
- Do not expose the HTTP service directly to the public internet.

---

## 5. Update Ubuntu

Run as `root` or through `sudo`:

```bash
apt update
apt full-upgrade -y
```

Reboot if the operating system or kernel was upgraded:

```bash
reboot
```

After reboot, confirm the system version:

```bash
cat /etc/os-release
uname -a
```

---

## 6. Set the Hostname

The validated hostname is:

```text
iqwurks
```

Set the hostname:

```bash
hostnamectl set-hostname iqwurks
```

Review:

```bash
hostnamectl
hostname
```

Ensure `/etc/hosts` contains a local hostname entry similar to:

```text
127.0.1.1 iqwurks
```

---

## 7. Set the System Timezone

The validated timezone is:

```text
America/Los_Angeles
```

Set it with:

```bash
timedatectl set-timezone America/Los_Angeles
```

Confirm:

```bash
timedatectl
date
```

The system timezone and IQwurksPunch company timezone should normally agree.

The company timezone is configured later through:

```text
Settings → Company Settings
```

---

## 8. Install Core Packages

Install the production web and application components:

```bash
apt install -y \
    nginx \
    sqlite3 \
    git \
    curl \
    unzip \
    cron \
    logrotate \
    ufw
```

Install PHP 8.5, PHP-FPM, and the extensions required by the application.

The exact package set depends on the Ubuntu repository configuration. Typical packages include:

```bash
apt install -y \
    php8.5 \
    php8.5-cli \
    php8.5-fpm \
    php8.5-sqlite3 \
    php8.5-mbstring \
    php8.5-xml \
    php8.5-curl \
    php8.5-zip \
    php8.5-intl
```

Confirm PHP:

```bash
php -v
php -m
```

Confirm PHP-FPM:

```bash
systemctl status php8.5-fpm
```

IQwurksPunch includes a System Doctor that will later check the complete required PHP-extension set.

---

## 9. Install Composer

Check whether Composer is already available:

```bash
composer --version
```

When Composer is not installed, install it using the approved Composer installation method for the operating system.

After installation, confirm:

```bash
composer --version
```

The validated deployment used Composer 2.9 or later.

---

## 10. Place the Application

Create the web application parent directory:

```bash
mkdir -p /var/www
```

Place or clone the IQwurksPunch source at:

```text
/var/www/IQwurksPunch
```

Example:

```bash
cd /var/www

git clone REPOSITORY_URL IQwurksPunch
```

For an archive installation:

```bash
mkdir -p /var/www/IQwurksPunch
```

Extract the release contents into that directory.

Confirm:

```bash
cd /var/www/IQwurksPunch

pwd
ls -la
```

Expected project files include:

```text
app/
bootstrap/
config/
database/
docs/
public/
releases/
routes/
storage/
tests/
composer.json
iqwurks
migrate.php
VERSION
```

---

## 11. Install Composer Dependencies

From the project root:

```bash
cd /var/www/IQwurksPunch

composer install \
    --no-dev \
    --optimize-autoloader
```

For development or release validation, install development dependencies:

```bash
composer install
```

Confirm Composer autoloading:

```bash
php -r '
require "vendor/autoload.php";
echo "Composer autoload OK." . PHP_EOL;
'
```

---

## 12. Create Runtime Directories

Create the required runtime directories:

```bash
cd /var/www/IQwurksPunch

mkdir -p \
    database/sqlite \
    storage/backups \
    storage/cache \
    storage/exports \
    storage/logs \
    storage/sessions
```

The web process must be able to write to:

```text
database/sqlite
storage/backups
storage/cache
storage/exports
storage/logs
storage/sessions
```

Set the validated ownership and permissions:

```bash
chown -R www-data:www-data \
    database/sqlite \
    storage/backups \
    storage/cache \
    storage/exports \
    storage/logs \
    storage/sessions
```

Set group-writable directory permissions:

```bash
chmod 0770 \
    database/sqlite \
    storage/backups \
    storage/cache \
    storage/exports \
    storage/logs \
    storage/sessions
```

Ensure parent directories are traversable by the web process:

```bash
chown -R root:www-data /var/www/IQwurksPunch
```

Give files group-read access and directories group traversal:

```bash
find /var/www/IQwurksPunch \
    -type d \
    -exec chmod 0750 {} \;

find /var/www/IQwurksPunch \
    -type f \
    -exec chmod 0640 {} \;
```

Restore executable permission to the console entry points:

```bash
chmod 0750 \
    iqwurks \
    migrate.php
```

Restore public static-file access:

```bash
find public \
    -type d \
    -exec chmod 0755 {} \;

find public \
    -type f \
    -exec chmod 0644 {} \;
```

Reapply runtime-directory permissions after any broad ownership or permission operation:

```bash
chown -R www-data:www-data \
    database/sqlite \
    storage/backups \
    storage/cache \
    storage/exports \
    storage/logs \
    storage/sessions

chmod 0770 \
    database/sqlite \
    storage/backups \
    storage/cache \
    storage/exports \
    storage/logs \
    storage/sessions
```

The root user can still run administrative console commands.

---

## 13. Configure SMTP Mail

The active SMTP configuration is stored in:

```text
config/mail.php
```

This file contains secrets and must not be committed to Git.

Start from the example configuration when present:

```bash
cp config/mail.example.php config/mail.php
```

Edit:

```bash
nano config/mail.php
```

Enter the correct:

- SMTP hostname
- SMTP port
- Encryption method
- Username
- Password
- Sender address
- Sender name

Restrict permissions:

```bash
chown root:www-data config/mail.php
chmod 0640 config/mail.php
```

Confirm Git ignores the file:

```bash
git check-ignore -v config/mail.php
```

Do not store payroll-recipient addresses in `config/mail.php`.

Recipients are managed through the application’s Notification Center.

---

## 14. Configure Backup and Maintenance Settings

### Backup Configuration

File:

```text
config/backup.php
```

Validated configuration:

```php
<?php
declare(strict_types=1);

return [
    'directory' =>
        dirname(
            __DIR__
        )
        .
        '/storage/backups',

    'retention_count' =>
        30,

    'lock_file' =>
        dirname(
            __DIR__
        )
        .
        '/storage/cache/backup.lock',
];
```

### Maintenance Configuration

File:

```text
config/maintenance.php
```

Validated configuration:

```php
<?php
declare(strict_types=1);

return [
    'file' =>
        dirname(
            __DIR__
        )
        .
        '/storage/cache/maintenance.json',

    'retry_after_seconds' =>
        300,
];
```

Validate both files:

```bash
php -l config/backup.php
php -l config/maintenance.php
```

---

## 15. Initialize or Migrate the Database

Run:

```bash
cd /var/www/IQwurksPunch

php migrate.php
```

Version 0.6 should record 11 migrations, including:

```text
010_add_punch_correction_support.php
011_add_kiosk_inactivity_timeout.php
```

Review the schema:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
.tables
"
```

Review migration history:

```bash
sqlite3 database/sqlite/iqwurks.sqlite "
SELECT migration
FROM migrations
ORDER BY migration;
"
```

Check database health:

```bash
./iqwurks database:check
```

A healthy database should report:

```text
Status: HEALTHY
Journal mode: wal
Integrity check: ok
Foreign-key violations: 0
Applied migrations: 11
```

---

## 16. Create the Initial Supervisor

When no supervisor exists, open:

```text
http://SERVER_ADDRESS/setup
```

Create the initial administrator account.

Use a strong password.

After the initial account is created:

1. Confirm supervisor login.
2. Confirm `/setup` no longer permits unsafe reinitialization.
3. Record the account securely.
4. Do not share the supervisor password with kiosk employees.

---

## 17. Configure Nginx

Create:

```bash
nano /etc/nginx/sites-available/iqwurks-punch
```

Use:

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    server_name
        iqwurks
        iqwurks.local
        192.168.1.82
        _;

    root /var/www/IQwurksPunch/public;
    index index.php;

    charset utf-8;

    access_log /var/log/nginx/iqwurks-punch-access.log;
    error_log /var/log/nginx/iqwurks-punch-error.log warn;

    server_tokens off;

    client_max_body_size 10m;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "same-origin" always;
    add_header X-Robots-Tag "noindex, nofollow" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;

        fastcgi_param SCRIPT_FILENAME
            $document_root$fastcgi_script_name;

        fastcgi_param SCRIPT_NAME
            $fastcgi_script_name;

        fastcgi_param HTTP_PROXY "";

        fastcgi_pass unix:/run/php/php8.5-fpm.sock;

        fastcgi_read_timeout 120s;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(?:css|js|jpg|jpeg|gif|png|svg|ico|webp|woff|woff2)$ {
        expires 7d;

        add_header Cache-Control "public, max-age=604800";

        try_files $uri =404;

        access_log off;
    }
}
```

Change the hostname or IP address where required.

Disable the default site:

```bash
rm -f /etc/nginx/sites-enabled/default
```

Enable IQwurksPunch:

```bash
ln -s \
    /etc/nginx/sites-available/iqwurks-punch \
    /etc/nginx/sites-enabled/iqwurks-punch
```

Test Nginx:

```bash
nginx -t
```

Restart and enable it:

```bash
systemctl restart nginx
systemctl enable nginx
```

Confirm:

```bash
systemctl is-active nginx
systemctl is-enabled nginx
```

---

## 18. Configure PHP-FPM

Create:

```bash
nano /etc/php/8.5/fpm/conf.d/99-iqwurks-punch.ini
```

Use:

```ini
expose_php = Off

display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL

error_log = /var/www/IQwurksPunch/storage/logs/php-fpm-error.log

memory_limit = 256M
max_execution_time = 120
max_input_time = 120
max_input_vars = 2000

post_max_size = 10M
upload_max_filesize = 10M

session.use_strict_mode = 1
session.use_only_cookies = 1
session.cookie_httponly = 1
session.cookie_samesite = Lax
session.gc_maxlifetime = 28800

opcache.enable = 1
opcache.enable_cli = 0
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000

date.timezone = America/Los_Angeles
```

Change the timezone when required.

Validate PHP configuration:

```bash
php-fpm8.5 -t
```

Restart and enable PHP-FPM:

```bash
systemctl restart php8.5-fpm
systemctl enable php8.5-fpm
```

Confirm:

```bash
systemctl is-active php8.5-fpm
systemctl is-enabled php8.5-fpm
```

Confirm the socket exists:

```bash
ls -l /run/php/php8.5-fpm.sock
```

---

## 19. Test the Web Application

Test locally:

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
```

Expected:

```text
Kiosk HTTP 200
Login HTTP 200
```

Test a protected page while logged out:

```bash
curl \
    --silent \
    --output /dev/null \
    --write-out 'Dashboard HTTP %{http_code}\n' \
    http://127.0.0.1/dashboard
```

A redirect response is expected for unauthenticated access.

From another LAN computer, open:

```text
http://192.168.1.82/kiosk
http://192.168.1.82/login
```

---

## 20. Verify Local Frontend Assets

Run from the project root:

```bash
cd /var/www/IQwurksPunch

sha256sum --check public/assets/vendor/SHA256SUMS
```

Do not run the checksum command from inside `public/assets/vendor`.

Verify HTTP access:

```bash
curl -I \
    http://127.0.0.1/assets/vendor/bootstrap/5.3.7/css/bootstrap.min.css

curl -I \
    http://127.0.0.1/assets/vendor/bootstrap/5.3.7/js/bootstrap.bundle.min.js

curl -I \
    http://127.0.0.1/assets/vendor/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css
```

Each asset should return HTTP 200.

Confirm the rendered kiosk has no old CDN dependency:

```bash
curl --silent http://127.0.0.1/kiosk \
    | grep -i 'jsdelivr' \
    && echo 'FAIL: External jsDelivr reference found.' \
    || echo 'PASS: Primary frontend assets are local.'
```

---

## 21. Configure the Payroll Scheduler

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

Run the scheduler manually:

```bash
cd /var/www/IQwurksPunch

./iqwurks schedule:run
```

A normal result may be:

```text
No scheduled report is due.
```

Run diagnostics:

```bash
./iqwurks scheduler:check
```

A healthy installation should pass all scheduler checks.

---

## 22. Configure Automatic Backups

Edit root’s crontab:

```bash
crontab -e
```

Add:

```cron
# BEGIN IQWURKSPUNCH BACKUP
# Daily verified SQLite backup with automatic retention
CRON_TZ=America/Los_Angeles
15 1 * * * cd /var/www/IQwurksPunch && /usr/bin/flock -n /tmp/iqwurks-backup-cron.lock /usr/bin/php iqwurks backup:run >> storage/logs/cron-backup.log 2>&1
# END IQWURKSPUNCH BACKUP
```

Change the timezone and schedule where necessary.

Confirm:

```bash
crontab -l
```

Test the backup workflow:

```bash
cd /var/www/IQwurksPunch

./iqwurks backup:run
./iqwurks backup:list
./iqwurks backup:verify
```

A backup should not be considered successful until verification passes.

---

## 23. Configure Log Rotation

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

The second section becomes usable after the `kiosk` account is created.

Validate:

```bash
logrotate --debug /etc/logrotate.d/iqwurks-punch
```

Confirm the logrotate timer:

```bash
systemctl status logrotate.timer
systemctl is-enabled logrotate.timer
```

A forced test can be run during installation:

```bash
logrotate --force /etc/logrotate.d/iqwurks-punch
```

After a forced rotation, the current mail log may be empty until the next successful email.

That can temporarily produce a System Doctor warning.

---

## 24. Configure the Firewall

Enable only trusted-LAN access.

Allow SSH:

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

When Netdata is installed, allow it:

```bash
ufw allow from 192.168.1.0/24 \
    to any port 19999 \
    proto tcp \
    comment 'LAN Netdata'
```

Enable UFW:

```bash
ufw enable
```

Review:

```bash
ufw status numbered
```

Validated rules:

```text
22/tcp       ALLOW IN    192.168.1.0/24
80/tcp       ALLOW IN    192.168.1.0/24
19999/tcp    ALLOW IN    192.168.1.0/24
```

Do not enable UFW remotely until the SSH rule has been added and verified.

---

## 25. Configure a Fixed Network Address

A dedicated kiosk should keep a consistent address.

Preferred methods:

- DHCP reservation in the router or DHCP server
- Correctly configured static addressing

The validated reservation is:

```text
Hostname: iqwurks
IP address: 192.168.1.82
MAC address: d4:be:d9:ce:ee:0c
Interface: enp3s0
Gateway: 192.168.1.254
DHCP/DNS server: 192.168.1.234
```

Confirm the current address:

```bash
ip address show
ip route
hostname -I
```

Confirm the default route:

```bash
ip route show default
```

After creating a DHCP reservation, renew the lease or reboot and confirm the expected address remains assigned.

---

## 26. Install the Physical Kiosk Components

Install the graphical kiosk packages:

```bash
apt install -y \
    xorg \
    openbox \
    lightdm \
    lightdm-gtk-greeter \
    unclutter
```

Install Chromium using the supported Ubuntu package or snap:

```bash
snap install chromium
```

Confirm:

```bash
/snap/bin/chromium --version
```

---

## 27. Create the Restricted Kiosk User

Create the local kiosk account:

```bash
adduser \
    --disabled-password \
    --gecos '' \
    kiosk
```

Confirm:

```bash
id kiosk
```

The account must not have `sudo` access.

Check:

```bash
getent group sudo
```

Do not add `kiosk` to the `sudo` group.

Create its configuration and state directories:

```bash
install \
    -d \
    -o kiosk \
    -g kiosk \
    -m 0700 \
    /home/kiosk/.config/openbox

install \
    -d \
    -o kiosk \
    -g kiosk \
    -m 0700 \
    /home/kiosk/.local/state/iqwurks-kiosk
```

---

## 28. Configure LightDM Automatic Login

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

Confirm the file:

```bash
cat /etc/lightdm/lightdm.conf.d/50-iqwurks-kiosk.conf
```

Enable LightDM:

```bash
systemctl enable lightdm
```

Do not restart LightDM remotely unless interruption of the physical screen is acceptable.

---

## 29. Create the Kiosk Browser Launcher

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

Set ownership and permissions:

```bash
chown root:root /usr/local/bin/iqwurks-kiosk-browser
chmod 0755 /usr/local/bin/iqwurks-kiosk-browser
```

Validate Bash syntax:

```bash
bash -n /usr/local/bin/iqwurks-kiosk-browser
```

---

## 30. Configure Openbox Autostart

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

/usr/bin/xsetroot \
    -solid black

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
sh -n /home/kiosk/.config/openbox/autostart
```

---

## 31. Disable Sleep and Display Interruption

The Openbox autostart file disables:

- X screen saver
- Screen blanking
- Display power management

Also mask operating-system sleep targets:

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

The targets should show as masked.

---

## 32. Start the Physical Kiosk

Restart LightDM:

```bash
systemctl restart lightdm
```

The physical display should:

1. Automatically log in as `kiosk`.
2. Start Openbox.
3. Wait for IQwurksPunch to respond.
4. Start Chromium.
5. Open the employee kiosk full-screen.

Review the browser log:

```bash
tail -n 100 \
    /home/kiosk/.local/state/iqwurks-kiosk/browser.log
```

---

## 33. Test Browser Recovery

Find Chromium processes:

```bash
pgrep -a chromium
```

Terminate the kiosk browser during a controlled test:

```bash
pkill -u kiosk chromium
```

The launcher should restart Chromium after approximately two seconds.

Review:

```bash
tail -n 50 \
    /home/kiosk/.local/state/iqwurks-kiosk/browser.log
```

Expected log behavior includes:

```text
Chromium exited with code ...
Restarting in two seconds.
Starting Chromium kiosk browser.
```

---

## 34. Configure Kiosk Inactivity Protection

Log in as a supervisor and open:

```text
Settings → Company Settings
```

Under:

```text
Kiosk Safety
```

set:

```text
Kiosk Inactivity Timeout
```

Allowed range:

```text
15–600 seconds
```

Recommended production default:

```text
60 seconds
```

Test:

1. Enter a valid employee number.
2. Leave the PIN screen untouched.
3. Confirm the final ten-second warning appears.
4. Confirm the kiosk returns to the starting screen.
5. Confirm no punch is recorded.
6. Repeat on the punch-action screen.
7. Confirm keyboard, mouse, or touch activity restarts the timer.

---

## 35. Configure Company Settings

Log in as a supervisor.

Open:

```text
Settings → Company Settings
```

Configure:

- Company name
- Address
- City
- State
- ZIP code
- Phone
- Email
- Timezone
- Kiosk inactivity timeout
- Punch rounding
- Automatic meal deduction
- Meal deduction minutes
- Paid-break allowance

Save and reload the page to confirm persistence.

---

## 36. Configure Labor Rules

Open:

```text
Settings → Labor Rules
```

Configure:

- Daily overtime threshold
- Weekly overtime threshold
- Double-time threshold
- Sunday or Monday workweek start

Ensure the double-time threshold is greater than the daily overtime threshold.

Generate test payroll reports after changing labor rules.

---

## 37. Configure Notification Recipients

Open the Notification Center.

Add payroll-report recipients.

Configure each recipient’s subscriptions:

- Daily payroll
- Weekly payroll
- Exception reports

Activate the recipients that should receive scheduled reports.

Recipients are stored in the database and are separate from SMTP transport settings.

---

## 38. Configure Automatic Report Delivery

Open the report email settings.

Configure:

- Automatic delivery enabled or disabled
- Delivery time
- Delivery days
- Active recipients

Save the schedule.

Run:

```bash
./iqwurks scheduler:check
```

Confirm that:

- The schedule is structurally valid.
- Active recipients are available.
- The cron entry is installed.

---

## 39. Run Mail Diagnostics

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks mail:check
```

Review:

- SMTP configuration
- Hostname resolution
- TCP connectivity
- TLS readiness
- Delivery status

Send a manual test or payroll email through the application after diagnostics pass.

A newly rotated empty mail log may cause a temporary warning.

---

## 40. Run the Complete Diagnostic Suite

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks database:check
./iqwurks scheduler:check
./iqwurks doctor
```

Expected essentials:

```text
Database status: HEALTHY
Scheduler status: PASS
System Doctor: no failures
```

A mail-activity warning may be acceptable immediately after log rotation when SMTP configuration and recent historical delivery are otherwise confirmed.

---

## 41. Run Automated Tests

For a development or release-validation installation:

```bash
cd /var/www/IQwurksPunch

php vendor/bin/phpunit
```

Expected Version 0.6 result:

```text
OK (36 tests, 188 assertions)
```

If `vendor/bin/phpunit` is not executable, use:

```bash
php vendor/bin/phpunit
```

Do not continue to release validation when tests fail.

---

## 42. Validate PHP Syntax

Every PHP file modified during installation or deployment should pass:

```bash
php -l PATH_TO_FILE.php
```

Validate the primary configuration and application entry files:

```bash
php -l config/backup.php
php -l config/maintenance.php
php -l public/index.php
php -l app/Core/Database.php
php -l app/Controllers/KioskController.php
php -l routes/web.php
php -l database/migrations/010_add_punch_correction_support.php
php -l database/migrations/011_add_kiosk_inactivity_timeout.php
```

---

## 43. Test Maintenance Mode

Enable it:

```bash
cd /var/www/IQwurksPunch

./iqwurks maintenance:on Installation validation
```

Check:

```bash
./iqwurks maintenance:status
```

Confirm a web request receives the maintenance response.

Disable it:

```bash
./iqwurks maintenance:off
```

Confirm:

```bash
./iqwurks maintenance:status
```

Do not use:

```bash
./iqwurks maintenance:on --help
```

The current console does not implement universal per-command help. That command would enable maintenance mode with `--help` as the reason.

---

## 44. Create the First Verified Backup

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks backup:create
./iqwurks backup:list
./iqwurks backup:verify
```

Record the newest verified backup filename.

Preview a restore without applying it:

```bash
./iqwurks backup:restore BACKUP_FILENAME
```

Do not apply a restore during ordinary installation validation unless a controlled restore test has been planned.

---

## 45. Reboot Recovery Test

After configuration is complete:

```bash
reboot
```

After reboot, confirm:

```bash
systemctl is-active nginx
systemctl is-active php8.5-fpm
systemctl is-active cron
systemctl is-active lightdm
systemctl is-active snapd
```

Confirm automatic startup:

```bash
systemctl is-enabled nginx
systemctl is-enabled php8.5-fpm
systemctl is-enabled cron
systemctl is-enabled lightdm
systemctl is-enabled snapd
```

Verify the web application:

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
```

Verify:

- The physical kiosk started.
- Chromium is full-screen.
- The employee-number screen is visible.
- LAN kiosk access works.
- Supervisor login works.
- Protected routes redirect unauthenticated users.
- Scheduler activity resumed.
- Automatic backup cron remains installed.
- Firewall rules remain active.

Run:

```bash
cd /var/www/IQwurksPunch

./iqwurks database:check
./iqwurks scheduler:check
./iqwurks doctor
```

---

## 46. Production Service Files

The validated system-level files are:

```text
/etc/nginx/sites-available/iqwurks-punch
/etc/nginx/sites-enabled/iqwurks-punch
/etc/php/8.5/fpm/conf.d/99-iqwurks-punch.ini
/etc/lightdm/lightdm.conf.d/50-iqwurks-kiosk.conf
/usr/local/bin/iqwurks-kiosk-browser
/home/kiosk/.config/openbox/autostart
/etc/logrotate.d/iqwurks-punch
```

The validated root cron includes:

```text
schedule:run
backup:run
```

These system-level files are outside the Git repository.

Keep secure backup copies of them or record them in deployment documentation.

---

## 47. Production Runtime Paths

Important application paths include:

```text
Database:
database/sqlite/iqwurks.sqlite

Database sidecars:
database/sqlite/iqwurks.sqlite-wal
database/sqlite/iqwurks.sqlite-shm

Backups:
storage/backups

Cache and locks:
storage/cache

Exports:
storage/exports

Application logs:
storage/logs

PHP sessions:
storage/sessions

Maintenance state:
storage/cache/maintenance.json

Backup lock:
storage/cache/backup.lock

Kiosk browser log:
/home/kiosk/.local/state/iqwurks-kiosk/browser.log
```

Do not commit runtime files to Git.

---

## 48. Security Checklist

Before production use, confirm:

- Supervisor passwords are strong.
- Only active administrators and supervisors have administrative access.
- `config/mail.php` is ignored by Git.
- `config/mail.php` has restricted permissions.
- The Nginx document root is `public`.
- Arbitrary PHP execution is blocked.
- Hidden files are blocked.
- UFW is active.
- HTTP port 80 is limited to the trusted LAN.
- SSH is limited to the trusted LAN.
- The kiosk user has no `sudo` access.
- Runtime directories are writable only as required.
- Session files are ignored by Git.
- Database files are ignored by Git.
- Backups are ignored by Git.
- A verified backup exists.
- Maintenance mode is inactive.
- Diagnostics show no failures.
- The physical kiosk cannot browse arbitrary websites through normal operation.

The validated deployment uses HTTP only on a trusted LAN.

Add HTTPS before exposing IQwurksPunch across an untrusted network.

---

## 49. Installation Acceptance Checklist

The installation is ready for production only after all applicable items pass.

### Application

- [ ] IQwurksPunch reports Version 0.6.0.
- [ ] Composer autoloading works.
- [ ] All migrations are applied.
- [ ] Database health is `HEALTHY`.
- [ ] SQLite journal mode is `wal`.
- [ ] Foreign-key violations are zero.
- [ ] Supervisor login works.
- [ ] Dashboard works.
- [ ] Employee management works.
- [ ] Reports work.
- [ ] Settings work.
- [ ] Labor Rules works.
- [ ] Punch correction works.
- [ ] Kiosk works.

### Kiosk

- [ ] LightDM automatically logs in as `kiosk`.
- [ ] Openbox starts.
- [ ] Chromium starts full-screen.
- [ ] Chromium restarts after termination.
- [ ] Screen blanking is disabled.
- [ ] Display power management is disabled.
- [ ] Suspend and hibernation are disabled.
- [ ] Kiosk timeout resets the PIN screen.
- [ ] Kiosk timeout resets the action screen.
- [ ] Activity restarts the timeout.
- [ ] Timeout does not create a punch.

### Operations

- [ ] Nginx is enabled and active.
- [ ] PHP-FPM is enabled and active.
- [ ] Cron is enabled and active.
- [ ] Scheduler cron is installed.
- [ ] Backup cron is installed.
- [ ] Logrotate is enabled.
- [ ] UFW is active.
- [ ] LAN access works.
- [ ] SMTP configuration passes diagnostics.
- [ ] A manual email has been sent successfully.
- [ ] A verified backup exists.
- [ ] Restore preview works.
- [ ] System Doctor has no failures.
- [ ] Automated tests pass.
- [ ] Reboot recovery passes.

---

## 50. Known Installation Limitations

Version 0.6 does not yet provide:

- A guided graphical installer
- An automated upgrade command
- Automatic Nginx configuration installation
- Automatic PHP-FPM configuration installation
- Automatic cron installation
- Automatic LightDM or Openbox configuration
- Automatic firewall configuration
- Versioned release packaging
- Universal per-command console help
- Built-in HTTPS provisioning
- Multiple independently managed kiosk profiles

These tasks currently require manual system administration.

---

## 51. Post-Installation Documentation

After installation, record:

- Server hostname
- Server IP address
- Network interface
- MAC address
- Gateway
- DNS server
- DHCP reservation
- Application path
- Company timezone
- Supervisor account owner
- SMTP provider
- Backup schedule
- Backup retention
- Scheduler schedule
- Kiosk timeout
- System-level configuration file locations
- Date of the most recent recovery test
- Date of the most recent reboot validation

Do not record passwords, PINs, SMTP secrets, or password hashes in general deployment documentation.
