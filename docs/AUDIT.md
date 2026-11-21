# Audit (phase 0)

Done on 2026-11-21 before writing code. Every point below was read in the source; references are `file:line`.
Points that could not be verified are in [OPEN-QUESTIONS.md](OPEN-QUESTIONS.md).

## FreeScout

Version read: 1.8.252 (`config/app.php:21`, latest release on GitHub on the day of the audit).

| Topic | Finding | Reference |
|---|---|---|
| Modules | nWidart modules: `module.json` (providers, alias), service provider loads routes, views, translations, migrations | any module, e.g. `Modules/WorkflowPacks/Providers/WorkflowPacksServiceProvider.php` |
| Installation of a module | `freescout:module-install <alias>`: migrations + symlink `public/modules/<alias>` | `app/Console/Commands/ModuleInstall.php:17,49,72,130` |
| Manage menu | Eventy action `menu.manage.after_mailboxes` | `resources/views/layouts/app.blade.php:110` |
| Page sections | `stylesheets`, `content`, `javascripts` | `resources/views/layouts/app.blade.php:42,264,315` |
| Access rights | middleware `roles` reads the `roles` key of the route action | `app/Http/Middleware/CheckRole.php:18-41` |
| Scheduler | `\Eventy::filter('schedule', $schedule)` lets modules add tasks to `schedule:run` | `app/Console/Kernel.php:190` |
| Mailbox fields | `in_*` / `out_*` fillable; passwords encrypted by mutators | `app/Mailbox.php:160,174,203` |
| Encryption constants | `IN_ENCRYPTION_NONE = 1`, `OUT_METHOD_SMTP = 3`, `OUT_ENCRYPTION_NONE = 1` | `app/Mailbox.php:58,65,92` |
| Fetch switch | `isInActive()` asks the filter `mailbox.in_active` first, `FetchEmails` skips inactive mailboxes | `app/Mailbox.php:460-473`, `app/Console/Commands/FetchEmails.php:118` |
| Last fetch | only global options `fetch_emails_last_run` / `fetch_emails_last_successful_run` (no per-mailbox value) | `app/Console/Commands/FetchEmails.php:85,187` |
| New mailbox | `MailboxObserver::created` creates the folders | `app/Observers/MailboxObserver.php:16-21` |
| Same address as a user | refused by FreeScout (`Mailbox::userEmailExists`) | `app/Http/Controllers/MailboxesController.php:78`, `app/Mailbox.php:1035` |
| Native notifications | `MailHelper::sendAlertMail($text, $title)` queues `SendAlert`: admins + option `alert_recipients` | `app/Misc/Mail.php:584`, `app/Jobs/SendAlert.php` |
| Users | `ROLE_ADMIN = 2`, `STATUS_ACTIVE = 1` | `app/User.php:46,62` |
| Flash messages | `flash_success_floating`, `flash_error_floating` | `resources/views/partials/floating_flash_messages.blade.php` |

## Workflow Packs (visual style)

The style guide of that module (`docs/style.md` of the WorkflowPacks repository) lists its tokens: text `#312e81`,
primary `#4f46e5`, success `#059669` / `#047857` on `#e7f6ef`, radius 16 px for sections, 999 px pills, 44 px touch
targets, no gradient, pill = dot + word. HydroLink reproduces this language in its own CSS
(`HydroLink/Public/css/hydrolink.css`); no file is copied. Both projects are AGPL-3.0 and from the same author.
Its WebPush adapter (`WorkflowPacks/Services/Notify/WebPush.php:17-31`) documents the public method used:
`\Modules\WebPush\Services\WebPush::sendToUsers([[user_id, payload]])`.

## UpdateGuard

Contract read in `UpdateGuard/Services/ModuleDiscoveryService.php:8-16` and `HealthCheckService.php:7-15`:

- `module.json`: `latestVersionUrl` (plain text version), `latestVersionZipUrl`, optional `sha256sumsUrl`
  (default: `SHA256SUMS` next to the ZIP), `healthCommand` (must be `<alias>:...`, `ModuleDiscoveryService.php:88-99`),
  `updateGuard.migrations`.
