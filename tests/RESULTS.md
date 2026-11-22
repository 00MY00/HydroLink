# Test results

Automated by the CI on every push (`.github/workflows/ci.yml`); the integration job runs a real FreeScout (Docker,
PHP 8.2, MariaDB 10.11) with HydroLink and PHPUnit (`HydroLink/Tests/Feature`). No Proton account is used in the
CI: `HydroLink/Tests/Support/fake-hydroxide.php` reproduces hydroxide's messages and files, and the hydroxide binary
built by the CI is started for real.

| Scenario | How | Status |
|---|---|---|
| Free account without 2FA | `AuthFlowTest::testAccountWithout2faIsConnectedAndItsMailboxCreated` | CI |
| Free account with 2FA (good / wrong code) | `AuthFlowTest::testTwoFactorCodeGivenFromThePanel`, `testWrongTwoFactorCode` | CI |
| Human verification (CAPTCHA) | `AuthFlowTest::testHumanVerificationUrlShownThenContinued` | CI |
| Two-password account | `AuthFlowTest::testTwoPasswordAccount` | CI |
| Refused sign-in, time out, expired request | `AuthFlowTest` | CI |
| Mailbox created / existing one linked | `AuthFlowTest` | CI |
| Forced stop of hydroxide: restart by the watchdog | `ServiceTest::testWatchdogStartsRestartsAndStops` (SIGKILL) | CI |
| Crash loop guard, one notification | `ServiceTest::testCrashLoopStopsAfterTooManyStartsAndNotifiesOnce` | CI |
| Binary update: success | `BinaryTest::testInstallThenUpToDate` | CI |
| Binary update: invalid checksum | `BinaryTest::testWrongChecksumKeepsTheCurrentBinary` | CI |
| Binary update: VirusTotal above the threshold | `BinaryTest::testVirusTotalAboveThresholdQuarantines` | CI |
| Binary update: start failure, rollback | `BinaryTest::testFailedRestartRollsBack`, `testBinaryThatDoesNotStartIsRefused` | CI |
| Errors 9001 / 401 / unknown answer: classification | `tests/unit/CoreTest.php`, `ServiceTest::testHealthOfAccountsAndClassification`, `testWrongBridgePasswordNeedsReconnection` | CI |
| Notification sent once, reminder, recovery | `ServiceTest`, `testRecoveryIsNotifiedOnce` | CI |
| `proc_open` disabled in PHP-FPM | the panel only writes `run/desired.json`: `PanelTest::testServiceAndBinaryButtonsOnlyWriteTheDesiredState` | CI |
| Real hydroxide binary: self test, serve, IMAP/SMTP | `RealBinaryTest` | CI |
| Panel for administrators only, autosave, validation | `PanelTest` | CI |
| UpdateGuard health command | `PanelTest::testHealthCommandForUpdateGuard`, `testHealthFailsOnAnyCodeProblem`, `hydrolink:health --json` in `run.sh` | CI |
| Keep-alive: read without marking read, e-mail to itself skipped by FreeScout then trashed, other address, failures, interval, "Now" | `KeepAliveTest` (test double) | CI |
| Keep-alive with a real Proton account (does Proton count it?) | not verifiable from outside (docs/OPEN-QUESTIONS.md 10) | manual |
| UpdateGuard probe checks (`updateguard.health_check`) | `PanelTest::testUpdateGuardProbeGetsRuntimeChecksForHydroLinkOnly` | CI |
| Secret scan | job `secrets` (gitleaks, blocks the release) | CI |
| Receive a mail in FreeScout, reply received by the recipient (real free account) | by hand on a test instance | to do on first install |
| Panel on phone, tablet, desktop | CSS rules (one column under 768 px, 44 px targets); by hand on the instance | to do on first install |
| Module update through UpdateGuard | contract checked by `CatalogTest` (module.json, version.txt, SHA256SUMS); first real update with 1.0.1 | to do on next release |
