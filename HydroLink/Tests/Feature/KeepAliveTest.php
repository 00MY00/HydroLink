<?php

namespace Modules\HydroLink\Tests\Feature;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Accounts\AccountService;
use Modules\HydroLink\Services\Accounts\AuthWorker;
use Modules\HydroLink\Services\KeepAlive\KeepAlive;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Process\Watchdog;
use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Services\Support\Settings;
use Modules\HydroLink\Tests\TestCase;

/** Keep-alive through hydroxide (test double): read, e-mail to itself (+ clean-up, skipped by FreeScout), other address. */
class KeepAliveTest extends TestCase
{
    private function connected(string $email): Account
    {
        $this->installFake();
        AccountService::login($email, 'pw-'.uniqid());
        AuthWorker::run(30);
        $this->assertSame('running', Watchdog::service()['status']);

        return Account::where('email', $email)->first();
    }

    private function mailFile(): string
    {
        return Paths::configHome().'/hydroxide/mail.json';
    }

    private function actions(): string
    {
        $file = Paths::configHome().'/hydroxide/actions.log';

        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    private function deliver(string $email, string $subject): void
    {
        $mail = is_file($this->mailFile()) ? json_decode(file_get_contents($this->mailFile()), true) : [];
        $mail[$email][] = ['uid' => count($mail[$email] ?? []) + 1, 'subject' => $subject, 'from' => 'customer@example.test', 'body' => 'hello', 'seen' => false];
        file_put_contents($this->mailFile(), json_encode($mail));
    }

    public function testReadOpensTheNewestEmailWithoutMarkingItRead()
    {
        $a = $this->connected('plain@example.test');
        $this->deliver('plain@example.test', 'Question of a customer');
        $this->assertSame('ok', KeepAlive::perform($a));
        $this->assertNotNull($a->fresh()->keepalive_at);
        $this->assertNull($a->fresh()->keepalive_code);
        $this->assertStringContainsString('plain@example.test read 1 peek', $this->actions());
        $this->assertStringNotContainsString('seen', $this->actions(), 'an unread e-mail stays unread for FreeScout');
        $this->assertStringNotContainsString(' sent ', $this->actions(), 'nothing is sent');
    }

    public function testEmailToItselfIsSentSkippedByFreeScoutThenReadAndTrashed()
    {
        $a = $this->connected('plain@example.test');
        Settings::set('keepalive_method', 'self');
        $this->assertSame('ok', KeepAlive::perform($a));
        $this->assertMatchesRegularExpression('~plain@example\.test sent plain@example\.test \[HydroLink keep-alive\] [a-f0-9]{16}~', $this->actions());

        $tokens = KeepAlive::state()['tokens'];
        $this->assertCount(1, $tokens);
        $token = array_keys($tokens)[0];
        $this->assertFalse($tokens[$token]['done']);
        // FreeScout's fetch: skipped (not a ticket); other e-mails are not concerned
        $this->assertFalse(\Eventy::filter('fetch_emails.should_save_thread', true, ['subject' => '[HydroLink keep-alive] '.$token]));
        $this->assertTrue(\Eventy::filter('fetch_emails.should_save_thread', true, ['subject' => '[HydroLink keep-alive] 0123456789abcdef']));
        $this->assertTrue(\Eventy::filter('fetch_emails.should_save_thread', true, ['subject' => 'Question of a customer']));

        // next run of the cron: the e-mail is read, marked read, moved to the trash
        KeepAlive::run();
        $this->assertTrue(KeepAlive::state()['tokens'][$token]['cleaned']);
        $this->assertMatchesRegularExpression('~read 1\n.*seen 1\n.*trash 1~s', $this->actions());
        $this->assertSame([], json_decode(file_get_contents($this->mailFile()), true)['plain@example.test']);
    }

    public function testOtherAddressAndFailuresAreReported()
    {
        $a = $this->connected('plain@example.test');
        Settings::set('keepalive_method', 'other');
        $this->assertSame('HL-KA-03', KeepAlive::perform($a), 'no address');
        $this->assertSame('HL-KA-03', $a->fresh()->keepalive_code);
        $this->assertTrue(collect(self::$sent)->contains(function ($t) {
            return strpos($t, 'HL-KA-03') !== false;
        }));

        Settings::set('keepalive_to', 'reject@example.test');
        $this->assertSame('HL-KA-02', KeepAlive::perform($a->fresh()), 'refused by the SMTP server');

        Settings::set('keepalive_to', 'owner@example.test');
        $this->assertSame('ok', KeepAlive::perform($a->fresh()));
        $this->assertStringContainsString('plain@example.test sent owner@example.test', $this->actions());
        $this->assertNull($a->fresh()->keepalive_code, 'error cleared');
        $this->assertFalse((bool) \Modules\HydroLink\Entities\Alert::where('alert_key', 'keepalive:'.$a->id)->value('active'));
    }

    public function testDueAfterTheIntervalOrWhenAskedAndNotRepeatedOnFailure()
    {
        $a = Account::create(['email' => 'due-'.uniqid().'@example.test', 'status' => Account::CONNECTED, 'connected_at' => now()]);
        $this->assertFalse(KeepAlive::due($a), 'a sign-in is itself an activity');
        $a->connected_at = now()->subDays(31);
        $this->assertTrue(KeepAlive::due($a), 'default interval: 30 days');
        Settings::set('keepalive_days', 60);
        $this->assertFalse(KeepAlive::due($a));
        Settings::set('keepalive_days', 30);
        Settings::set('keepalive', false);
        $this->assertFalse(KeepAlive::due($a), 'switched off');
        $this->assertTrue(KeepAlive::due($a, [$a->id => time()]), '"Now" in the panel works even when switched off');

        Settings::set('keepalive', true);
        $a->save();
        KeepAlive::perform($a);   // no service in this test: fails
        $this->assertNotNull($a->fresh()->keepalive_code);
        $this->assertFalse(KeepAlive::due($a->fresh()), 'no new try within the hour after a failure');
    }

    public function testNowButtonOnlyQueuesARequestForTheCron()
    {
        $a = Account::create(['email' => 'now-'.uniqid().'@example.test', 'status' => Account::CONNECTED]);
        $this->actingAs($this->admin())->postJson(route('hydrolink.accounts.keepalive', ['id' => $a->id]))->assertStatus(200);
        $this->assertGreaterThan(0, (int) State::desired()['keepalive_now'][$a->id]);
        $this->assertNull($a->fresh()->keepalive_at, 'nothing done by the web request');

        $waiting = Account::create(['email' => 'wait-'.uniqid().'@example.test', 'status' => Account::WAITING_2FA]);
        $r = $this->actingAs($this->admin())->postJson(route('hydrolink.accounts.keepalive', ['id' => $waiting->id]));
        $this->assertNotSame(200, $r->getStatusCode());
    }

    public function testSettingsOfTheCard()
    {
        $this->actingAs($this->admin())->postJson(route('hydrolink.settings'), ['key' => 'keepalive_method', 'value' => 'self'])->assertStatus(200);
        $this->assertSame('self', Settings::keepAliveMethod());
        $this->actingAs($this->admin())->postJson(route('hydrolink.settings'), ['key' => 'keepalive_method', 'value' => 'login'])->assertStatus(422);
        $this->actingAs($this->admin())->postJson(route('hydrolink.settings'), ['key' => 'keepalive_to', 'value' => 'not-an-address'])->assertStatus(422);
        $this->actingAs($this->admin())->postJson(route('hydrolink.settings'), ['key' => 'keepalive_days', 'value' => 0])->assertStatus(422);
        $this->actingAs($this->admin())->postJson(route('hydrolink.settings'), ['key' => 'keepalive_days', 'value' => 90])->assertStatus(200);
        $this->assertSame(90, Settings::keepAliveDays());
        $page = $this->actingAs($this->admin())->get(route('hydrolink.panel'))->getContent();
        $this->assertStringContainsString('id="hl-card-keepalive"', $page);
    }
}
