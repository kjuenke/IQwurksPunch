# IQwurksPunch UFW Template

Replace:

```text
{{TRUSTED_SUBNET}}
```

with the trusted LAN subnet before running any command.

Example:

```text
192.168.1.0/24
```

---

## Allow SSH First

Always allow and verify SSH access before enabling UFW remotely:

```bash
ufw allow from {{TRUSTED_SUBNET}} \
    to any port 22 \
    proto tcp \
    comment 'LAN SSH'
```

Review the rule:

```bash
ufw status numbered
```

Do not enable UFW remotely until SSH access is allowed and confirmed.

---

## Allow IQwurksPunch

```bash
ufw allow from {{TRUSTED_SUBNET}} \
    to any port 80 \
    proto tcp \
    comment 'LAN IQwurksPunch'
```

---

## Optional Monitoring Port

When a local monitoring service such as Netdata is intentionally installed:

```bash
ufw allow from {{TRUSTED_SUBNET}} \
    to any port 19999 \
    proto tcp \
    comment 'LAN Monitoring'
```

Do not add the monitoring rule when no monitoring service requires it.

---

## Review Before Enablement

Review the pending and active rules:

```bash
ufw status numbered
```

Enable UFW only after the SSH rule has been confirmed:

```bash
ufw enable
```

Review the final policy:

```bash
ufw status verbose
```

The validated IQwurksPunch deployment uses HTTP only on a trusted LAN.

Add HTTPS before exposing the application across an untrusted network.
