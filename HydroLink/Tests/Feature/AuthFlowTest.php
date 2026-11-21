<?php

namespace Modules\HydroLink\Tests\Feature;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Accounts\AccountService;
use Modules\HydroLink\Services\Accounts\AuthRequests;
use Modules\HydroLink\Services\Accounts\AuthWorker;
use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Tests\TestCase;

/** Sign-in flows driven by the cron worker: no 2FA, 2FA, human verification, two passwords, refusals, time out. */
class AuthFlowTest extends TestCase
{
    const PASSWORD = 'test-only-password';

    private function signIn(string $email, ?callable $before = null, string $mailboxPassword = ''): Account
    {
        $this->installFake();
        $account = AccountService::login($email, self::PASSWORD, $mailboxPassword);
        if ($before) {
            $before($account);
        }
        AuthWorker::run(60);

        return $account->fresh();
    }

    public function testAccountWithout2faIsConnectedAndItsMailboxCreated()
    {
        $a = $this->signIn('plain@example.test');
        $this->assertSame(Account::CONNECTED, $a->status);
        $this->assertSame(44, strlen($a->bridgePassword()));
        $mailbox = \App\Mailbox::find($a->mailbox_id);
        $this->assertNotNull($mailbox);
        $this->assertSame('127.0.0.1', $mailbox->in_server);
        $this->assertSame(1143, (int) $mailbox->in_port);
        $this->assertSame(1025, (int) $mailbox->out_port);
        $this->assertSame('plain@example.test', $mailbox->in_username);
        $this->assertSame($a->bridgePassword(), $mailbox->in_password);
        $this->assertSame(\App\Mailbox::IN_ENCRYPTION_NONE, (int) $mailbox->in_encryption);
        $this->assertTrue($mailbox->isInActive());
        $this->assertSame([], AuthRequests::pending(), 'request (with the password) deleted');
        $log = file_get_contents(Paths::authLog($a->id));
        $this->assertStringNotContainsString(self::PASSWORD, $log);
        $this->assertStringNotContainsString($a->bridgePassword(), $log);
    }

    public function testExistingMailboxIsLinkedNotDuplicated()
    {
        $mailbox = new \App\Mailbox();
        $mailbox->fill(['name' => 'Existing', 'email' => 'plain@example.test']);
        $mailbox->save();
        $a = $this->signIn('plain@example.test');
        $this->assertSame($mailbox->id, (int) $a->mailbox_id);
        $this->assertSame(1, \App\Mailbox::where('email', 'plain@example.test')->count());
    }

    public function testTwoFactorCodeGivenFromThePanel()
    {
        $a = $this->signIn('totp@example.test', function ($account) {
            AuthRequests::giveTotp($account->id, '123456');
        });
        $this->assertSame(Account::CONNECTED, $a->status);
        $this->assertStringNotContainsString('123456', file_get_contents(Paths::authLog($a->id)));
    }

    public function testWrongTwoFactorCode()
    {
        $a = $this->signIn('totp@example.test', function ($account) {
            AuthRequests::giveTotp($account->id, '000000');
        });
        $this->assertSame('HL-AUTH-05', $a->error_code);
    }

    public function testHumanVerificationUrlShownThenContinued()
    {
        $a = $this->signIn('captcha@example.test', function ($account) {
            AuthRequests::verificationDone($account->id);
        });
        $this->assertSame(Account::CONNECTED, $a->status);
        $log = file_get_contents(Paths::authLog($a->id));
        $this->assertStringContainsString('verify.proton.me', $log);
        $this->assertStringNotContainsString('TEST-TOKEN-123', $log, 'verification token masked');
    }

    public function testTwoPasswordAccount()
    {
        $this->assertSame('HL-AUTH-06', $this->signIn('twopw@example.test')->error_code);
        $this->assertSame(Account::CONNECTED, $this->signIn('twopw@example.test', null, 'mailbox-secret')->status);
    }

    public function testRefusedSignIn()
    {
        $a = $this->signIn('wrong@example.test');
        $this->assertSame('HL-AUTH-03', $a->error_code);
        $this->assertNull($a->bridge_password);
    }

    public function testNoAnswerInTime()
    {
        config(['hydrolink.auth_wait_timeout' => 2]);
        $a = $this->signIn('totp@example.test');
        $this->assertSame('HL-AUTH-07', $a->error_code);
    }

    public function testExpiredRequestAsksForThePasswordAgain()
    {
        $this->installFake();
        $account = AccountService::login('plain@example.test', self::PASSWORD);
        touch(Paths::requests($account->id.'.json'));
        config(['hydrolink.auth_request_ttl' => -1]);
        AuthWorker::run(10);
        $this->assertSame(Account::NEEDS_PASSWORD, $account->fresh()->status);
    }
}
