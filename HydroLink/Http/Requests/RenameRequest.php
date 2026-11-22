<?php

declare(strict_types=1);

namespace Modules\HydroLink\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** New name of an account: 40 characters at most (limit of FreeScout's mailbox names), empty = default name. */
class RenameRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules()
    {
        return ['name' => ['nullable', 'string', 'max:40', 'regex:/^[^\x00-\x1F\x7F<>]*$/u']];
    }

    public function messages()
    {
        return ['name.max' => __('hydrolink::messages.name_too_long'), 'name.regex' => __('hydrolink::messages.name_invalid')];
    }
}
