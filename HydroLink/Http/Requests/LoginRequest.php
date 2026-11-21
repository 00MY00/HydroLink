<?php

declare(strict_types=1);

namespace Modules\HydroLink\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Add / reconnect a Proton account. Passwords are only checked for length, never logged. */
class LoginRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules()
    {
        return [
            // new account: the address; reconnection: the address of the existing account is used
            'email'            => $this->route() && $this->route()->getName() === 'hydrolink.accounts.store' ? 'required|email|max:191' : 'nullable',
            'password'         => 'required|string|max:512',
            'mailbox_password' => 'nullable|string|max:512',
        ];
    }
}
