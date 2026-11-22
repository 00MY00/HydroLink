<?php

declare(strict_types=1);

namespace Modules\HydroLink\Http\Controllers;

use Illuminate\Http\Request;
use Modules\HydroLink\Entities\Account;
use Modules\HydroLink\Http\Requests\LoginRequest;
use Modules\HydroLink\Http\Requests\RenameRequest;
use Modules\HydroLink\Http\Requests\TotpRequest;
use Modules\HydroLink\Services\Accounts\AccountService;
use Modules\HydroLink\Services\Health\LogReader;
use Modules\HydroLink\Services\Support\AccountView;

class AccountsController extends Controller
{
    public function store(LoginRequest $request)
    {
        $email = strtolower(trim((string) $request->input('email')));
        $mailbox = \App\Mailbox::where('email', $email)->first();
        if (!$mailbox && \App\Mailbox::userEmailExists($email)) {
            return $this->fail($request, __('hydrolink::messages.email_is_user'));
        }
        AccountService::login($email, (string) $request->input('password'), (string) $request->input('mailbox_password', ''));

        return $this->done($request, __('hydrolink::messages.login_queued'));
    }

    public function reconnect(LoginRequest $request, $id)
    {
        $account = Account::findOrFail((int) $id);
        $blocked = AccountView::reconnectBlocked($account);
        if ($blocked !== '') {
            return $this->fail($request, $blocked);
        }
        AccountService::login($account->email, (string) $request->input('password'), (string) $request->input('mailbox_password', ''));

        return $this->done($request, __('hydrolink::messages.login_queued'));
    }

    public function totp(TotpRequest $request, $id)
    {
        $account = Account::findOrFail((int) $id);
        if ($account->status !== Account::WAITING_2FA) {
            return $this->fail($request, __('hydrolink::messages.why_no_2fa'));
        }
        AccountService::giveTotp($account, (string) $request->input('code'));

        return $this->done($request, __('hydrolink::messages.code_sent'));
    }

    public function verified(Request $request, $id)
    {
        $account = Account::findOrFail((int) $id);
        if ($account->status !== Account::WAITING_CAPTCHA) {
            return $this->fail($request, __('hydrolink::messages.why_no_captcha'));
        }
        AccountService::verificationDone($account);

        return $this->done($request, __('hydrolink::messages.verification_sent'));
    }

    public function rename(RenameRequest $request, $id)
    {
        $account = Account::findOrFail((int) $id);
        AccountService::rename($account, (string) $request->input('name', ''));

        return $this->done($request, __('hydrolink::messages.renamed'));
    }

    public function pause(Request $request, $id)
    {
        $account = Account::findOrFail((int) $id);
        $blocked = AccountView::pauseBlocked($account);
        if ($blocked !== '') {
            return $this->fail($request, $blocked);
        }
        AccountService::setPaused($account, !$account->paused);

        return $this->done($request, __($account->paused ? 'hydrolink::messages.paused' : 'hydrolink::messages.resumed'));
    }

    public function destroy(Request $request, $id)
    {
        $account = Account::findOrFail((int) $id);
        if ($request->input('confirm') !== $account->email) {
            return $this->fail($request, __('hydrolink::messages.confirm_needed'));
        }
        AccountService::remove($account);

        return $this->done($request, __('hydrolink::messages.removed'));
    }

    public function logs($id)
    {
        $account = Account::findOrFail((int) $id);

        return response()->json(LogReader::forAccount((int) $account->id, $account->email));
    }
}
