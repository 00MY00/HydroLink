<?php

namespace Modules\HydroLink\Tests\Feature;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Accounts\AuthRequests;
use Modules\HydroLink\Services\Process\State;
use Modules\HydroLink\Services\Support\Settings;
use Modules\HydroLink\Tests\TestCase;

/** The panel: administrators only, actions written for the cron (never a process), automatic save of settings. */
class PanelTest extends TestCase
{
    public function testPanelRendersForAdministrators()
    {
        $this->installFake();
        Account::create(['email' => 'shown@example.test', 'status' => Account::WAITING_2FA]);
        $r = $this->actingAs($this->admin())->get(route('hydrolink.panel'));
        $r->assertStatus(200);
        // assertions on the content directly (the TestResponse helpers of Laravel 5.5 do not work with PHPUnit 11)
        $this->assertStringContainsString('shown@example.test', $r->getContent());
        $this->assertStringContainsString('HL-ENV-05', $r->getContent());   // no cron run yet in this test storage
        $state = $this->actingAs($this->admin())->get(route('hydrolink.state'));
        $state->assertStatus(200);
        $this->assertSame(['statuses', 'accounts', 'env', 'tick'], array_keys(json_decode($state->getContent(), true)));
    }

    public function testMenuEntriesForAdministratorsOnly()
    {
        // Refresh's left bar (refresh.rail_items), FreeScout's Manage menu and the mailbox settings menu (phones)
        $this->actingAs($this->admin());
        $items = \Eventy::filter('refresh.rail_items', []);
        $this->assertSame(['HydroLink'], array_column($items, 'label'));
        $this->assertSame(route('hydrolink.panel'), $items[0]['url']);
        $this->assertStringEndsWith('/img/module.svg?v='.\Modules\HydroLink\Providers\HydroLinkServiceProvider::version(), $items[0]['icon']);
        foreach (['menu.manage.after_mailboxes', 'mailboxes.settings.menu'] as $hook) {
            ob_start();
            \Eventy::action($hook, null);
            $this->assertStringContainsString(route('hydrolink.panel'), ob_get_clean(), $hook);
        }

        $user = new \App\User();
        $user->fill(['first_name' => 'U', 'last_name' => 'Ser', 'email' => 'user-'.uniqid().'@example.test', 'password' => bcrypt(uniqid())]);
        $user->role = \App\User::ROLE_USER;
        $user->save();
        $this->actingAs($user);
        $this->assertSame([], \Eventy::filter('refresh.rail_items', []));
    }

    public function testNonAdministratorsAreRefused()
    {
        $user = new \App\User();
        $user->fill(['first_name' => 'U', 'last_name' => 'Ser', 'email' => 'user-'.uniqid().'@example.test', 'password' => bcrypt(uniqid())]);
        $user->role = \App\User::ROLE_USER;
        $user->save();
        $r = $this->actingAs($user)->get(route('hydrolink.panel'));
        $this->assertNotSame(200, $r->getStatusCode());
    }

    public function testAddAccountQueuesARequestOnly()
    {
        $this->installFake();
        $r = $this->actingAs($this->admin())->postJson(route('hydrolink.accounts.store'), ['email' => 'New@Example.test', 'password' => 'secret-pass']);
        $r->assertStatus(200);
        $this->assertTrue(json_decode($r->getContent(), true)['ok']);
        $a = Account::where('email', 'new@example.test')->first();
        $this->assertSame(Account::PENDING, $a->status);
        $this->assertSame('secret-pass', AuthRequests::get($a->id)['password']);
        $this->assertStringNotContainsString('secret-pass', file_get_contents(\Modules\HydroLink\Services\Support\Paths::requests($a->id.'.json')), 'encrypted at rest');
    }

    public function testValidationErrorsAreExplicit()
    {
        $this->actingAs($this->admin())->postJson(route('hydrolink.accounts.store'), ['email' => 'not-an-address'])->assertStatus(422);
        $this->actingAs($this->admin())->postJson(route('hydrolink.settings'), ['key' => 'unknown', 'value' => 1])->assertStatus(422);
        $this->actingAs($this->admin())->postJson(route('hydrolink.settings'), ['key' => 'vt_threshold', 'value' => 999])->assertStatus(422);
    }

