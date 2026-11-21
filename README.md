# HydroLink

FreeScout module: receive and send the e-mails of **free Proton Mail accounts** in FreeScout, without the official
Proton Mail Bridge (paid plans only). HydroLink installs and runs [hydroxide](https://codeberg.org/emersion/hydroxide)
(open source, MIT), which signs in to Proton like a normal client and offers IMAP and SMTP on `127.0.0.1`.
FreeScout then fetches and sends with its own functions.

> Independent project, not affiliated with Proton AG. "Proton" and "Proton Mail" are trademarks of Proton AG.
> hydroxide uses Proton's **unofficial** API: test with a secondary address first.

## What it does

- Add an account in one form (address, password, mailbox password for two-password accounts). When Proton asks for
  a 2FA code or a human verification (CAPTCHA), the panel asks for it.
- Creates or links the FreeScout mailbox (IMAP 127.0.0.1:1143, SMTP 127.0.0.1:1025, encryption "None").
- FreeScout's cron starts, watches and restarts hydroxide. The panel only writes what you want, so a PHP-FPM that
  blocks `proc_open` does not matter.
- Installs and updates the hydroxide binary built by this repository's CI (SHA-256, optional VirusTotal, test,
  rollback).
- Health checks and notifications (FreeScout alert e-mail, WebPush module when installed), once per change of state.

## What it does not do

- Paid Proton accounts with the official bridge, CardDAV, calendars.
- Bypass a CAPTCHA: you solve it yourself in your browser.
- Change FreeScout or any other module. No root, no systemd, no Docker on the server.

## Requirements

- FreeScout 1.8 or newer, PHP 7.1 or newer, Linux **amd64** or **arm64**.
- FreeScout's cron for the web user: `* * * * * php /var/www/html/artisan schedule:run >> /dev/null 2>&1`
- `proc_open` allowed for **command-line** PHP (PHP-FPM may keep it disabled).
- `storage/` writable and not mounted `noexec`; ports 1143 and 1025 free on 127.0.0.1.
- Outgoing HTTPS (Proton, GitHub releases).

## Installation

One command (as root, on the FreeScout server; it acts as the web user):

```bash
curl -fsSL https://github.com/00MY00/HydroLink/releases/latest/download/install.sh -o /tmp/hydrolink-install.sh \
  && sudo bash /tmp/hydrolink-install.sh
```

It checks the environment, downloads the latest release (SHA-256 checked), activates the module, runs the
migrations and installs the hydroxide binary. Options: `--path /var/www/html`, `--update`, `--dry-run`,
`--uninstall [--keep-accounts]`, `--yes`, `--lang fr|en`.

Or manually: unzip `HydroLink.zip` into `Modules/`, then **Manage > Modules > HydroLink > Activate**.

Then: **HydroLink** in Refresh's left bar, **Manage > HydroLink**, or the settings menu of any mailbox (phones), card **Add an account**. The sign-in starts within a minute (cron). Answer the 2FA
code or the verification if asked. The mailbox appears in FreeScout, ready to receive and send.

## Updates

- Module: FreeScout's **Manage > Modules**, the [UpdateGuard](https://github.com/00MY00/UpdateGuard) module, or
  `install.sh --update`. `hydrolink:health` is the health check UpdateGuard runs.
- hydroxide binary: card **Binary** > **Update**, or **Automatic update**.

## Commands

| Command | Use |
|---|---|
| `php artisan hydrolink:diagnose` | environment, binary, service, accounts (no secret printed) |
| `php artisan hydrolink:update-binary [--force] [--dry-run]` | install or update hydroxide now |
| `php artisan hydrolink:health [--json]` | health of the module (UpdateGuard) |
| `php artisan hydrolink:uninstall [--keep-accounts] [--dry-run]` | stop hydroxide and clean its files |

`hydrolink:tick` (watchdog) and `hydrolink:auth` (sign-ins) are run by the cron every minute.

## Error codes

| Code | Meaning | Action |
|---|---|---|
| HL-ENV-01 | `proc_open` disabled in the CLI | allow it in the CLI php.ini |
| HL-ENV-02 | `storage/` mounted noexec | mount without noexec |
| HL-ENV-03 | port 1143 / 1025 already used | free the port, restart |
| HL-ENV-04 | architecture not supported | amd64 or arm64 |
| HL-ENV-05 | FreeScout cron missing | add the cron job |
| HL-ENV-06 | `storage/` not writable | give it to the web user |
| HL-BIN-01 | binary missing | Binary > Install |
| HL-BIN-02 | checksum mismatch | try later, report if repeated |
| HL-BIN-03 | new binary does not start | check architecture / noexec |
| HL-BIN-04 | release unreachable | check outgoing HTTPS |
| HL-BIN-05 | VirusTotal above the threshold (quarantine) | read the report |
| HL-BIN-06 | failure after update, rolled back | wait for the next release |
| HL-AUTH-01 | human verification (Proton 9001) | solve it from the account card |
| HL-AUTH-02 | session invalid (401, 10013) | Reconnect |
| HL-AUTH-03 | sign-in refused | check address and password |
| HL-AUTH-04 | 2FA method other than TOTP | use app-based 2FA |
| HL-AUTH-05 | 2FA code refused | reconnect with a fresh code |
| HL-AUTH-06 | mailbox password needed or wrong | reconnect with it |
| HL-AUTH-07 | no answer within 10 minutes | Reconnect |
| HL-NET-01 | network error towards Proton | check the connection |
| HL-API-01 | unexpected answer from Proton | read the logs, update the binary |
| HL-API-02 | Proton API probably changed | update the binary |
| HL-SVC-01 | hydroxide keeps stopping | read the logs, Restart |
| HL-SVC-02 / 03 | local SMTP / IMAP silent | Restart |
| HL-SVC-04 | FreeScout fetching late | check the cron |
| HL-VT-01 / 02 | VirusTotal key refused / unavailable | fix the key, or wait |

Each code is explained in the panel (expected, effect, action).

## Security

- HydroLink never keeps the Proton password: it is handed once to `hydroxide auth` (environment of that process
  only) and deleted; hydroxide keeps its own encrypted session. The bridge password and the VirusTotal key are
  encrypted with `APP_KEY`. Secrets are masked in every log.
- Panel for FreeScout administrators only. Commands are argument arrays, never shell strings built from input.
- Every downloaded binary is checked against `SHA256SUMS` before it is run.

## Screenshots

To be added from the first real installation (desktop and phone).

## Development

- `php tests/unit/run.php`: unit tests, no FreeScout needed.
- `bash tests/integration/run.sh`: real FreeScout in Docker + PHPUnit (`HL_REAL_BINARY=...` to start a real binary).
- `bash build/build-hydroxide.sh`: binaries from `build/hydroxide.lock` + `build/patches/` (needs Go).
- Audit and choices: [docs/AUDIT.md](docs/AUDIT.md), [docs/OPEN-QUESTIONS.md](docs/OPEN-QUESTIONS.md).
- Add a feature: one class in `HydroLink/Features/` implementing `Feature` (card, settings, tasks, status).

## License

AGPL-3.0 (same as FreeScout). The hydroxide binaries are MIT licensed: `LICENSE.hydroxide` is published with each
release.
