<?php

declare(strict_types=1);

namespace Modules\HydroLink\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** 2FA code (TOTP: 6 digits; 8 accepted for apps that use longer codes). */
class TotpRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules()
    {
        return ['code' => ['required', 'regex:/^\d{6,8}$/']];
    }
}
