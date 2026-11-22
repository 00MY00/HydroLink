# Open questions and cautious choices

Points that could not be fully verified, and the choice made.

1. **Storage folder.** The request said "storage/ of the module". Files are kept in FreeScout's
   `storage/app/hydrolink/` instead: UpdateGuard and FreeScout's own update replace the whole module folder, which
   would delete the binary and the hydroxide sessions at each update.

2. **Patch of hydroxide.** Unattended sign-in needs upstream pull request #359 (open, not merged). HydroLink applies
   it, minus the change that makes a human verification fatal without a terminal. When the pull request is merged
   upstream (or changed), `build/hydroxide.lock` and the patch must be updated together.

3. **Wrong 2FA code / wrong password codes.** hydroxide prints Proton's message as `[<code>] <message>`. Only 9001,
   10013 and HTTP 401 are named in its source. Other codes are classified by context: an error after a 2FA code was
   sent is `HL-AUTH-05`; a `[<code>]` error before is `HL-AUTH-03`; anything else unknown is `HL-API-01`.

4. **"Last successful fetch" per account.** FreeScout only keeps a global `fetch_emails_last_successful_run`. The
   health check uses it (`HL-SVC-04`) plus its own IMAP login per account.

5. **VirusTotal threshold.** The threshold is compared with the number of engines that flag the file as
   `malicious`; `suspicious` is shown nowhere and does not block (Go binaries often get heuristic hits). A VirusTotal
   error other than the quota postpones the update (`HL-VT-01` / `HL-VT-02`) rather than installing unchecked.

6. **Session renewal with 2FA.** When Proton invalidates a refresh token (10013), hydroxide cannot sign in again
   alone if 2FA is on (`auth/auth.go:137-139`). HydroLink reports `HL-AUTH-02` and asks for a reconnection: no
   automatic retry with stored passwords (HydroLink keeps none).

7. **Telegram / Slack.** Their FreeScout modules are paid and their code was not available: no direct integration.
   They can be connected through the `hydrolink.notification_channels` filter.

8. **Tests with a real Proton account.** The CI cannot use a Proton account. Sign-in flows are tested with a test
   double that reproduces hydroxide's messages; the real binary is started in the CI (IMAP / SMTP answer locally).
   Receiving and sending through a real free account must be checked once by hand (see `tests/RESULTS.md`).

9. **Screenshots.** README screenshots need a running instance with an account; they are to be added from the first
   real installation.

10. **Does activity through hydroxide count for Proton's inactivity rule?** Proton's terms (section 3) and its support
    page (proton.me/support/inactive-accounts, read on 2026-11-23) name signing in on Proton's own apps and using a
    product once signed in (inbox, reading an e-mail). They do not mention Bridge, IMAP clients or third-party API
    clients, and Proton gives no way to see an account's "last activity". The keep-alive therefore does the closest
    real actions available through hydroxide (reading an e-mail, sending one); whether they count cannot be checked
    from outside. Signing in once a year on proton.me stays the advice in the README.
