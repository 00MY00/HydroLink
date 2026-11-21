<?php

namespace Modules\HydroLink\Tests\Feature;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Accounts\AccountService;
use Modules\HydroLink\Services\Accounts\AuthStore;
use Modules\HydroLink\Services\Accounts\AuthWorker;
use Modules\HydroLink\Services\Health\HealthChecker;
use Modules\HydroLink\Services\Health\Probes;
use Modules\HydroLink\Services\Process\HydroxideProcess;
use Modules\HydroLink\Services\Process\ProcessRunner;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Process\Watchdog;
use Modules\HydroLink\Tests\TestCase;

/** Watchdog, local health checks, classification, notifications once per change, pause, removal. */
class ServiceTest extends TestCase
{
    private function connected(string $email): Account
    {
        $this->installFake();
        AccountService::login($email, 'pw-'.uniqid());
        AuthWorker::run(30);

        return Account::where('email', $email)->first();
    }

    public function testWatchdogStartsRestartsAndStops()
    {
        $this->connected('plain@example.test');
        $this->assertSame('running', Watchdog::service()['status']);
        $pid = HydroxideProcess::pid();
        $this->assertTrue(HydroxideProcess::running($pid));
        $this->assertTrue(Probes::smtp(1025)['ok']);
        $this->assertTrue(Probes::imap(1143)['ok']);

        ProcessRunner::terminate($pid, 0);   // forced stop (SIGKILL)
        usleep(300000);
        $this->assertFalse(HydroxideProcess::running($pid));
        $this->assertSame('running', Watchdog::service()['status'], 'restarted by the watchdog');
        $this->assertNotSame($pid, HydroxideProcess::pid());

        State::want(['running' => false]);
        $this->assertSame('stopped', Watchdog::service()['status']);
        $this->assertFalse(HydroxideProcess::running());
    }

    public function testCrashLoopStopsAfterTooManyStartsAndNotifiesOnce()
    {
        $this->connected('plain@example.test');
        State::put('starts', array_fill(0, 5, time()));
        $this->assertSame('failed', Watchdog::service()['status']);
        $this->assertSame('failed', Watchdog::service()['status']);
        $this->assertCount(1, self::$sent, 'one notification for the change of state');
        $this->assertStringContainsString('HL-SVC-01', self::$sent[0]);
        State::requestRestart();
        $this->assertSame('running', Watchdog::service()['status'], 'a manual restart clears the loop guard');
    }

    public function testHealthOfAccountsAndClassification()
    {
        $ok = $this->connected('plain@example.test');
        $api = $this->connected('api@example.test');
        Watchdog::service();
        $health = HealthChecker::run();
        $this->assertSame('ok', $health['accounts'][$ok->id]);
        $this->assertSame('HL-API-01', $health['accounts'][$api->id]);
        $this->assertSame('HL-API-01', $api->fresh()->error_code);
        // the same problem again: no new notification
        $count = count(self::$sent);
        HealthChecker::run();
        $this->assertCount($count, self::$sent);
        // three times unknown on one account: "broken API" alert
        HealthChecker::run();
        $this->assertTrue(collect(self::$sent)->contains(function ($t) {
            return strpos($t, 'HL-API-02') !== false;
        }));
    }

    public function testWrongBridgePasswordNeedsReconnection()
    {
        $a = $this->connected('plain@example.test');
        Watchdog::service();
        $a->setBridgePassword(base64_encode(random_bytes(32)));
        $a->save();
        $this->assertSame('HL-AUTH-02', HealthChecker::account($a));
    }

    public function testRecoveryIsNotifiedOnce()
    {
        $this->assertTrue(\Modules\HydroLink\Services\Support\Settings::notifyRecovery(), 'recovery notifications on by default');
        \Modules\HydroLink\Services\Notify\Alerts::raise('binary', 'HL-BIN-04');
        \Modules\HydroLink\Services\Notify\Alerts::raise('binary', 'HL-BIN-04');
        $this->assertCount(1, self::$sent, 'problem notified once: '.implode(' | ', self::$sent));
        $this->assertTrue((bool) \Modules\HydroLink\Entities\Alert::where('alert_key', 'binary')->value('active'), 'alert active');
        \Modules\HydroLink\Services\Notify\Alerts::resolve('binary');
        $this->assertFalse((bool) \Modules\HydroLink\Entities\Alert::where('alert_key', 'binary')->value('active'), 'alert resolved');
        \Modules\HydroLink\Services\Notify\Alerts::resolve('binary');
        $this->assertCount(2, self::$sent, 'problem + return to normal: '.implode(' | ', self::$sent));
    }

    public function testPauseStopsFetchingOfTheMailbox()
    {
        $a = $this->connected('plain@example.test');
        $mailbox = \App\Mailbox::find($a->mailbox_id);
        $this->assertTrue($mailbox->isInActive());
        AccountService::setPaused($a, true);
        $this->assertFalse(\App\Mailbox::find($a->mailbox_id)->isInActive());
    }

    public function testRemovalKeepsTheMailboxAndRemovesTheSession()
    {
        $a = $this->connected('plain@example.test');
        Watchdog::service();
        $mailboxId = $a->mailbox_id;
        AccountService::remove($a);
        Watchdog::service();
        $this->assertFalse(AuthStore::has('plain@example.test'));
        $this->assertNotNull(\App\Mailbox::find($mailboxId), 'mailbox and tickets kept');
        $this->assertFalse(\App\Mailbox::find($mailboxId)->isInActive());
        $this->assertSame('stopped', State::real()['service']['status'], 'nothing left to serve');
    }
}
