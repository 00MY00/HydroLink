# Changelog

All notable changes to HydroLink. Versions follow [semantic versioning](https://semver.org/).

## [1.0.2] - 2026-11-21

- Panel layout: text fields had a 200 px height in the cards (flex basis in a column), which made the "Add an
  account" card and the settings cards far too tall; fixed.
- Desktop: three columns (two on tablets, one on phones), "Accounts" and "Settings" sections, cards of a row with
  the same height, "Add an account" on one line (address, password, button).
- Folded sections ("Reconnect", "Logs", ...) show an arrow.
- No more "leave the page?" dialog: settings save themselves, a save still waiting is sent when the page is left.

## [1.0.1] - 2026-11-21

- HydroLink entry in Refresh's left bar (`refresh.rail_items`, own icon), like WorkflowPacks and UpdateGuard; the
  entry in FreeScout's Manage menu stays.
- HydroLink entry in the settings menu of each mailbox (administrators), reachable on phones and tablets where
  Refresh hides its left bar.

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
