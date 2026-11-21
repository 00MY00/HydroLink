<?php

declare(strict_types=1);

namespace Modules\HydroLink\Services\Accounts;

use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Services\Health\ErrorClassifier;
use Modules\HydroLink\Services\Process\ProcessRunner;
use Modules\HydroLink\Services\Support\Paths;
use Modules\HydroLink\Services\Support\Secrets;
use Modules\HydroLink\Services\Support\Settings;

/**
 * One "hydroxide auth <email>" run, driven step by step by the worker (cron). The passwords go through environment
 * variables of the child process only (build/patches/0001), the 2FA code and the "verification done" line through
 * stdin when hydroxide asks for them. The output is logged with every secret masked.
 */
final class AuthSession
{
    private $account;
    private $request;
    private $bridge;
    private $proc;
    private $parser;
    private $waitingSince = 0;
    private $totpSent = false;
    private $secrets;

    public function __construct(Account $account, array $request)
    {
        $this->account = $account;
        $this->request = $request;
        $this->bridge = $account->bridgePassword() ?: Secrets::newBridgePassword();
        $this->parser = new OutputParser();
        $this->secrets = array_filter([$request['password'], $request['mailbox_password'], $this->bridge]);
    }

    public function start(): bool
    {
        $env = Paths::processEnv([
            'HYDROXIDE_PASSWORD'        => $this->request['password'],
            'HYDROXIDE_BRIDGE_PASSWORD' => $this->bridge,
        ]);
        if ($this->request['mailbox_password'] !== '') {
            $env['HYDROXIDE_MAILBOX_PASSWORD'] = $this->request['mailbox_password'];
        }
        @file_put_contents(Paths::authLog((int) $this->account->id), '-- hydrolink auth '.date('c')."\n");
        $this->proc = ProcessRunner::open([Paths::bin(), 'auth', $this->account->email], $env);
        $this->status(Account::RUNNING);

        return $this->proc !== null;
    }

    /** One step: reads the output, answers hydroxide. Returns false once the process has ended. */
    public function step(): bool
    {
        $this->read();
        $this->handlePrompts();
        if ($this->waitingSince && time() - $this->waitingSince > (int) Settings::config('auth_wait_timeout', 600)) {
            proc_terminate($this->proc['proc'], 9);
            $this->finish(-1, 'HL-AUTH-07');

            return false;
        }
        $status = proc_get_status($this->proc['proc']);
        if ($status['running']) {
            return true;
        }
        $this->read();
        $this->finish((int) $status['exitcode']);

        return false;
    }

    private function read(): void
    {
        $out = (string) stream_get_contents($this->proc['pipes'][1]);
        $err = (string) stream_get_contents($this->proc['pipes'][2]);
        if ($out !== '' || $err !== '') {
            $this->parser->feed($out, $err);
            @file_put_contents(Paths::authLog((int) $this->account->id), Secrets::mask($out.$err, $this->secrets), FILE_APPEND);
        }
    }

    private function handlePrompts(): void
    {
        $url = $this->parser->newVerificationUrl();
        if ($url !== '') {
            $this->account->captcha_url = $url;
            $this->wait(Account::WAITING_CAPTCHA);
        }
        if ($this->parser->newTotpPrompt()) {
            $this->totpSent = false;
            $this->wait(Account::WAITING_2FA);
        }
        if ($this->account->status === Account::WAITING_2FA) {
            $code = AuthRequests::takeInput((int) $this->account->id, 'totp');
            if ($code !== '') {
                $this->secrets[] = $code;
                $this->send($code);
                $this->totpSent = true;
            }
        } elseif ($this->account->status === Account::WAITING_CAPTCHA && AuthRequests::takeInput((int) $this->account->id, 'verified') !== '') {
            $this->account->captcha_url = null;
            $this->send('');
        }
    }

    private function send(string $line): void
    {
        @fwrite($this->proc['pipes'][0], $line."\n");
        @fflush($this->proc['pipes'][0]);
        $this->waitingSince = 0;
        $this->status(Account::RUNNING);
    }

    private function wait(string $status): void
    {
        $this->waitingSince = time();
        $this->status($status);
    }

    private function finish(int $code, string $forced = ''): void
    {
        foreach ($this->proc['pipes'] as $pipe) {
            @fclose($pipe);
        }
        @proc_close($this->proc['proc']);
        AuthRequests::delete((int) $this->account->id);
        if ($forced === '' && $code === 0 && $this->parser->saved()) {
            $this->succeed();

            return;
        }
        $error = Secrets::mask($this->parser->lastError(), $this->secrets);
        $this->account->setError($forced !== '' ? $forced : $this->errorCode($error), $error);
        $this->account->captcha_url = null;
        $this->account->save();
    }

    private function errorCode(string $error): string
    {
        $code = ErrorClassifier::classify($error);
        if ($this->totpSent && !in_array($code, ['HL-NET-01', 'HL-AUTH-06'], true)) {
            return 'HL-AUTH-05';
        }
        // a Proton refusal of the login itself ("[<code>] message" before any 2FA)
        if ($code === 'HL-API-01' && ErrorClassifier::apiCode($error) > 0) {
            return 'HL-AUTH-03';
        }

        return $code;
    }

    private function succeed(): void
    {
        // a reconnection: the running hydroxide keeps the old session in memory until it restarts
        if ($this->account->connected_at) {
            \Modules\HydroLink\Services\Process\State::requestRestart();
        }
        $this->account->setBridgePassword($this->bridge);
        $this->account->clearError();
        $this->account->status = Account::CONNECTED;
        $this->account->connected_at = now();
        $this->account->api_errors = 0;
        $this->account->save();
        MailboxLinker::link($this->account, $this->bridge);
    }

    private function status(string $status): void
    {
        $this->account->status = $status;
        $this->account->save();
    }
}
