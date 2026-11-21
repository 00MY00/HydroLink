# Changelog

All notable changes to HydroLink. Versions follow [semantic versioning](https://semver.org/).

## [1.0.0] - 2026-11-21

First version.

- Free Proton Mail accounts in FreeScout through hydroxide (MIT), run by FreeScout's cron: one `hydroxide serve`
  for all accounts, IMAP 127.0.0.1:1143 and SMTP 127.0.0.1:1025, CardDAV off, everything in `storage/app/hydrolink`.
- Accounts: add in one form, 2FA code and human verification (CAPTCHA) asked in the panel when Proton requests
  them, two-password accounts, FreeScout mailbox created or linked automatically, reconnect, pause, delete, logs.
- Watchdog (`hydrolink:tick`): lock, pid check, restart, crash loop protection; the panel only writes a desired
  state, so a PHP-FPM without `proc_open` does not matter.
- hydroxide binaries built by this repository's CI from a pinned upstream commit (+ one patch for unattended
  sign-in), `linux-amd64` and `linux-arm64`, `SHA256SUMS`; update with checksum, optional VirusTotal analysis,
  test run, atomic replacement, health check and automatic rollback.
- Health checks, error classification (`HL-*` codes), "broken API" detection, notifications through FreeScout's
  alert e-mail, the WebPush module when installed and any module using the `hydrolink.notification_channels`
  filter; one notification per change of state, reminder, return to normal.
- Panel in the Workflow Packs style, touch friendly, French and English.
- `install.sh` (`--update`, `--uninstall [--keep-accounts]`, `--dry-run`), `hydrolink:health` for UpdateGuard.
