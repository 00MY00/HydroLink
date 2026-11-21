<?php

declare(strict_types=1);

namespace Modules\HydroLink\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\HydroLink\Features\Registry;

/** One setting saved by the panel (automatic save): only the keys declared by a feature, with its rules. */
class SettingRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules()
    {
        $definitions = Registry::settings();
        $key = (string) $this->input('key');
        $rules = isset($definitions[$key]) ? $definitions[$key]['rules'] : 'prohibited';

        return [
            'key'   => ['required', 'string', 'in:'.implode(',', array_keys($definitions))],
            'value' => $rules === 'prohibited' ? ['nullable'] : $rules,
        ];
    }
}