- Health protocol: `php artisan <alias>:health [--json]`, exit 0 = healthy, JSON `{"status","module","version","checks"}`.
- Optional `interop.json` next to the ZIP (`Compatibility/Compatibility.php:14-15,51-60`): not needed by HydroLink.

HydroLink follows it: `module.json` URLs on `releases/latest/download/`, `SHA256SUMS` published, `hydrolink:health --json`.

## hydroxide

- Upstream moved to Codeberg: `github.com/emersion/hydroxide` is archived, its last commit adds a "Codeberg migration
  notice" (2026-08-02). Codeberg `emersion/hydroxide`, commit `02b3b6c8` (2026-10-25) adds the human verification flow
  (Proton error 9001): it is newer than the forks that patched CAPTCHA (e.g. `Kin69/hydroxide-captcha-fix`, last push
  2026-08-02). Chosen: upstream Codeberg at `02b3b6c8`, last tag `v0.2.33`.
- Multi-account: one `serve` serves every user of `auth.json`; `auth.Manager` keys sessions by user name
  (`auth/auth.go:183-244`).
- Flags: `-imap-host/-imap-port` (default 127.0.0.1:1143), `-smtp-host/-smtp-port` (1025), `-disable-carddav`
  (`cmd/hydroxide/main.go:178-246`).
- Configuration: `config.Path` uses `os.UserConfigDir()` (`config/config.go:8-22`), i.e. `$XDG_CONFIG_HOME` on Linux.
  Files: `hydroxide/auth.json` (`auth/auth.go:20`), one IMAP database per user (`imap/database/user.go:228`).
- `HYDROXIDE_BRIDGE_PASS` is only read when a command asks for the bridge password (`main.go:73-79`), not by `auth`.
- `auth` reads passwords with `term.ReadPassword` on stdin or `/dev/tty` (`main.go:54-71`): impossible from cron.
  Upstream pull request #359 (open) adds `HYDROXIDE_PASSWORD`, `HYDROXIDE_MAILBOX_PASSWORD`,
  `HYDROXIDE_BRIDGE_PASSWORD`, `HYDROXIDE_TOTP`, but makes a human verification fatal without a terminal.
  HydroLink builds with that pull request minus that one change (`build/patches/0001-noninteractive-auth.patch`).
- Human verification: hydroxide prints `https://verify.proton.me/?methods=...&token=...` and waits for a line on
  stdin, then retries with the token (`main.go:300-314`). Nothing is bypassed.
- 2FA: TOTP only ("Only TOTP is supported as a 2FA method"), code read from stdin after `2FA TOTP code: ` (`main.go:319-334`).
- Bridge password = base64 of the 32-byte key (`auth/auth.go:160-167`).
- Errors: Proton API errors print as `[<code>] <message>` (`protonmail/protonmail.go:61-63`); 9001 = human
  verification (`protonmail.go:36`); 10013 = invalid refresh token, then a new login, impossible with 2FA
  (`auth/auth.go:125-139`); HTTP 401 triggers `ReAuth` (`protonmail/protonmail.go:161`).
- No `logout` command: removing an account = deleting its key in `auth.json`.
- License: MIT (`LICENSE`).

## Notification channels

- Native: FreeScout alert e-mail (above). FreeScout's in-app notifications are tied to conversations
  (`App\Notifications\WebsiteNotification`) and cannot carry a system alert.
- WebPush module (`github.com/altmenorg/freescout-webpush`, listed in the instance's module list): public
  `sendToUsers()` as used by Workflow Packs.
- Others (Telegram, Slack official modules): paid, source not available, not cited. Any module can add a channel
  through the filter `hydrolink.notification_channels`, and receives every notification through the action
  `hydrolink.alert`.

## VirusTotal API v3

From the official reference (docs.virustotal.com) and the UpdateGuard client that already uses it
(`UpdateGuard/Services/Security/VirusTotalScanner.php`): header `x-apikey`; `GET /api/v3/files/{sha256}`
(`data.attributes.last_analysis_stats`, 404 when unknown); `POST /api/v3/files` multipart `file`, up to 32 MB
(`data.id` = analysis id); `GET /api/v3/analyses/{id}` (`data.attributes.status` = `completed`, `stats`).
Public API quota: 4 requests per minute, 500 per day. The hydroxide binary is about 15 MB.