    public function testSettingsAutosaveAndSecretEncrypted()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('hydrolink.settings'), ['key' => 'auto_update', 'value' => true])->assertStatus(200);
        $this->assertTrue(Settings::autoUpdate());
        $this->actingAs($admin)->postJson(route('hydrolink.settings'), ['key' => 'vt_key', 'value' => 'abcDEF123'])->assertStatus(200);
        $this->assertSame('abcDEF123', Settings::vtKey());
        $this->assertNotSame('abcDEF123', \Option::get('hydrolink.vt_key'));
        $this->actingAs($admin)->postJson(route('hydrolink.settings'), ['key' => 'channels', 'value' => ['mail', 'nope']])->assertStatus(200);
        $this->assertSame(['mail'], Settings::channels(), 'unknown channels dropped');
    }

    public function testServiceAndBinaryButtonsOnlyWriteTheDesiredState()
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('hydrolink.service'), ['action' => 'stop'])->assertStatus(200);
        $this->assertFalse(State::desired()['running']);
        $this->actingAs($admin)->postJson(route('hydrolink.binary'))->assertStatus(200);
        $this->assertGreaterThan(0, State::desired()['binary_request']);
        $this->assertArrayNotHasKey('pid', State::real(), 'no process started by the web');
    }

    public function testTwoFactorCodeOnlyWhenAsked()
    {
        $a = Account::create(['email' => 'x@example.test', 'status' => Account::CONNECTED]);
        $this->actingAs($this->admin())->postJson(route('hydrolink.accounts.totp', ['id' => $a->id]), ['code' => '123456'])->assertStatus(422);
        $a->status = Account::WAITING_2FA;
        $a->save();
        $this->actingAs($this->admin())->postJson(route('hydrolink.accounts.totp', ['id' => $a->id]), ['code' => '123456'])->assertStatus(200);
        $this->assertSame('123456', AuthRequests::takeInput($a->id, 'totp'));
    }

    public function testHealthCommandForUpdateGuard()
    {
        $this->assertSame(0, \Artisan::call('hydrolink:health', ['--json' => true]));
        $json = json_decode(trim(\Artisan::output()), true);
        $this->assertSame('ok', $json['status']);
        $this->assertSame('HydroLink', $json['module']);
        $this->assertSame(['files', 'classes', 'database', 'routes', 'views', 'translations', 'storage', 'commands', 'schedule'], array_keys($json['checks']));
        $this->assertSame(['ok'], array_values(array_unique($json['checks'])), json_encode($json['checks']));
        // runtime state: reported, never a failure (no binary and no cron in this test storage)
        $this->assertSame('WARN', $json['runtime']['hydrolink binary']['result']);
        $this->assertSame('WARN', $json['runtime']['hydrolink cron']['result']);
    }

    public function testHealthFailsOnAnyCodeProblem()
    {
        // no DDL here (it would end the test transaction): the exit code follows every code check
        $H = \Modules\HydroLink\Services\Health\ModuleHealth::class;
        $this->assertTrue($H::healthy(['files' => 'ok', 'routes' => 'ok']));
        $this->assertFalse($H::healthy(['files' => 'ok', 'database' => 'fail: table hydrolink_alerts missing']));
    }

    public function testUpdateGuardProbeGetsRuntimeChecksForHydroLinkOnly()
    {
        $checks = \Eventy::filter('updateguard.health_check', [], 'HydroLink', 'hydrolink');
        $this->assertSame(['hydrolink binary', 'hydrolink cron', 'hydrolink service', 'hydrolink accounts'], array_keys($checks));
        foreach ($checks as $check) {
            $this->assertContains($check['result'], ['PASS', 'WARN']);
        }
        $this->assertSame([], \Eventy::filter('updateguard.health_check', [], 'WorkflowPacks', 'workflowpacks'));
    }
}
