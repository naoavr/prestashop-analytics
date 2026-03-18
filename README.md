# SecurityGuard – PrestaShop Security Module

Real-time security monitoring and protection module for **PrestaShop 1.7.8.11** (and compatible with PS 8).

## Features

- Dashboard with live KPI tiles (attacks today, blocked IPs, active patterns, attacks last minute)
- Real-time event feed updated every 5 seconds via AJAX polling
- Auto-blocking of attackers with configurable threshold and duration
- Learn mode (logs only, does not block)
- Honeypot support
- Debug panel for AJAX diagnostics
- No custom Admin tab — all UI rendered inside the standard _Modules → Configure_ panel

---

## File structure

```
modules/securityguard/
├── securityguard.php                        Main module class
├── config.xml                               Module metadata
├── install/
│   └── install.sql                          DB table definitions
├── classes/
│   ├── SecurityLog.php                      Log stats & realtime data
│   └── SecurityBlocklist.php               Manage blocked IPs
├── ajax/
│   └── realtime.php                         Secure AJAX endpoint (JSON)
└── views/
    ├── js/
    │   ├── dashboard.js                     5-second polling + UI updates
    │   └── debug.js                         Debug diagnostics (debug mode only)
    └── templates/
        └── admin/
            └── dashboard.tpl               Smarty dashboard template
```

---

## Installation

### 1 — Create the zip

From the **repository root**, run:

```bash
zip -r securityguard.zip modules/securityguard
```

This creates `securityguard.zip` containing the `modules/securityguard/` folder.

### 2 — Upload via Back-Office

1. Log in to your PrestaShop Back-Office.
2. Go to **Modules → Module Manager**.
3. Click **Upload a module** (top-right button).
4. Select `securityguard.zip` and confirm.
5. After upload, click **Install**.
6. Click **Configure** to open the SecurityGuard panel.

### 3 — Manual installation (FTP/SSH)

1. Copy the `modules/securityguard/` folder to `<prestashop_root>/modules/`.
2. In Back-Office go to **Modules → Module Manager**, find _SecurityGuard_ and click **Install**.

---

## Configuration

After installation, go to **Modules → Module Manager → SecurityGuard → Configure**.

| Setting | Default | Description |
|---------|---------|-------------|
| Enable module | ON | Master on/off switch |
| Auto-block attackers | ON | Automatically block IPs after threshold |
| Max attempts before block | 5 | Failed attempts before an IP is blocked |
| Block duration | 1440 min | How long (in minutes) an IP stays blocked |
| Alert email | Shop email | Email address for security alerts |
| Learn mode | ON | Log threats but do **not** block (safe for initial deployment) |
| Enable honeypot | ON | Inject invisible honeypot fields |
| Debug mode | OFF | Show debug panel with AJAX diagnostics |

---

## Troubleshooting

### Blank page after install

Clear all caches:

```bash
# PrestaShop CLI (if available)
php bin/console cache:clear

# Or via Back-Office:
# Advanced Parameters → Performance → Clear cache
```

Also clear the Smarty compile cache:
```bash
rm -rf var/cache/dev/* var/cache/prod/*
```

### Token errors (`403 invalid_token` from realtime.php)

- Make sure you are **logged in** to the Back-Office before opening the Configure page.
- The token is tied to your session; if your BO session expires, reload the Configure page to get a fresh token.
- In some server configurations the `Authorization` header is stripped — this module uses a `?token=` query parameter which is not affected.

### `401 not_authenticated` from realtime.php

- Your Back-Office session has expired. Log in again and reload the Configure page.
- Some reverse-proxy setups strip session cookies on XHR. Ensure the AJAX request is sent to the same domain as your Back-Office.

### Smarty notice: `ajax_url` undefined

- Make sure you are running the latest version of `securityguard.php` from this repository. Older versions had an empty `ajax_url`.
- Clear the Smarty compile cache after updating the module.

### Dashboard shows "Waiting for events…" indefinitely

This is normal when no attacks have been detected yet. The feed will populate as soon as the module logs a threat event.

To verify the AJAX endpoint works manually:
1. Enable **Debug mode** in the module settings.
2. Reload the Configure page.
3. Check the **Debug panel** at the bottom of the dashboard — it shows the raw AJAX response and any errors.

### How to enable debug mode

1. In Back-Office: **Modules → SecurityGuard → Configure**.
2. Set **Debug mode** to **On** and click **Save**.
3. Reload the page — a yellow debug panel appears at the bottom of the dashboard showing:
   - The full `SG_AJAX_URL` used for polling
   - Whether `dashboard.js` loaded successfully
   - The last raw AJAX response
   - A diagnostic log from `debug.js`

---

## Security notes

- The `ajax/realtime.php` endpoint validates the token with `hash_equals()` (timing-safe comparison).
- The endpoint also checks that the request comes from an authenticated Back-Office employee.
- No sensitive data is exposed to unauthenticated requests.
